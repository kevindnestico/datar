<?php
$titulo = 'Precios y salarios';
$fuente = 'indec';
require __DIR__ . '/partials/header.php';
require_once __DIR__ . '/lib/indec.php';
require __DIR__ . '/partials/tiles.php';
?>

<h1>Precios y salarios</h1>
<p class="lead">El IPC en detalle (por rubro, por región y por tipo de precio), la evolución de los salarios frente a la inflación y las líneas de pobreza e indigencia.</p>
<p class="intro-indec">Datos del <b>INDEC</b>, tomados de la API de Series de Tiempo de datos.gob.ar. Se identifican en violeta para diferenciarlos de los del BCRA.</p>

<?php render_tiles_indec([
    ['148.3_INIVELNAL_DICI_M_26', 'Inflación mensual', '%', 1, 'var_m'],
    ['148.3_INIVELNAL_DICI_M_26', 'Inflación interanual', '%', 1, 'var_ia'],
    ['148.3_INUCLEONAL_DICI_M_19', 'Inflación núcleo mensual', '%', 1, 'var_m'],
    ['148.3_IREGULANAL_DICI_M_22', 'Regulados: variación mensual', '%', 1, 'var_m'],
    ['149.1_TL_INDIIOS_OCTU_0_21', 'Salarios: variación mensual', '%', 1, 'var_m'],
    ['149.1_TL_INDIIOS_OCTU_0_21', 'Salarios: variación interanual', '%', 1, 'var_ia'],
    ['150.1_LA_POBREZA_0_D_13', 'Línea de pobreza', ' $', 0],
    ['150.1_LA_INDICIA_0_D_16', 'Línea de indigencia', ' $', 0],
]); ?>

<h2>Inflación por tipo de precio y por rubro</h2>
<div class="grid-2">
    <section id="c-categorias"></section>
    <section id="c-divisiones"></section>
</div>

<h2>Inflación por región</h2>
<div class="grid-2">
    <section id="c-regiones"></section>
    <section id="c-regiones-rank"></section>
</div>

<h2>Salarios</h2>
<div class="grid-2">
    <section id="c-salario-real"></section>
    <section id="c-salario-ia"></section>
</div>

<h2>Pobreza e indigencia</h2>
<div class="grid-2">
    <section id="c-lineas"></section>
    <section id="c-lineas-ia"></section>
</div>

<?php
$divisiones = array_keys(array_filter(INDEC_SERIES, fn($k) => str_starts_with($k, '146.3_'), ARRAY_FILTER_USE_KEY));
$regiones = array_keys(array_filter(INDEC_SERIES, fn($k) => str_starts_with($k, '145.3_ING'), ARRAY_FILTER_USE_KEY));
$script = SPARK_SCRIPT . "\nconst DIVISIONES = " . json_encode($divisiones) . ";\nconst REGIONES = " . json_encode($regiones) . ";\n" . <<<'JS'

/** Ranking de variaciones del último mes para un conjunto de índices. */
function rankingIndices(ids) {
    return async (modo) => {
        const ss = await DatAR.indec(ids);
        const ult = ss[0].datos.at(-1).fecha;
        const items = ss.map((s) => ({ label: s.descripcion, valor: DatAR.transformar(s.datos, modo).at(-1).valor }));
        return { periodo: DatAR.periodoTxt(DatAR.toTs(ult), 'M'), items };
    };
}
const MODOS = [{ key: 'var_m', label: 'Mensual' }, { key: 'var_ia', label: 'Interanual' }];

