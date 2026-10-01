<?php
/**
 * Tarjetas de indicadores con último valor, variación contra el dato anterior y minigráfico.
 * $tiles = [[id, etiqueta, sufijo, decimales, dias_sparkline, divisor = 1], ...]
 */
function render_tiles(array $tiles): void
{
    try {
        $vars = bcra_variables();
    } catch (BcraException $e) {
        echo '<p class="alert">' . h($e->getMessage()) . '</p>';
        return;
    }
    echo '<div class="tiles">';
    foreach ($tiles as $t) {
        [$id, $label, $sufijo, $dec, $dias] = $t;
        $divisor = $t[5] ?? 1;
        $v = $vars[$id] ?? null;
        if (!$v) {
            continue;
        }
        $delta = '';
        try {
            $ult = bcra_ultimos($id);
            if (count($ult) === 2 && $ult[1]['valor'] != 0) {
                $d = $ult[0]['valor'] - $ult[1]['valor'];
                $pct = $d / abs($ult[1]['valor']) * 100;
                $flecha = $d > 0 ? '▲' : ($d < 0 ? '▼' : '=');
                $txt = $sufijo === '%' ? num($d, 2) . ' pp' : num($pct, 2) . '%';
                $delta = "<span title=\"Contra el dato del " . fecha_ar($ult[1]['fecha']) . "\">$flecha $txt</span>";
            }
        } catch (BcraException $e) {
        }
        $mostrar = num($v['ultValorInformado'] / $divisor, $dec);
        printf(
            '<div class="tile"><span class="tile-label" title="%s">%s</span><span class="tile-value">%s<small>%s</small></span>'
            . '<span class="tile-meta"><span>%s</span>%s</span><div class="spark"><canvas data-spark="%d" data-dias="%d" aria-hidden="true"></canvas></div></div>',
            h($v['descripcion'] . ' · ' . $v['unidadExpresion']),
            h($label),
            $mostrar,
            h($sufijo),
            fecha_ar($v['ultFechaInformada']),
            $delta,
            $id,
            $dias
        );
    }
    echo '</div>';
}

/**
 * Tarjetas con series del INDEC (borde violeta).
 * $tiles = [[id, etiqueta, sufijo, decimales, modo = 'nivel', puntos_sparkline = 24], ...]
 * modo: 'nivel' (el valor tal cual), 'var_m' (variación contra el período anterior), 'var_ia' (interanual).
 */
function render_tiles_indec(array $tiles): void
{
    require_once __DIR__ . '/../lib/indec.php';
    echo '<div class="tiles">';
    foreach ($tiles as $t) {
        [$id, $label, $sufijo, $dec] = $t;
        $modo = $t[4] ?? 'nivel';
        $puntos = $t[5] ?? 24;
        try {
            $bruta = indec_serie($id);
            $serie = indec_transformar($bruta, $modo);
        } catch (BcraException $e) {
            echo '<p class="alert">' . h($e->getMessage()) . '</p>';
            continue;
        }
        $n = count($serie);
        if (!$n) {
            continue;
        }
        $trim = indec_es_trimestral($bruta);
        [$ult, $prev] = [$serie[$n - 1], $serie[$n - 2] ?? null];
        $delta = '';
        if ($prev && $prev['valor'] != 0) {
            $d = $ult['valor'] - $prev['valor'];
            $flecha = $d > 0 ? '▲' : ($d < 0 ? '▼' : '=');
            $txt = $sufijo === '%' ? num($d, 1) . ' pp' : num($d / abs($prev['valor']) * 100, 1) . '%';
            $delta = '<span title="Contra ' . h(indec_periodo($prev['fecha'], $trim)) . "\">$flecha $txt</span>";
        }
        [$nombre, $unidad] = INDEC_SERIES[$id];
        printf(
            '<div class="tile tile-indec"><span class="tile-label" title="%s">%s</span><span class="tile-value">%s<small>%s</small></span>'
            . '<span class="tile-meta"><span>%s</span>%s</span><div class="spark"><canvas data-indec="%s" data-modo="%s" data-puntos="%d" aria-hidden="true"></canvas></div></div>',
            h("$nombre · $unidad · Fuente: INDEC"),
            h($label),
            num($ult['valor'], $dec),
            h($sufijo),
            h(indec_periodo($ult['fecha'], $trim)),
            $delta,
            h($id),
            h($modo),
            $puntos
        );
    }
    echo '</div>';
}

const SPARK_SCRIPT = <<<'JS'
document.querySelectorAll('canvas[data-spark]').forEach(async (cv) => {
    const desde = DatAR.isoDate(new Date(Date.now() - cv.dataset.dias * 864e5));
    try {
        const [s] = await DatAR.series(cv.dataset.spark, desde);
        DatAR.sparkline(cv, DatAR.toXY(s.datos));
    } catch (e) { cv.remove(); }
});
document.querySelectorAll('canvas[data-indec]').forEach(async (cv) => {
    try {
        const [s] = await DatAR.indec(cv.dataset.indec);
        const datos = DatAR.transformar(s.datos, cv.dataset.modo).slice(-cv.dataset.puntos);
        DatAR.sparkline(cv, DatAR.toXY(datos), '--indec');
    } catch (e) { cv.remove(); }
});
JS;
