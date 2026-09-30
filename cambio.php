<?php
$titulo = 'Tipo de cambio';
require __DIR__ . '/partials/header.php';

try {
    $cot = bcra_cotizaciones();
    $monedas = array_values(array_filter($cot['detalle'], fn($m) => $m['tipoCotizacion'] > 0));
    usort($monedas, fn($a, $b) => strcmp($a['descripcion'], $b['descripcion']));
    $error = null;
} catch (BcraException $e) {
    $monedas = [];
    $error = $e->getMessage();
}
$principales = ['USD', 'EUR', 'BRL', 'GBP', 'CNY', 'JPY', 'CLP', 'UYU', 'CHF', 'XAU'];
?>

<h1>Tipo de cambio</h1>
<p class="lead">Cotizaciones oficiales del BCRA (Comunicación A 3500 y tipos de pase) para más de 50 monedas, su evolución histórica y un conversor.</p>

<?php if ($error): ?>
    <p class="alert"><?= h($error) ?></p>
<?php else: ?>

<div class="grid-2">
    <section class="card">
        <h3 class="card-title">Conversor</h3>
        <p class="card-sub">Cotización del <?= fecha_ar($cot['fecha']) ?></p>
        <div class="converter" style="margin-top:1rem">
            <label class="form-row" style="flex-direction:column;align-items:stretch">
                <span class="muted">Monto</span>
                <input id="conv-monto" type="number" value="100" min="0" step="any" inputmode="decimal">
            </label>
            <span class="eq"></span>
            <label class="form-row" style="flex-direction:column;align-items:stretch">
                <span class="muted">Moneda</span>
                <select id="conv-moneda">
                    <?php foreach ($monedas as $m): ?>
                        <option value="<?= h($m['codigoMoneda']) ?>" data-cot="<?= $m['tipoCotizacion'] ?>" <?= $m['codigoMoneda'] === 'USD' ? 'selected' : '' ?>>
                            <?= h($m['codigoMoneda'] . ' — ' . ucfirst(mb_strtolower($m['descripcion']))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <p style="margin:1.25rem 0 0" class="muted">Equivale a</p>
        <output id="conv-res" style="font-size:1.8rem;font-weight:600"></output>
        <p class="card-sub" id="conv-inv"></p>
        <p style="margin-top:1rem">
            <button class="btn-link" type="button" id="conv-swap">Invertir: pesos → moneda</button>
        </p>
    </section>

    <section class="card">
        <h3 class="card-title">Cotizaciones del día</h3>
        <p class="card-sub"><?= count($monedas) ?> monedas al <?= fecha_ar($cot['fecha']) ?>. Pesos por unidad y tipo de pase (unidades por dólar).</p>
        <input id="filtro" type="search" placeholder="Buscar moneda…" style="width:100%;margin:.75rem 0 .5rem" aria-label="Buscar moneda">
        <div class="table-wrap" style="max-height:330px">
            <table id="tabla-monedas">
                <thead><tr><th>Moneda</th><th class="num">Pesos</th><th class="num">Pase (x US$)</th></tr></thead>
                <tbody>
                <?php foreach ($monedas as $m): ?>
                    <tr data-codigo="<?= h($m['codigoMoneda']) ?>" style="cursor:pointer" title="Ver historial">
                        <td><b><?= h($m['codigoMoneda']) ?></b> <span class="muted"><?= h(ucfirst(mb_strtolower($m['descripcion']))) ?></span></td>
                        <td class="num"><?= num($m['tipoCotizacion'], $m['tipoCotizacion'] < 1 ? 6 : 2) ?></td>
                        <td class="num"><?= $m['tipoPase'] > 0 ? num($m['tipoPase'], 4) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<h2>Evolución de una moneda</h2>
<div class="form-row" style="margin-bottom:12px">
    <label>Moneda
        <select id="hist-moneda">
            <?php foreach ($monedas as $m): ?>
                <option value="<?= h($m['codigoMoneda']) ?>" <?= $m['codigoMoneda'] === 'EUR' ? 'selected' : '' ?>><?= h($m['codigoMoneda'] . ' — ' . ucfirst(mb_strtolower($m['descripcion']))) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</div>
<section id="c-hist"></section>

<h2>Dólar oficial</h2>
<div class="grid-2">
    <section id="c-may-min"></section>
    <section id="c-brecha"></section>
</div>

<?php endif; ?>

<?php
$script = <<<'JS'
const selConv = document.getElementById('conv-moneda');
const monto = document.getElementById('conv-monto');
const res = document.getElementById('conv-res');
const inv = document.getElementById('conv-inv');
const swap = document.getElementById('conv-swap');
let aPesos = true;
function convertir() {
    if (!selConv) return;
    const opt = selConv.selectedOptions[0];
    const cot = parseFloat(opt.dataset.cot);
    const m = parseFloat(monto.value) || 0;
    res.textContent = aPesos ? `$ ${DatAR.fmt(m * cot, 2)}` : `${DatAR.fmt(m / cot, 4)} ${opt.value}`;
    inv.textContent = `1 ${opt.value} = $ ${DatAR.fmt(cot, cot < 1 ? 6 : 2)} · $ 1 = ${DatAR.fmt(1 / cot, 6)} ${opt.value}`;
    swap.textContent = aPesos ? 'Invertir: pesos → moneda' : 'Invertir: moneda → pesos';
}
selConv?.addEventListener('change', convertir);
monto?.addEventListener('input', convertir);
swap?.addEventListener('click', () => { aPesos = !aPesos; convertir(); });
convertir();

const filtro = document.getElementById('filtro');
filtro?.addEventListener('input', () => {
    const q = filtro.value.toLowerCase();
    document.querySelectorAll('#tabla-monedas tbody tr').forEach((tr) => { tr.hidden = !tr.textContent.toLowerCase().includes(q); });
});

const selHist = document.getElementById('hist-moneda');
if (selHist) {
    const hist = DatAR.chart('#c-hist', {
        title: 'Cotización histórica', sub: 'Pesos por unidad de moneda extranjera',
        range: '1A', ranges: ['1M', '3M', '6M', '1A', '2A', '5A'],
        load: async (desde) => {
            const m = selHist.value;
            const datos = await DatAR.cotizacion(m, desde);
            return [{ label: selHist.selectedOptions[0].textContent.trim(), data: DatAR.toXY(datos) }];
        },
    });
    selHist.addEventListener('change', () => hist.reload());
    document.querySelectorAll('#tabla-monedas tbody tr').forEach((tr) => tr.addEventListener('click', () => {
        selHist.value = tr.dataset.codigo;
        hist.reload();
        document.getElementById('c-hist').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }));

    DatAR.chart('#c-may-min', {
        title: 'Mayorista vs. minorista', sub: 'Pesos por dólar',
        range: '1A', ranges: ['1M', '6M', '1A', '5A', 'Todo'],
        load: DatAR.fromIds([5, 4], ['Mayorista (A 3500)', 'Minorista (promedio vendedor)']),
    });
    DatAR.chart('#c-brecha', {
        title: 'Diferencia minorista / mayorista', sub: 'Cuánto más caro está el dólar minorista que el mayorista, en %',
        range: '1A', ranges: ['6M', '1A', '5A', 'Todo'], unit: '%', decimals: 2,
        load: async (desde) => {
            const [may, min] = await DatAR.series([5, 4], desde);
            const d = DatAR.combinar(min.datos, may.datos, (a, b) => (a / b - 1) * 100);
            return [{ label: 'Diferencia', data: DatAR.toXY(d), fill: true }];
        },
    });
}
JS;
require __DIR__ . '/partials/footer.php';
