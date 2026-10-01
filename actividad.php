<?php
$titulo = 'Actividad económica';
$fuente = 'indec';
require __DIR__ . '/partials/header.php';
require_once __DIR__ . '/lib/indec.php';
require __DIR__ . '/partials/tiles.php';
?>

<h1>Actividad económica</h1>
<p class="lead">Cómo evoluciona la producción: el EMAE (el “PBI mensual”), la industria, la construcción y el PIB trimestral.</p>
<p class="intro-indec">Datos del <b>INDEC</b>, tomados de la API de Series de Tiempo de datos.gob.ar. Se identifican en violeta para diferenciarlos de los del BCRA.</p>

<?php render_tiles_indec([
    ['143.3_NO_PR_2004_A_31', 'EMAE: variación mensual (desest.)', '%', 1, 'var_m'],
    ['143.3_NO_PR_2004_A_21', 'EMAE: variación interanual', '%', 1, 'var_ia'],
    ['453.1_SERIE_ORIGNAL_0_0_14_46', 'Industria: variación interanual', '%', 1, 'var_ia'],
    ['33.2_ISAC_NIVELRAL_0_M_18_63', 'Construcción: variación interanual', '%', 1, 'var_ia'],
    ['31.3_UNG_2004_M_18', 'Capacidad instalada utilizada', '%', 1],
    ['9.2_PP2_2004_T_16', 'PIB: variación interanual', '%', 1, 'var_ia', 16],
    ['9.2_PPCDC_2004_T_33', 'PIB per cápita', ' US$', 0, 'nivel', 16],
]); ?>

<h2>Estimador mensual de actividad (EMAE)</h2>
<div class="grid-2">
    <section id="c-emae"></section>
    <section id="c-emae-ia"></section>
</div>

<h2>Qué sectores crecen</h2>
<div class="grid-2">
    <section id="c-sectores"></section>
    <section id="c-ind-const"></section>
</div>

<h2>Industria y PIB</h2>
<div class="grid-2">
    <section id="c-uci"></section>
    <section id="c-pib"></section>
    <section id="c-pib-pc"></section>
</div>

<?php
$sectores = array_filter(INDEC_SERIES, fn($k) => str_starts_with($k, '11.3_'), ARRAY_FILTER_USE_KEY);
$script = SPARK_SCRIPT . "\nconst SECTORES = " . json_encode(array_keys($sectores)) . ";\n" . <<<'JS'

DatAR.chart('#c-emae', {
    title: 'EMAE: serie original y desestacionalizada', sub: 'Índice base 2004 = 100',
    range: '5A', ranges: ['2A', '5A', '10A', 'Todo'], decimals: 1,
    load: DatAR.fromIndec([
        { id: '143.3_NO_PR_2004_A_21', label: 'Original', color: '--muted' },
        { id: '143.3_NO_PR_2004_A_31', label: 'Desestacionalizada', color: 1 },
    ]),
});
DatAR.chart('#c-emae-ia', {
    title: 'EMAE: variación interanual', sub: 'Contra el mismo mes del año anterior, en %',
    range: '5A', ranges: ['2A', '5A', '10A', 'Todo'], unit: '%', decimals: 1,
    load: DatAR.fromIndec([{ id: '143.3_NO_PR_2004_A_21', modo: 'var_ia', label: 'Variación interanual', kind: 'bar', signed: true }]),
});
DatAR.ranking('#c-sectores', {
    title: 'Actividad por sector', sub: 'Variación del último mes informado, en %',
    modos: [{ key: 'var_ia', label: 'Interanual' }, { key: 'acum12', label: 'Últimos 12 meses' }],
    unit: '%', decimals: 1,
    load: async (modo) => {
        const ss = await DatAR.indec(SECTORES);
        const ult = ss[0].datos.at(-1).fecha;
        const items = ss.map((s) => {
            const d = s.datos, n = d.length;
            const valor = modo === 'var_ia'
                ? (d[n - 1].valor / d[n - 13].valor - 1) * 100
                : (d.slice(-12).reduce((a, p) => a + p.valor, 0) / d.slice(-24, -12).reduce((a, p) => a + p.valor, 0) - 1) * 100;
            return { label: s.descripcion, valor };
        });
        const periodo = DatAR.periodoTxt(DatAR.toTs(ult), 'M');
        return { periodo: modo === 'var_ia' ? `${periodo} contra el mismo mes de ${Number(ult.slice(0, 4)) - 1}` : `12 meses a ${periodo} contra los 12 anteriores`, items };
    },
});
DatAR.chart('#c-ind-const', {
    title: 'Industria y construcción', sub: 'Variación interanual, en %',
    range: '2A', ranges: ['1A', '2A', '5A', 'Todo'], unit: '%', decimals: 1,
    load: DatAR.fromIndec([
        { id: '453.1_SERIE_ORIGNAL_0_0_14_46', modo: 'var_ia', label: 'Industria (IPI manufacturero)' },
        { id: '33.2_ISAC_NIVELRAL_0_M_18_63', modo: 'var_ia', label: 'Construcción (ISAC)' },
    ]),
});
DatAR.chart('#c-uci', {
    title: 'Capacidad instalada utilizada en la industria', sub: '% de la capacidad de producción en uso',
    range: '5A', ranges: ['2A', '5A', 'Todo'], unit: '%', decimals: 1,
    load: DatAR.fromIndec([{ id: '31.3_UNG_2004_M_18', label: 'Capacidad utilizada', fill: true }]),
});
DatAR.chart('#c-pib', {
    title: 'Crecimiento del PIB', sub: 'Variación interanual a precios constantes, en %',
    range: '10A', ranges: ['5A', '10A', 'Todo'], unit: '%', decimals: 1,
    load: DatAR.fromIndec([{ id: '9.2_PP2_2004_T_16', modo: 'var_ia', label: 'PIB', kind: 'bar', signed: true }]),
});
DatAR.chart('#c-pib-pc', {
    title: 'PIB per cápita en dólares', sub: 'Dólares corrientes',
    range: 'Todo', ranges: ['5A', '10A', 'Todo'], decimals: 0,
    load: DatAR.fromIndec([{ id: '9.2_PPCDC_2004_T_33', label: 'PIB per cápita', fill: true }]),
});
JS;
require __DIR__ . '/partials/footer.php';
