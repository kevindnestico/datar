<?php
/**
 * Cliente para las APIs públicas del BCRA (https://www.bcra.gob.ar/BCRAyVos/catalogo-de-APIs-banco-central.asp).
 * No requieren token. Las respuestas se guardan en /cache para no golpear la API en cada visita.
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');

const BCRA_BASE      = 'https://api.bcra.gob.ar';
const BCRA_MONETARIAS = BCRA_BASE . '/estadisticas/v4.0/monetarias';
const BCRA_CAMBIARIAS = BCRA_BASE . '/estadisticascambiarias/v1.0';
const CACHE_DIR      = __DIR__ . '/../cache';
const CACHE_TTL      = 6 * 3600; // 6 horas

class BcraException extends RuntimeException {}

/** Series que usan las páginas: se precargan en caché y se exportan a JSON en la versión estática. */
const SERIES_SITIO = [1, 4, 5, 7, 11, 12, 13, 14, 15, 17, 18, 19, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 40, 44,
    1187, 1188, 1203, 1207, 1208, 1213, 1215, 1217, 1232, 1233, 1234, 1235, 1236, 1237, 1238, 1240];

/** true cuando build.php genera la versión estática para GitHub Pages. */
function es_estatico(): bool
{
    return getenv('DATAR_STATIC') === '1';
}

/** Enlace interno: en la versión estática las páginas son .html */
function enlace(string $archivo): string
{
    return es_estatico() ? preg_replace('/\.php\b/', '.html', $archivo) : $archivo;
}

/** GET a la API del BCRA, sin caché. La API a veces responde 500 de forma transitoria: se reintenta una vez. */
function bcra_http(string $url): array
{
    for ($intento = 1; $intento <= 2; $intento++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: es-AR'],
            CURLOPT_USERAGENT      => 'DatAR/2.0 (+php)',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        if ($code < 500 && $code !== 0) {
            break;
        }
        sleep(2);
    }

    $json = is_string($body) ? json_decode($body, true) : null;
    if ($code === 200 && is_array($json)) {
        return $json;
    }
    $msg = $json['errorMessages'][0] ?? ($err ?: "HTTP $code");
    $host = parse_url($url, PHP_URL_HOST);
    throw new BcraException("Error consultando $host: $msg");
}

function cache_escribir(string $file, array $data): void
{
    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0775, true);
    }
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), LOCK_EX);
}

/** GET con caché en disco. Si la API falla y hay una copia vieja, se usa esa. */
function bcra_get(string $url, int $ttl = CACHE_TTL): array
{
    $file = CACHE_DIR . '/' . sha1($url) . '.json';
    if (is_file($file) && time() - filemtime($file) < $ttl && empty($GLOBALS['BCRA_FORZAR'])) {
        return json_decode(file_get_contents($file), true);
    }
    try {
        $json = bcra_http($url);
    } catch (BcraException $e) {
        if (is_file($file)) {
            return json_decode(file_get_contents($file), true);
        }
        throw $e;
    }
    cache_escribir($file, $json);
    return $json;
}

/**
 * Serie guardada en caché que se actualiza de forma incremental: normalmente solo se piden los
 * últimos 90 días y se combinan con la historia guardada (la API tarda mucho en devolver series
 * completas). Cada $diasCompleta días se vuelve a bajar entera, por si el BCRA revisó datos viejos.
 *
 * $descargar(?string $desde) devuelve [['fecha' => 'Y-m-d', 'valor' => float, ...], ...]
 */
function serie_incremental(string $nombre, callable $descargar, int $diasCompleta = 7): array
{
    $file = CACHE_DIR . "/$nombre.json";
    $c = is_file($file) ? json_decode(file_get_contents($file), true) : null;
    if ($c && time() - filemtime($file) < CACHE_TTL && empty($GLOBALS['BCRA_FORZAR'])) {
        return $c['datos'];
    }
    try {
        if ($c && time() - $c['completa'] < $diasCompleta * 86400) {
            $datos = array_column($c['datos'], null, 'fecha');
            foreach ($descargar(date('Y-m-d', strtotime('-90 days'))) as $p) {
                $datos[$p['fecha']] = $p;
            }
            ksort($datos);
            $datos = array_values($datos);
            $completa = $c['completa'];
        } else {
            $datos = $descargar(null);
            $completa = time();
        }
    } catch (BcraException $e) {
        if ($c) {
            return $c['datos'];
        }
        throw $e;
    }
    cache_escribir($file, ['completa' => $completa, 'datos' => $datos]);
    return $datos;
}

