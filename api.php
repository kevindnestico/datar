<?php
/**
 * Endpoint JSON que consumen los gráficos del sitio.
 *
 *   api.php?action=serie&ids=1,5&desde=2024-01-01&hasta=2026-12-31
 *   api.php?action=cotizacion&moneda=EUR&desde=2025-01-01&hasta=2026-12-31
 *   api.php?action=variables
 *   api.php?action=indec&ids=143.3_NO_PR_2004_A_21,74.3_IET_0_M_16   (serie completa)
 */
require_once __DIR__ . '/lib/bcra.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=900');

function fecha_param(string $name): ?string
{
    $v = $_GET[$name] ?? '';
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

function responder(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($_GET['action'] ?? '') {
        case 'serie':
            $ids = array_slice(array_filter(array_map('intval', explode(',', $_GET['ids'] ?? ''))), 0, 8);
            if (!$ids) {
                responder(['error' => 'Falta el parámetro ids'], 400);
            }
            $vars = bcra_variables();
            $out = [];
            foreach ($ids as $id) {
                if (!isset($vars[$id])) {
                    responder(['error' => "Variable $id inexistente"], 404);
                }
                $v = $vars[$id];
                $out[] = [
                    'id'           => $id,
                    'descripcion'  => $v['descripcion'],
                    'unidad'       => $v['unidadExpresion'],
                    'periodicidad' => $v['periodicidad'],
                    'datos'        => serie_rango(bcra_serie($id), fecha_param('desde'), fecha_param('hasta')),
                ];
            }
            responder(['series' => $out]);

        case 'cotizacion':
            $moneda = strtoupper($_GET['moneda'] ?? 'USD');
            if (!preg_match('/^[A-Z]{3}$/', $moneda)) {
                responder(['error' => 'Moneda inválida'], 400);
            }
            $hasta = fecha_param('hasta') ?? date('Y-m-d');
            $desde = fecha_param('desde') ?? date('Y-m-d', strtotime('-1 year'));
            responder(['moneda' => $moneda, 'datos' => bcra_cotizacion_historica($moneda, $desde, $hasta)]);

        case 'indec':
            require_once __DIR__ . '/lib/indec.php';
            $ids = array_slice(array_filter(explode(',', $_GET['ids'] ?? '')), 0, 16);
            $out = [];
            foreach ($ids as $id) {
                if (!isset(INDEC_SERIES[$id])) {
                    responder(['error' => "Serie del INDEC no disponible: $id"], 404);
                }
                [$nombre, $unidad] = INDEC_SERIES[$id];
                $out[] = ['id' => $id, 'descripcion' => $nombre, 'unidad' => $unidad, 'datos' => indec_serie($id)];
            }
            responder(['series' => $out]);

        case 'indec_catalogo':
            require_once __DIR__ . '/lib/indec.php';
            header('Cache-Control: public, max-age=3600');
            responder(indec_catalogo());

        case 'variables':
            responder(['variables' => array_values(bcra_variables())]);

        default:
            responder(['error' => 'Acción desconocida'], 400);
    }
} catch (BcraException $e) {
    responder(['error' => $e->getMessage()], 502);
}