DatAR.chart('#c-categorias', {
    title: 'Inflación mensual por tipo de precio', sub: 'Variación mensual, en %',
    range: '2A', ranges: ['1A', '2A', '5A', 'Todo'], unit: '%', decimals: 1,
    note: 'Núcleo: precios sin estacionalidad ni regulación. Regulados: tarifas, combustibles, prepagas. Estacionales: frutas, verduras, ropa, turismo.',
    load: DatAR.fromIndec([
        { id: '148.3_INIVELNAL_DICI_M_26', modo: 'var_m', label: 'Nivel general' },
        { id: '148.3_INUCLEONAL_DICI_M_19', modo: 'var_m', label: 'Núcleo' },
        { id: '148.3_IREGULANAL_DICI_M_22', modo: 'var_m', label: 'Regulados' },
        { id: '148.3_IESTACINAL_DICI_M_25', modo: 'var_m', label: 'Estacionales', dash: true },
    ]),
});
DatAR.ranking('#c-divisiones', {
    title: 'Inflación por rubro', sub: 'Las 12 divisiones del IPC nacional, en %',
    modos: MODOS, unit: '%', decimals: 1, load: rankingIndices(DIVISIONES),
});
DatAR.chart('#c-regiones', {
    title: 'Inflación interanual por región', sub: 'En %',
    range: '2A', ranges: ['1A', '2A', '5A', 'Todo'], unit: '%', decimals: 1,
    load: DatAR.fromIndec(REGIONES.map((id) => ({ id, modo: 'var_ia' }))),
});
DatAR.ranking('#c-regiones-rank', {
    title: 'Inflación por región en el último mes', sub: 'En %',
    modos: MODOS, unit: '%', decimals: 1, load: rankingIndices(REGIONES),
});

DatAR.chart('#c-salario-real', {
    title: 'Salario real', sub: 'Salarios descontada la inflación. Base 100 al inicio del período',
    range: '2A', ranges: ['1A', '2A', '5A', 'Todo'], decimals: 1,
    note: 'Por encima de 100: los salarios le ganaron a la inflación desde el inicio del período elegido. Según el INDEC, el índice de salarios no registrados tiene un rezago de 5 meses: conviene leerlo con cautela.',
    load: async (desde) => {
        const ids = ['149.1_SOR_PRIADO_OCTU_0_25', '149.1_SOR_PUBICO_OCTU_0_14', '149.1_SOR_PRIADO_OCTU_0_28'];
        const [ipc, ...sal] = await DatAR.indec(['148.3_INIVELNAL_DICI_M_26', ...ids]);
        return sal.map((s) => {
            const real = DatAR.combinar(s.datos, ipc.datos, (w, p) => w / p).filter((p) => !desde || p.fecha >= desde);
            const base = real[0]?.valor || 1;
            return { label: s.descripcion, periodo: 'M', data: DatAR.toXY(real.map((p) => ({ fecha: p.fecha, valor: p.valor / base * 100 }))) };
        });
    },
});
DatAR.chart('#c-salario-ia', {
    title: 'Salarios vs. inflación', sub: 'Variación interanual, en %',
    range: '2A', ranges: ['1A', '2A', '5A', 'Todo'], unit: '%', decimals: 1,
    load: DatAR.fromIndec([
        { id: '149.1_TL_INDIIOS_OCTU_0_21', modo: 'var_ia', label: 'Salarios (total)' },
        { id: '148.3_INIVELNAL_DICI_M_26', modo: 'var_ia', label: 'Inflación', dash: true },
    ]),
});
DatAR.chart('#c-lineas', {
    title: 'Líneas de pobreza e indigencia', sub: 'Pesos corrientes por mes (valores de referencia del INDEC)',
    range: '2A', ranges: ['1A', '2A', '5A', 'Todo'], decimals: 0,
    note: 'Un hogar es pobre si sus ingresos no alcanzan la línea de pobreza (canasta básica total) e indigente si no alcanzan la de indigencia (canasta alimentaria).',
    load: DatAR.fromIndec([
        { id: '150.1_LA_POBREZA_0_D_13', label: 'Línea de pobreza' },
        { id: '150.1_LA_INDICIA_0_D_16', label: 'Línea de indigencia' },
    ]),
});
DatAR.chart('#c-lineas-ia', {
    title: 'Canasta básica vs. salarios', sub: 'Variación interanual, en %',
    range: '2A', ranges: ['1A', '2A', '5A', 'Todo'], unit: '%', decimals: 1,
    load: DatAR.fromIndec([
        { id: '150.1_LA_POBREZA_0_D_13', modo: 'var_ia', label: 'Línea de pobreza' },
        { id: '149.1_TL_INDIIOS_OCTU_0_21', modo: 'var_ia', label: 'Salarios (total)' },
    ]),
});
JS;
require __DIR__ . '/partials/footer.php';
