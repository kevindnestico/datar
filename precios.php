<?php
$titulo = 'Inflación e índices';
require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/tiles.php';
$hoy = date('Y-m-d');
?>

<h1>Inflación e índices de actualización</h1>
<p class="lead">IPC, expectativas del mercado (REM) y los índices que publica el BCRA para actualizar deudas, créditos y alquileres: CER, UVA, UVI e ICL.</p>

<?php render_tiles([
    [27, 'Inflación mensual',     '%', 1, 730],
    [28, 'Inflación interanual',  '%', 1, 730],
    [29, 'Expectativa 12 meses (REM)', '%', 1, 730],
    [30, 'CER',                   '',  4, 365],
    [31, 'UVA',                   ' $', 2, 365],
    [32, 'UVI',                   ' $', 2, 365],
    [40, 'ICL (alquileres)',      '',  2, 365],
]); ?>

<h2>Inflación</h2>
<div class="grid-2">
    <section id="c-mensual"></section>
    <section id="c-acum"></section>
</div>

<h2>Índices de actualización</h2>
<div class="grid-2">
    <section id="c-indices"></section>
    <section class="card" id="calc">
        <h3 class="card-title">Calculadora de actualización</h3>
        <p class="card-sub">Actualizá un monto entre dos fechas según la variación de un índice.</p>
        <div class="form-row" style="margin-top:1rem">
            <label>Monto ($)<input id="calc-monto" type="number" value="100000" min="0" step="any" inputmode="decimal" style="width:10rem"></label>
            <label>Índice
                <select id="calc-indice">
                    <option value="31">UVA</option>
                    <option value="30">CER</option>
                    <option value="40">ICL (alquileres)</option>
                    <option value="32">UVI</option>
                    <option value="5">Dólar mayorista</option>
                </select>
            </label>
        </div>
        <div class="form-row" style="margin-top:.75rem">
            <label>Desde<input id="calc-desde" type="date" value="<?= date('Y-m-d', strtotime('-1 year')) ?>" max="<?= $hoy ?>"></label>
            <label>Hasta<input id="calc-hasta" type="date" value="<?= $hoy ?>" max="<?= $hoy ?>"></label>
        </div>
        <p style="margin:1.25rem 0 0" class="muted">Monto actualizado</p>
        <output id="calc-res" style="font-size:1.8rem;font-weight:600">…</output>
        <p class="card-sub" id="calc-det"></p>
        <p class="note" style="margin-top:1rem">Orientativo. Para contratos, verificá la fecha de corte que fija cada norma (por ejemplo, el ICL usa el valor del día de inicio y fin de cada período).</p>
    </section>
</div>

<?php
$script = SPARK_SCRIPT . <<<'JS'

DatAR.chart('#c-mensual', {
    title: 'Inflación mensual', sub: 'Variación mensual del IPC, en %',
    range: '5A', ranges: ['1A', '2A', '5A', '10A', 'Todo'], unit: '%', decimals: 1, beginAtZero: true,
    load: DatAR.fromIds([27], ['Inflación mensual'], [{ kind: 'bar' }]),
});
DatAR.chart('#c-acum', {
    title: 'Inflación acumulada en el año', sub: 'Desde enero de cada año, en %',
    range: '5A', ranges: ['2A', '5A', '10A', 'Todo'], unit: '%', decimals: 1, beginAtZero: true,
    load: async (desde) => {
        const [inf] = await DatAR.series(27, desde);
        let anio = null, acum = 1;
        const data = inf.datos.map((p) => {
            if (p.fecha.slice(0, 4) !== anio) { anio = p.fecha.slice(0, 4); acum = 1; }
            acum *= 1 + p.valor / 100;
            return { x: DatAR.toTs(p.fecha), y: (acum - 1) * 100 };
        });
        return [{ label: 'Acumulada en el año', kind: 'bar', data }];
    },
});
DatAR.chart('#c-indices', {
    title: 'UVA, ICL y dólar mayorista comparados', sub: 'Base 100 al inicio del período elegido',
    range: '2A', ranges: ['6M', '1A', '2A', '5A'], decimals: 1,
    note: 'Muestra cuánto subió cada índice en el mismo período: si la UVA sube más que el dólar, un crédito UVA se encareció en dólares.',
    load: async (desde) => {
        const ss = await DatAR.series([31, 40, 5], desde);
        const nombres = ['UVA', 'ICL (alquileres)', 'Dólar mayorista'];
        return ss.map((s, i) => {
            const base = s.datos[0]?.valor || 1;
            return { label: nombres[i], data: s.datos.map((p) => ({ x: DatAR.toTs(p.fecha), y: p.valor / base * 100 })) };
        });
    },
});

// Calculadora
const $ = (id) => document.getElementById(id);
async function valorEn(id, fecha) {
    const desde = DatAR.isoDate(new Date(Date.parse(fecha) - 15 * 864e5));
    const [s] = await DatAR.series(id, desde, fecha);
    return s.datos.at(-1); // último dato disponible hasta esa fecha
}
async function calcular() {
    const id = $('calc-indice').value, d = $('calc-desde').value, h = $('calc-hasta').value;
    const monto = parseFloat($('calc-monto').value) || 0;
    if (!d || !h) return;
    $('calc-res').textContent = '…';
    try {
        const [a, b] = await Promise.all([valorEn(id, d), valorEn(id, h)]);
        if (!a || !b) throw new Error('Sin datos para esas fechas');
        const f = b.valor / a.valor;
        $('calc-res').textContent = '$ ' + DatAR.fmt(monto * f, 2);
        $('calc-det').textContent = `Variación ${DatAR.fmt((f - 1) * 100, 2)}% · índice ${DatAR.fmt(a.valor, 4)} (${a.fecha.split('-').reverse().join('/')}) → ${DatAR.fmt(b.valor, 4)} (${b.fecha.split('-').reverse().join('/')})`;
    } catch (e) {
        $('calc-res').textContent = '—';
        $('calc-det').textContent = e.message;
    }
}
['calc-monto', 'calc-indice', 'calc-desde', 'calc-hasta'].forEach((i) => $(i).addEventListener('change', calcular));
$('calc-monto').addEventListener('input', calcular);
calcular();
JS;
require __DIR__ . '/partials/footer.php';
