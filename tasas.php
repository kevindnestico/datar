<?php
$titulo = 'Tasas de interés';
require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/tiles.php';
?>

<h1>Tasas de interés</h1>
<p class="lead">Tasas de referencia, de depósitos y de préstamos, y cuánto rindió un plazo fijo frente a la inflación. Todas en porcentaje nominal anual salvo que se indique.</p>

<?php render_tiles([
    [7,    'BADLAR bancos privados', '%', 2, 365],
    [44,   'TAMAR bancos privados',  '%', 2, 365],
    [1207, 'Plazo fijo 30 días',     '%', 2, 365],
    [1203, 'Caja de ahorro',         '%', 2, 365],
    [14,   'Préstamos personales',   '%', 2, 365],
    [1215, 'Tarjetas de crédito',    '%', 2, 730],
    [1240, 'Hipotecarios UVA',       '%', 2, 730],
    [1213, 'Plazo fijo en dólares 30 días', '%', 2, 365],
]); ?>

<h2>Plazo fijo vs. inflación</h2>
<div class="grid-2">
    <section id="c-real"></section>
    <section id="c-pf-infl"></section>
</div>

<h2>Tasas de referencia y de depósitos</h2>
<div class="grid-2">
    <section id="c-ref"></section>
    <section id="c-pasivas"></section>
</div>

<h2>Tasas de préstamos</h2>
<div class="grid-2">
    <section id="c-activas"></section>
    <section id="c-spread"></section>
</div>

<?php
$script = SPARK_SCRIPT . <<<'JS'

/** Tasa mensual de un plazo fijo a 30 días (promedio del mes) contra la inflación del mes. */
async function pfVsInflacion(desde) {
    const [pf, inf] = await DatAR.series([1207, 27], desde);
    const prom = DatAR.promedioMensual(pf.datos);
    return inf.datos.filter((p) => prom[p.fecha.slice(0, 7)] != null).map((p) => {
        const im = prom[p.fecha.slice(0, 7)] / 100 * 30 / 365;
        return { fecha: p.fecha, pf: im * 100, inf: p.valor, real: ((1 + im) / (1 + p.valor / 100) - 1) * 100 };
    });
}

DatAR.chart('#c-real', {
    title: 'Rendimiento real de un plazo fijo a 30 días', sub: 'Tasa mensual del plazo fijo descontada la inflación del mes, en %',
    range: '5A', ranges: ['1A', '2A', '5A', '10A', 'Todo'], unit: '%', decimals: 2,
    note: 'Positivo: el plazo fijo le ganó a la inflación ese mes. Negativo: perdió poder de compra.',
    load: async (desde) => {
        const d = await pfVsInflacion(desde);
        return [{ label: 'Tasa real mensual', kind: 'bar', signed: true, data: d.map((p) => ({ x: DatAR.toTs(p.fecha), y: p.real })) }];
    },
});
DatAR.chart('#c-pf-infl', {
    title: 'Tasa mensual del plazo fijo vs. inflación mensual', sub: 'En % mensual',
    range: '5A', ranges: ['1A', '2A', '5A', '10A', 'Todo'], unit: '%', decimals: 2,
    load: async (desde) => {
        const d = await pfVsInflacion(desde);
        return [
            { label: 'Plazo fijo 30 días (mensualizada)', data: d.map((p) => ({ x: DatAR.toTs(p.fecha), y: p.pf })) },
            { label: 'Inflación mensual', data: d.map((p) => ({ x: DatAR.toTs(p.fecha), y: p.inf })) },
        ];
    },
});
DatAR.chart('#c-ref', {
    title: 'Tasas de referencia', sub: '% nominal anual',
    range: '1A', ranges: ['3M', '6M', '1A', '5A', 'Todo'], unit: '%',
    load: DatAR.fromIds([7, 44, 11], ['BADLAR', 'TAMAR', 'BAIBAR (interbancaria)']),
});
DatAR.chart('#c-pasivas', {
    title: 'Tasas de depósitos en pesos', sub: '% nominal anual',
    range: '1A', ranges: ['3M', '6M', '1A', '5A', 'Todo'], unit: '%',
    load: DatAR.fromIds([1207, 1208, 1203], ['Plazo fijo 30 días', 'Plazo fijo 60 días o más', 'Caja de ahorro']),
});
DatAR.chart('#c-activas', {
    title: 'Tasas de préstamos', sub: '% nominal anual (tarjetas: promedio mensual)',
    range: '2A', ranges: ['1A', '2A', '5A', 'Todo'], unit: '%',
    load: DatAR.fromIds([1215, 14, 13, 1217], ['Tarjetas de crédito', 'Personales', 'Adelantos en cta. cte.', 'Hipotecarios tasa fija']),
});
DatAR.chart('#c-spread', {
    title: 'Diferencia entre préstamos personales y plazo fijo', sub: 'Puntos porcentuales de tasa nominal anual',
    range: '2A', ranges: ['1A', '2A', '5A', 'Todo'], decimals: 2,
    load: async (desde) => {
        const [pp, pf] = await DatAR.series([14, 1207], desde);
        return [{ label: 'Diferencia', data: DatAR.toXY(DatAR.combinar(pp.datos, pf.datos, (a, b) => a - b)), fill: true }];
    },
});
JS;
require __DIR__ . '/partials/footer.php';
