<?php
$titulo = 'Trabajo y comercio exterior';
$fuente = 'indec';
require __DIR__ . '/partials/header.php';
require_once __DIR__ . '/lib/indec.php';
require __DIR__ . '/partials/tiles.php';
?>

<h1>Trabajo y comercio exterior</h1>
<p class="lead">Desocupación, informalidad y distribución del ingreso, y el intercambio comercial con el mundo: exportaciones, importaciones, precios y turismo.</p>
<p class="intro-indec">Datos del <b>INDEC</b>, tomados de la API de Series de Tiempo de datos.gob.ar. Se identifican en violeta para diferenciarlos de los del BCRA.</p>

<?php render_tiles_indec([
    ['42.3_EPH_PUNTUATAL_0_M_30', 'Desocupación', '%', 1, 'nivel', 16],
    ['52.2_ASDJ_0_0_37', 'Asalariados sin aportes jubilatorios', '%', 1, 'nivel', 16],
    ['65.1_CGI_0_0_21', 'Coeficiente de Gini', '', 3, 'nivel', 16],
    ['53.1_TRTA_0_0_37', 'Participación de los salarios en el ingreso', '%', 1, 'nivel', 16],
    ['74.3_IET_0_M_16', 'Exportaciones del mes', ' M US$', 0],
    ['74.3_IIT_0_M_25', 'Importaciones del mes', ' M US$', 0],
    ['74.3_ISC_0_M_19', 'Saldo comercial del mes', ' M US$', 0],
    ['82.2_ITI_2004_T_27', 'Términos del intercambio', '', 1, 'nivel', 16],
]); ?>

<h2>Mercado de trabajo</h2>
<div class="grid-2">
    <section id="c-desocupacion"></section>
    <section id="c-informalidad"></section>
</div>

<h2>Distribución del ingreso</h2>
<div class="grid-2">
    <section id="c-gini"></section>
    <section id="c-funcional"></section>
</div>

<h2>Comercio exterior</h2>
<div class="grid-2">
    <section id="c-expo-impo"></section>
    <section id="c-saldo"></section>
    <section id="c-expo-rubro"></section>
    <section id="c-impo-uso"></section>
</div>

<h2>Precios internacionales y turismo</h2>
<div class="grid-2">
    <section id="c-terminos"></section>
    <section id="c-turismo"></section>
</div>

<?php
$script = SPARK_SCRIPT . <<<'JS'

DatAR.chart('#c-desocupacion', {
    title: 'Tasa de desocupación', sub: '% de la población activa (EPH, total de aglomerados urbanos)',
    range: '10A', ranges: ['5A', '10A', 'Todo'], unit: '%', decimals: 1, beginAtZero: true,
    load: DatAR.fromIndec([{ id: '42.3_EPH_PUNTUATAL_0_M_30', label: 'Desocupación', fill: true }]),
});
DatAR.chart('#c-informalidad', {
    title: 'Informalidad laboral', sub: '% de asalariados sin descuento jubilatorio',
    range: '10A', ranges: ['5A', '10A', 'Todo'], unit: '%', decimals: 1, beginAtZero: true,
    load: DatAR.fromIndec([{ id: '52.2_ASDJ_0_0_37', label: 'Asalariados sin aportes', fill: true }]),
});
DatAR.chart('#c-gini', {
    title: 'Desigualdad: coeficiente de Gini', sub: 'Del ingreso per cápita familiar. 0 = igualdad total, 1 = desigualdad máxima',
    range: '10A', ranges: ['5A', '10A', 'Todo'], decimals: 3,
    load: DatAR.fromIndec([{ id: '65.1_CGI_0_0_21', label: 'Gini' }]),
});
DatAR.chart('#c-funcional', {
    title: 'Cómo se reparte el ingreso', sub: '% del ingreso total generado en la economía',
    range: 'Todo', ranges: ['5A', 'Todo'], unit: '%', decimals: 1,
    load: DatAR.fromIndec([
        { id: '53.1_TRTA_0_0_37', label: 'Salarios' },
        { id: '53.1_EBE_0_0_27', label: 'Ganancias de empresas (excedente)' },
        { id: '53.1_TIMB_0_0_25', label: 'Cuentapropistas (ingreso mixto)' },
    ]),
});

DatAR.chart('#c-expo-impo', {
    title: 'Exportaciones e importaciones', sub: 'Millones de dólares por mes',
    range: '2A', ranges: ['1A', '2A', '5A', '10A', 'Todo'], decimals: 0,
    load: DatAR.fromIndec([
        { id: '74.3_IET_0_M_16', label: 'Exportaciones' },
        { id: '74.3_IIT_0_M_25', label: 'Importaciones' },
    ]),
});
DatAR.chart('#c-saldo', {
    title: 'Saldo comercial', sub: 'Exportaciones menos importaciones, millones de dólares por mes',
    range: '2A', ranges: ['1A', '2A', '5A', '10A', 'Todo'], decimals: 0,
    load: DatAR.fromIndec([{ id: '74.3_ISC_0_M_19', label: 'Saldo', kind: 'bar', signed: true }]),
});
DatAR.chart('#c-expo-rubro', {
    title: 'Exportaciones por rubro', sub: 'Millones de dólares por mes',
    range: '2A', ranges: ['1A', '2A', '5A', 'Todo'], decimals: 0,
    load: DatAR.fromIndec([
        { id: '74.3_IEMOA_0_M_48', label: 'Manufacturas agropecuarias' },
        { id: '74.3_IEPP_0_M_35', label: 'Productos primarios' },
        { id: '74.3_IEMOI_0_M_46', label: 'Manufacturas industriales' },
        { id: '74.3_IECE_0_M_35', label: 'Combustibles y energía' },
    ]),
});
DatAR.chart('#c-impo-uso', {
    title: 'Importaciones por uso', sub: 'Millones de dólares por mes',
    range: '2A', ranges: ['1A', '2A', '5A', 'Todo'], decimals: 0,
    load: DatAR.fromIndec([
        { id: '74.3_IIBI_0_M_36', label: 'Bienes intermedios' },
        { id: '74.3_IBCPP_0_M_32', label: 'Bienes de capital y piezas' },
        { id: '74.3_IIBCVAPR_0_M_58', label: 'Consumo y autos' },
        { id: '74.3_IICL_0_M_42', label: 'Combustibles' },
    ]),
});
DatAR.chart('#c-terminos', {
    title: 'Términos del intercambio', sub: 'Índices base 2004 = 100',
    range: '10A', ranges: ['5A', '10A', 'Todo'], decimals: 1,
    note: 'Términos del intercambio = precios de exportación / precios de importación. Si sube, con lo que se exporta se pueden comprar más importaciones.',
    load: DatAR.fromIndec([
        { id: '82.2_ITI_2004_T_27', label: 'Términos del intercambio' },
        { id: '82.2_IPE_2004_T_26', label: 'Precios de exportación', dash: true },
        { id: '82.2_IPI_2004_T_26', label: 'Precios de importación', dash: true },
    ]),
});
DatAR.chart('#c-turismo', {
    title: 'Turismo internacional', sub: 'Miles de personas por trimestre (Encuesta de Turismo Internacional)',
    range: '10A', ranges: ['5A', '10A', 'Todo'], decimals: 0,
    load: DatAR.fromIndec([
        { id: '322.2_TURISMO_RETAL__23_18', label: 'Llegan (receptivo)' },
        { id: '322.2_TURISMO_EMTAL__21_100', label: 'Salen (emisivo)' },
    ]),
});
JS;
require __DIR__ . '/partials/footer.php';
