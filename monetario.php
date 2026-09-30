<?php
$titulo = 'Dinero y crédito';
require __DIR__ . '/partials/header.php';
?>

<h1>Dinero y crédito</h1>
<p class="lead">Base monetaria, agregados, depósitos y préstamos del sistema financiero. Los montos en pesos se muestran en billones (millones de millones).</p>

<h2 id="base-reservas">Base monetaria / reservas</h2>
<p class="lead" style="margin-bottom:1rem">La idea original de este sitio: dividir la base monetaria por las reservas internacionales da un “tipo de cambio de respaldo”, es decir, a cuántos pesos por dólar se cubriría toda la base con las reservas. Compararlo con el dólar mayorista muestra cuánto respaldo en dólares tiene cada peso emitido.</p>
<div class="grid-2">
    <section id="c-base-res"></section>
    <section id="c-cobertura"></section>
</div>

<h2>Base monetaria</h2>
<div class="grid-2">
    <section id="c-base"></section>
    <section id="c-m2-ia"></section>
</div>

<h2>Agregados monetarios</h2>
<div class="grid-2">
    <section id="c-agregados"></section>
    <section id="c-depositos"></section>
</div>

<h2>Crédito al sector privado</h2>
<div class="grid-2">
    <section id="c-prestamos"></section>
    <section id="c-prest-tipo"></section>
</div>

<?php
$script = <<<'JS'
const B = 1e6; // millones → billones

DatAR.chart('#c-base-res', {
    title: 'Base monetaria / reservas vs. dólar mayorista', sub: 'Pesos por dólar',
    range: '5A', ranges: ['1A', '5A', '10A', 'Todo'],
    load: async (desde) => {
        const [base, res, may] = await DatAR.series([15, 1, 5], desde);
        const ratio = DatAR.combinar(base.datos, res.datos, (b, r) => b / r);
        return [
            { label: 'Base / reservas', data: DatAR.toXY(ratio) },
            { label: 'Dólar mayorista', data: DatAR.toXY(may.datos) },
        ];
    },
});
DatAR.chart('#c-cobertura', {
    title: 'Cobertura de la base con reservas', sub: 'Reservas valuadas al mayorista, como % de la base monetaria',
    range: '5A', ranges: ['1A', '5A', '10A', 'Todo'], unit: '%', decimals: 1,
    note: 'Por encima de 100% las reservas alcanzan para comprar toda la base monetaria al tipo de cambio oficial.',
    load: async (desde) => {
        const [base, res, may] = await DatAR.series([15, 1, 5], desde);
        const resPesos = DatAR.combinar(res.datos, may.datos, (r, tc) => r * tc);
        const cob = DatAR.combinar(resPesos, base.datos, (rp, b) => rp / b * 100);
        return [{ label: 'Cobertura', data: DatAR.toXY(cob), fill: true }];
    },
});
DatAR.chart('#c-base', {
    title: 'Base monetaria y sus componentes', sub: 'Billones de pesos',
    range: '2A', ranges: ['6M', '1A', '2A', '5A', 'Todo'],
    load: DatAR.fromIds([15, 17, 18, 19],
        ['Base monetaria', 'Billetes y monedas del público', 'Efectivo en bancos', 'Encajes en cta. cte. BCRA'], [], B),
});
DatAR.chart('#c-m2-ia', {
    title: 'M2 privado: variación interanual', sub: 'Promedio móvil de 30 días, en %',
    range: '5A', ranges: ['1A', '5A', '10A', 'Todo'], unit: '%', decimals: 1,
    load: DatAR.fromIds([25], ['M2 privado i.a.']),
});
DatAR.chart('#c-agregados', {
    title: 'Agregados monetarios en pesos', sub: 'Billones de pesos. M1 ⊂ M2 ⊂ M3',
    range: '2A', ranges: ['6M', '1A', '2A', '5A', 'Todo'],
    load: DatAR.fromIds([1232, 1233, 1234], ['M1', 'M2', 'M3'], [], B),
});
DatAR.chart('#c-depositos', {
    title: 'Depósitos en pesos por tipo', sub: 'Billones de pesos',
    range: '2A', ranges: ['6M', '1A', '2A', '5A', 'Todo'],
    load: DatAR.fromIds([24, 23, 22], ['Plazo fijo', 'Cajas de ahorro', 'Cuentas corrientes'], [], B),
});
DatAR.chart('#c-prestamos', {
    title: 'Préstamos al sector privado', sub: 'Billones de pesos',
    range: '2A', ranges: ['6M', '1A', '2A', '5A', 'Todo'],
    load: DatAR.fromIds([26], ['Préstamos al sector privado'], [{ fill: true }], B),
});
DatAR.chart('#c-prest-tipo', {
    title: 'Préstamos por línea', sub: 'Billones de pesos',
    range: '2A', ranges: ['6M', '1A', '2A', '5A', 'Todo'],
    load: DatAR.fromIds([1235, 1236, 1237, 1238],
        ['Adelantos y documentos', 'Personales y tarjetas', 'Hipotecarios y prendarios', 'Otros'], [], B),
});
JS;
require __DIR__ . '/partials/footer.php';
