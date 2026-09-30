<?php
$titulo = 'Explorador de series';
require __DIR__ . '/partials/header.php';

try {
    $vars = bcra_variables();
    $categorias = [];
    foreach ($vars as $v) {
        $categorias[$v['categoria']][] = $v;
    }
    // "Principales Variables" primero; después, las categorías más chicas (suelen ser las más útiles).
    uksort($categorias, fn($a, $b) => ($b === 'Principales Variables') <=> ($a === 'Principales Variables')
        ?: count($categorias[$a]) <=> count($categorias[$b]) ?: strcmp($a, $b));
    $error = null;
} catch (BcraException $e) {
    $categorias = [];
    $error = $e->getMessage();
}
$periodicidad = ['D' => 'diaria', 'M' => 'mensual', 'T' => 'trimestral', 'A' => 'anual'];
$monedaTxt = ['ML' => 'en pesos', 'ME' => 'en moneda extranjera', 'MEyML' => 'pesos y moneda extranjera'];
?>

<h1>Explorador de series</h1>
<p class="lead">Las <?= count($vars ?? []) ?> series estadísticas del BCRA. Elegí hasta 4 para graficarlas juntas, compararlas en base 100 o descargarlas en CSV.</p>

<?php if ($error): ?>
    <p class="alert"><?= h($error) ?></p>
<?php else: ?>
<div class="explorer">
    <aside class="card">
        <input id="buscar" type="search" placeholder="Buscar: reservas, plazo fijo, M2…" style="width:100%" aria-label="Buscar serie">
        <p class="card-sub" id="buscar-info" style="margin-top:.5rem"></p>
        <div class="var-list" id="lista">
            <?php foreach ($categorias as $cat => $lista): ?>
                <details <?= $cat === 'Principales Variables' ? 'open' : '' ?>>
                    <summary><?= h($cat) ?> <span class="muted">(<?= count($lista) ?>)</span></summary>
                    <?php foreach ($lista as $v): ?>
                        <button type="button" class="var-item" data-id="<?= $v['idVariable'] ?>" aria-pressed="false"
                                data-q="<?= h(mb_strtolower($v['descripcion'] . ' ' . $v['categoria'] . ' ' . ($monedaTxt[$v['moneda']] ?? '') . ' ' . $v['unidadExpresion'] . ' ' . $v['idVariable'])) ?>">
                            <?= h($v['descripcion']) ?>
                            <span class="muted">#<?= $v['idVariable'] ?> · <?= h($monedaTxt[$v['moneda']] ?? $v['moneda']) ?> · <?= h($v['unidadExpresion']) ?> · <?= $periodicidad[$v['periodicidad']] ?? $v['periodicidad'] ?> · <?= fecha_ar($v['primerFechaInformada']) ?>–<?= fecha_ar($v['ultFechaInformada']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>
        </div>
    </aside>

    <div>
        <div class="chips" id="chips"></div>
        <div class="form-row" style="margin-bottom:12px">
            <label style="flex-direction:row;align-items:center;gap:.4rem">
                <input type="checkbox" id="base100"> Comparar en base 100
            </label>
            <button class="btn-link" type="button" id="compartir">Copiar enlace a esta vista</button>
        </div>
        <section id="c-explorer"><p class="muted">Elegí una serie de la lista.</p></section>
    </div>
</div>
<?php endif; ?>

<?php
$script = <<<'JS'
const MAX = 4;
const lista = document.getElementById('lista');
if (lista) {
    const desc = {};
    lista.querySelectorAll('.var-item').forEach((b) => { desc[b.dataset.id] = b.firstChild.textContent.trim(); });

    const params = new URLSearchParams(location.search);
    let sel = (params.get('ids') || '1').split(',').filter((id) => desc[id]).slice(0, MAX);
    const base100 = document.getElementById('base100');
    base100.checked = params.get('base100') === '1';
    let chart = null;

    function sync() {
        lista.querySelectorAll('.var-item').forEach((b) => b.setAttribute('aria-pressed', String(sel.includes(b.dataset.id))));
        const chips = document.getElementById('chips');
        chips.replaceChildren(...sel.map((id, i) => DatAR.el('span', { class: 'chip' },
            DatAR.el('i', { style: `background:${DatAR.color(i + 1)}` }),
            DatAR.el('span', { title: desc[id] }, desc[id]),
            DatAR.el('button', { type: 'button', 'aria-label': 'Quitar', onclick: () => { sel = sel.filter((x) => x !== id); render(); } }, '×'))));
        const q = new URLSearchParams({ ids: sel.join(',') });
        if (base100.checked) q.set('base100', '1');
        history.replaceState(null, '', '?' + q);
    }

    function render() {
        sync();
        const target = document.getElementById('c-explorer');
        if (!sel.length) {
            chart = null;
            target.className = '';
            target.replaceChildren(DatAR.el('p', { class: 'muted' }, 'Elegí una serie de la lista.'));
            return;
        }
        const ids = [...sel];
        chart = DatAR.chart(target, {
            title: ids.length === 1 ? desc[ids[0]] : 'Comparación de series',
            sub: base100.checked ? 'Base 100 al inicio del período' : '',
            range: '5A', ranges: ['1M', '6M', '1A', '5A', '10A', 'Todo'], tall: true,
            load: async (desde) => (await DatAR.series(ids, desde)).map((s) => {
                const base = s.datos[0]?.valor || 1;
                return {
                    label: base100.checked ? s.descripcion : `${s.descripcion} (${s.unidad})`,
                    data: s.datos.map((p) => ({ x: DatAR.toTs(p.fecha), y: base100.checked ? p.valor / base * 100 : p.valor })),
                };
            }),
        });
    }

    lista.addEventListener('click', (e) => {
        const b = e.target.closest('.var-item');
        if (!b) return;
        const id = b.dataset.id;
        if (sel.includes(id)) sel = sel.filter((x) => x !== id);
        else if (sel.length < MAX) sel.push(id);
        else { alert(`Podés comparar hasta ${MAX} series a la vez.`); return; }
        render();
    });
    base100.addEventListener('change', render);

    const buscar = document.getElementById('buscar');
    const info = document.getElementById('buscar-info');
    buscar.addEventListener('input', () => {
        const palabras = buscar.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').split(/\s+/).filter(Boolean);
        let n = 0;
        lista.querySelectorAll('details').forEach((d) => {
            let visibles = 0;
            d.querySelectorAll('.var-item').forEach((b) => {
                const t = b.dataset.q.normalize('NFD').replace(/[̀-ͯ]/g, '');
                const ok = palabras.every((w) => t.includes(w));
                b.hidden = !ok;
                visibles += ok;
            });
            d.hidden = !visibles;
            if (palabras.length) d.open = visibles > 0 && visibles <= 40;
            n += visibles;
        });
        info.textContent = palabras.length ? `${n} series encontradas` : '';
    });

    document.getElementById('compartir').addEventListener('click', async (e) => {
        try { await navigator.clipboard.writeText(location.href); e.target.textContent = '¡Enlace copiado!'; }
        catch { e.target.textContent = location.href; }
        setTimeout(() => { e.target.textContent = 'Copiar enlace a esta vista'; }, 2500);
    });

    render();
}
JS;
require __DIR__ . '/partials/footer.php';
