<?php
/**
 * Cliente para las APIs públicas del BCRA (https://www.bcra.gob.ar/BCRAyVos/catalogo-de-APIs-banco-central.asp).
 * No requieren token. Las respuestas se guardan en /cache para no golpear la API en cada visita.
 */

const BCRA_BASE      = 'https://api.bcra.gob.ar';
const BCRA_MONETARIAS = BCRA_BASE . '/estadisticas/v4.0/monetarias';
const BCRA_CAMBIARIAS = BCRA_BASE . '/estadisticascambiarias/v1.0';
const CACHE_DIR      = __DIR__ . '/../cache';
const CACHE_TTL      = 6 * 3600; // 6 horas

class BcraException extends RuntimeException {}

/** GET con caché en disco. Si la API falla y hay una copia vieja, se usa esa. */
function bcra_get(string $url, int $ttl = CACHE_TTL): array
{
    $file = CACHE_DIR . '/' . sha1($url) . '.json';
    if (is_file($file) && time() - filemtime($file) < $ttl && empty($GLOBALS['BCRA_FORZAR'])) {
        return json_decode(file_get_contents($file), true);
    }

    // La API a veces responde 500 de forma transitoria: se reintenta una vez.
    for ($intento = 1; $intento <= 2; $intento++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: es-AR'],
            CURLOPT_USERAGENT      => 'DatAR/2.0 (+php)',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        if ($code < 500 && $code !== 0) {
            break;
        }
        sleep(1);
    }

    $json = is_string($body) ? json_decode($body, true) : null;
    if ($code === 200 && is_array($json)) {
        if (!is_dir(CACHE_DIR)) {
            mkdir(CACHE_DIR, 0775, true);
        }
        file_put_contents($file, $body, LOCK_EX);
        return $json;
    }

    if (is_file($file)) {
        return json_decode(file_get_contents($file), true);
    }
    $msg = $json['errorMessages'][0] ?? ($err ?: "HTTP $code");
    throw new BcraException("Error consultando la API del BCRA: $msg");
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
    $out = [];
    $offset = 0;
    do {
        $json = bcra_get(BCRA_MONETARIAS . "/$id?limit=3000&offset=$offset");
        $detalle = $json['results'][0]['detalle'] ?? [];
        foreach ($detalle as $p) {
            $out[] = ['fecha' => $p['fecha'], 'valor' => (float) $p['valor']];
        }
        $total = $json['metadata']['resultset']['count'] ?? 0;
        $offset += 3000;
    } while ($offset < $total && $detalle);

    usort($out, fn($a, $b) => strcmp($a['fecha'], $b['fecha']));
    return $out;
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
function bcra_cotizacion_historica(string $moneda, string $desde, string $hasta): array
{
    $out = [];
    $offset = 0;
    do {
        $q = http_build_query(['fechadesde' => $desde, 'fechahasta' => $hasta, 'limit' => 1000, 'offset' => $offset]);
        $json = bcra_get(BCRA_CAMBIARIAS . '/Cotizaciones/' . rawurlencode($moneda) . "?$q");
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