/** Catálogo completo de variables monetarias (~1600), indexado por id. */
function bcra_variables(): array
{
    static $vars = null;
    if ($vars !== null) {
        return $vars;
    }
    $json = bcra_get(BCRA_MONETARIAS . '?limit=3000', 3 * 3600);
    $vars = [];
    foreach ($json['results'] as $v) {
        $v['descripcion'] = trim(preg_replace('/\s+/u', ' ', $v['descripcion']));
        $vars[$v['idVariable']] = $v;
    }
    return $vars;
}

/**
 * Serie histórica completa de una variable, ordenada de más vieja a más nueva.
 * Devuelve [['fecha' => 'Y-m-d', 'valor' => float], ...]
 */
function bcra_serie(int $id): array
{
    // Las descargas completas se reparten en distintos días de la semana según el id.
    return serie_incremental("serie-$id", function (?string $desde) use ($id) {
        $out = [];
        $offset = 0;
        do {
            $q = http_build_query(array_filter(['desde' => $desde, 'limit' => 3000, 'offset' => $offset]));
            $json = bcra_http(BCRA_MONETARIAS . "/$id?$q");
            $detalle = $json['results'][0]['detalle'] ?? [];
            foreach ($detalle as $p) {
                $out[] = ['fecha' => $p['fecha'], 'valor' => (float) $p['valor']];
            }
            $total = $json['metadata']['resultset']['count'] ?? 0;
            $offset += 3000;
        } while ($offset < $total && $detalle);

        usort($out, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
        return $out;
    }, 7 + $id % 7);
}

/** Recorta una serie a un rango de fechas (inclusive). */
function serie_rango(array $serie, ?string $desde, ?string $hasta): array
{
    return array_values(array_filter($serie, fn($p) =>
        (!$desde || $p['fecha'] >= $desde) && (!$hasta || $p['fecha'] <= $hasta)));
}

/** Monedas disponibles en estadísticas cambiarias. */
function bcra_divisas(): array
{
    return bcra_get(BCRA_CAMBIARIAS . '/Maestros/Divisas', 24 * 3600)['results'];
}

/** Cotizaciones de todas las monedas para una fecha (la última disponible si es null). */
function bcra_cotizaciones(?string $fecha = null): array
{
    $url = BCRA_CAMBIARIAS . '/Cotizaciones' . ($fecha ? '?fecha=' . urlencode($fecha) : '');
    return bcra_get($url, 3 * 3600)['results'];
}

/** Historial de cotización de una moneda contra el peso. */
function bcra_cotizacion_historica(string $moneda, string $desde, string $hasta, bool $conCache = true): array
{
    $out = [];
    $offset = 0;
    do {
        $q = http_build_query(['fechadesde' => $desde, 'fechahasta' => $hasta, 'limit' => 1000, 'offset' => $offset]);
        $url = BCRA_CAMBIARIAS . '/Cotizaciones/' . rawurlencode($moneda) . "?$q";
        $json = $conCache ? bcra_get($url) : bcra_http($url);
        $res = $json['results'] ?? [];
        foreach ($res as $dia) {
            $d = $dia['detalle'][0] ?? null;
            if ($d && $d['tipoCotizacion'] > 0) {
                $out[] = ['fecha' => $dia['fecha'], 'valor' => (float) $d['tipoCotizacion'], 'pase' => (float) $d['tipoPase']];
            }
        }
        $total = $json['metadata']['resultset']['count'] ?? 0;
        $offset += 1000;
    } while ($offset < $total && $res);

    usort($out, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
    return $out;
}

/** Cotizaciones de los últimos $anios años de una moneda, con actualización incremental (para build.php). */
function bcra_cotizacion_reciente(string $moneda, int $anios = 5): array
{
    $datos = serie_incremental("cot-$moneda", fn(?string $desde) => bcra_cotizacion_historica(
        $moneda, $desde ?? date('Y-m-d', strtotime("-$anios years -10 days")), date('Y-m-d'), false));
    $corte = date('Y-m-d', strtotime("-$anios years -10 days"));
    return array_values(array_filter($datos, fn($p) => $p['fecha'] >= $corte));
}

/** Último valor y el anterior de una variable, para mostrar variación en tarjetas. */
function bcra_ultimos(int $id, int $n = 2): array
{
    $json = bcra_get(BCRA_MONETARIAS . "/$id?limit=10", 3 * 3600);
    return array_slice($json['results'][0]['detalle'] ?? [], 0, $n);
}

/* ---------- Formato ---------- */

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function num(float $v, int $dec = 2): string
{
    return number_format($v, $dec, ',', '.');
}

function fecha_ar(string $iso): string
{
    [$y, $m, $d] = explode('-', substr($iso, 0, 10));
    return "$d/$m/$y";
}
