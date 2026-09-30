<?php
/**
 * Precarga en /cache las series que usan las páginas, para que ningún visitante espere a la API del BCRA.
 * Uso (idealmente desde cron, cada 3–6 horas):  php actualizar_cache.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/lib/bcra.php';

$ids = SERIES_SITIO;

// Ignora la caché vigente y vuelve a descargar (si la API falla, se conserva la copia anterior).
$GLOBALS['BCRA_FORZAR'] = true;

$t = microtime(true);
bcra_variables();
bcra_cotizaciones();
foreach ($ids as $id) {
    try {
        $n = count(bcra_serie($id));
        bcra_ultimos($id);
        echo "✓ $id ($n datos)\n";
    } catch (BcraException $e) {
        echo "✗ $id: {$e->getMessage()}\n";
    }
}
printf("Listo en %.1f s\n", microtime(true) - $t);
