<?php
$titulo = 'Panel';
require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/tiles.php';
?>

<h1>Economía argentina en datos</h1>
<p class="lead">Los principales indicadores del Banco Central, actualizados a partir de su API pública.</p>

<?php render_tiles([
    [5,  'Dólar mayorista',        ' $',  2, 180],
    [4,  'Dólar minorista',        ' $',  2, 180],
    [1,  'Reservas internacionales', ' millones US$', 0, 365],
    [27, 'Inflación mensual',      '%',   1, 730],
    [28, 'Inflación interanual',   '%',   1, 730],
    [29, 'Inflación esperada (12 meses)', '%', 1, 730],
    [7,  'Tasa BADLAR',            '%',   2, 365],
    [44, 'Tasa TAMAR',             '%',   2, 365],
    [15, 'Base monetaria',         ' billones $', 2, 365, 1e6],
    [26, 'Préstamos al sector privado', ' billones $', 2, 365, 1e6],
    [31, 'UVA',                    ' $',  2, 365],
    [40, 'Índice de alquileres (ICL)', '', 2, 365],
]); ?>

<h2>Tipo de cambio y reservas</h2>
<div class="grid-2">
    <section id="c-dolar"></section>
    <section id="c-reservas"></section>
</div>

<h2>Inflación</h2>
<div class="grid-2">
    <section id="c-infl-mensual"></section>
    <section id="c-infl-anual"></section>
</div>

<?php
$script = SPARK_SCRIPT . <<<'JS'

DatAR.chart('#c-dolar', {
    title: 'Dólar mayorista y bandas cambiarias',
    sub: 'Pesos por dólar (Com. A 3500)',
    range: '1A', ranges: ['1M', '6M', '1A', '5A', 'Todo'],
    load: DatAR.fromIds([5, 1187, 1188], ['Mayorista', 'Banda inferior', 'Banda superior'],
        [{}, { color: '--muted', dash: true }, { color: '--muted', dash: true }]),
});
DatAR.chart('#c-reservas', {
    title: 'Reservas internacionales',
    sub: 'Millones de dólares',
    range: '5A', ranges: ['6M', '1A', '5A', '10A', 'Todo'], decimals: 0,
    load: DatAR.fromIds([1], ['Reservas'], [{ fill: true }]),
});
DatAR.chart('#c-infl-mensual', {
    title: 'Inflación mensual', sub: 'Variación mensual del IPC (INDEC), en %',
    range: '2A', ranges: ['1A', '2A', '5A', '10A', 'Todo'], unit: '%', decimals: 1, beginAtZero: true,
    load: DatAR.fromIds([27], ['Inflación mensual'], [{ kind: 'bar' }]),
});
DatAR.chart('#c-infl-anual', {
    title: 'Inflación interanual y expectativas', sub: 'En %. Expectativa: mediana del REM a 12 meses',
    range: '5A', ranges: ['2A', '5A', '10A', 'Todo'], unit: '%', decimals: 1,
    load: DatAR.fromIds([28, 29], ['Interanual', 'Esperada próximos 12 meses'], [{}, { dash: true }]),
});
JS;
require __DIR__ . '/partials/footer.php';
