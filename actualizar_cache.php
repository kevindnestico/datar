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

$ids = [1, 4, 5, 7, 11, 12, 13, 14, 15, 17, 18, 19, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 40, 44,
    1187, 1188, 1203, 1207, 1208, 1213, 1215, 1217, 1232, 1233, 1234, 1235, 1236, 1237, 1238, 1240];

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
