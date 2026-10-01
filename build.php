<?php
/**
 * Genera la versión estática del sitio en dist/ para publicarla en GitHub Pages:
 *   - descarga las series que usan las páginas y las guarda como JSON en dist/data/
 *   - renderiza cada página PHP a HTML
 *   - copia los assets
 *
 * Uso: php build.php   (lo corre .github/workflows/pages.yml cada 4 horas)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/lib/bcra.php';
require_once __DIR__ . '/lib/indec.php';

const DIST    = __DIR__ . '/dist';
const PAGINAS = ['index.php', 'cambio.php', 'monetario.php', 'tasas.php', 'precios.php', 'explorador.php',
    'actividad.php', 'precios-salarios.php', 'trabajo-comercio.php'];
const MONEDAS = ['USD', 'EUR', 'BRL'];

function escribir(string $ruta, string $contenido): void
{
    @mkdir(dirname($ruta), 0775, true);
    file_put_contents($ruta, $contenido);
}

function json(mixed $data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
}

function paso(string $msg): void
{
    fwrite(STDERR, $msg . "\n");
}

$t = microtime(true);
exec('rm -rf ' . escapeshellarg(DIST));

// Limpia de cache/ las respuestas que nadie usa hace una semana (p. ej. rangos de fechas viejos).
foreach (glob(CACHE_DIR . '/*.json') ?: [] as $f) {
    if (time() - filemtime($f) > 7 * 86400) {
        unlink($f);
    }
}

// 1. Datos: siempre se vuelven a pedir; si la API falla, bcra_get usa la copia de cache/.
$GLOBALS['BCRA_FORZAR'] = true;
$vars = bcra_variables(); // si esto falla no hay sitio que generar: el build termina con error
escribir(DIST . '/data/variables.json', json(['variables' => array_values($vars)]));
bcra_cotizaciones();

$fallas = 0;
foreach (SERIES_SITIO as $id) {
    try {
        $v = $vars[$id];
        $datos = array_map(fn($p) => [$p['fecha'], $p['valor']], bcra_serie($id));
        bcra_ultimos($id);
        escribir(DIST . "/data/serie/$id.json", json([
            'id' => $id, 'descripcion' => $v['descripcion'], 'unidad' => $v['unidadExpresion'], 'datos' => $datos,
        ]));
        paso(sprintf('✓ serie %d (%d datos)', $id, count($datos)));
    } catch (Throwable $e) {
        $fallas++;
        paso("✗ serie $id: {$e->getMessage()}");
    }
}

foreach (MONEDAS as $m) {
    try {
        $datos = array_map(fn($p) => [$p['fecha'], $p['valor']], bcra_cotizacion_reciente($m));
        escribir(DIST . "/data/cotizacion/$m.json", json(['moneda' => $m, 'datos' => $datos]));
        paso(sprintf('✓ cotización %s (%d datos)', $m, count($datos)));
    } catch (Throwable $e) {
        paso("✗ cotización $m: {$e->getMessage()}"); // el navegador la pedirá directo al BCRA
    }
}

// Series del INDEC (datos.gob.ar)
escribir(DIST . '/data/indec/_catalogo.json', json(INDEC_SERIES));
$fallasIndec = 0;
foreach (array_keys(INDEC_SERIES) as $id) {
    try {
        $datos = array_map(fn($p) => [$p['fecha'], $p['valor']], indec_serie($id));
        [$nombre, $unidad] = INDEC_SERIES[$id];
        escribir(DIST . "/data/indec/$id.json", json(['id' => $id, 'descripcion' => $nombre, 'unidad' => $unidad, 'datos' => $datos]));
        paso(sprintf('✓ INDEC %s (%d datos)', $id, count($datos)));
    } catch (Throwable $e) {
        $fallasIndec++;
        paso("✗ INDEC $id: {$e->getMessage()}");
    }
}

if ($fallas > count(SERIES_SITIO) / 4 || $fallasIndec > count(INDEC_SERIES) / 4) {
    paso("Demasiadas series fallaron (BCRA: $fallas, INDEC: $fallasIndec). No se publica para no pisar la versión anterior.");
    exit(1);
}

// 2. Páginas: cada una en su propio proceso (así se comportan igual que servidas por PHP), usando la caché recién cargada.
foreach (PAGINAS as $pagina) {
    $cmd = 'DATAR_STATIC=1 ' . escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr ' . escapeshellarg(__DIR__ . "/$pagina");
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__);
    $html = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0 || trim($err) !== '' || str_contains($html, 'class="alert"')) {
        paso("✗ $pagina falló:\n$err");
        exit(1);
    }
    escribir(DIST . '/' . str_replace('.php', '.html', $pagina), $html);
    paso("✓ $pagina");
}

// 3. Assets y extras
exec('cp -R ' . escapeshellarg(__DIR__ . '/assets') . ' ' . escapeshellarg(DIST . '/assets'));
escribir(DIST . '/base.html', '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0; url=monetario.html#base-reservas"><link rel="canonical" href="monetario.html#base-reservas">');
escribir(DIST . '/.nojekyll', '');

paso(sprintf('Sitio generado en dist/ en %.1f s', microtime(true) - $t));
