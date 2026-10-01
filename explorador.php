<?php
$titulo = 'Explorador de series';
$fuente = 'ambas';
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
<p class="lead">Las <?= count($vars ?? []) ?> series estadísticas del BCRA y unas 3.500 series vigentes del INDEC. Elegí hasta 4 (podés mezclar fuentes) para graficarlas juntas, compararlas en base 100 o descargarlas en CSV.</p>

<?php if ($error): ?>
    <p class="alert"><?= h($error) ?></p>
<?php else: ?>
<div class="explorer">
    <aside class="card">
        <div class="seg seg-fuentes" role="tablist" aria-label="Fuente" style="width:100%;margin-bottom:.75rem">
            <button type="button" role="tab" data-fuente="bcra" aria-pressed="true"><span class="fuente">BCRA</span><?= num(count($vars), 0) ?></button>
            <button type="button" role="tab" data-fuente="indec" aria-pressed="false"><span class="fuente fuente-indec">INDEC</span>~3.500</button>
        </div>
        <input id="buscar" type="search" placeholder="Buscar: reservas, plazo fijo, M2…" style="width:100%" aria-label="Buscar serie">
        <p class="card-sub" id="buscar-info" style="margin-top:.5rem"></p>
        <div class="var-list" id="lista">
            <?php foreach ($categorias as $cat => $lista): ?>
                <details <?= $cat === 'Principales Variables' ? 'open' : '' ?>>
                    <summary><?= h($cat) ?> <span class="muted">(<?= count($lista) ?>)</span></summary>
                    <?php foreach ($lista as $v): ?>
                        <button type="button" class="var-item" data-key="b:<?= $v['idVariable'] ?>" aria-pressed="false"
                                data-q="<?= h(mb_strtolower($v['descripcion'] . ' ' . $v['categoria'] . ' ' . ($monedaTxt[$v['moneda']] ?? '') . ' ' . $v['unidadExpresion'] . ' ' . $v['idVariable'])) ?>">
                            <?= h($v['descripcion']) ?>
                            <span class="muted">#<?= $v['idVariable'] ?> · <?= h($monedaTxt[$v['moneda']] ?? $v['moneda']) ?> · <?= h($v['unidadExpresion']) ?> · <?= $periodicidad[$v['periodicidad']] ?? $v['periodicidad'] ?> · <?= fecha_ar($v['primerFechaInformada']) ?>–<?= fecha_ar($v['ultFechaInformada']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>
        </div>
        <div class="var-list var-list-indec" id="lista-indec" hidden><p class="muted">Cargando catálogo del INDEC…</p></div>
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
        <p class="card-sub" style="margin-top:.75rem">Las series del INDEC se consultan en la API de Series de Tiempo de datos.gob.ar. Las que vienen como fracción (0,079) se muestran en porcentaje (7,9%).</p>
    </div>
</div>
<?php endif; ?>

<?php
$script = <<<'JS'
const MAX = 4;
const FREC = { D: 'diaria', M: 'mensual', T: 'trimestral', S: 'semestral', A: 'anual' };
const lista = document.getElementById('lista');
const listaIndec = document.getElementById('lista-indec');
const norm = (t) => t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

if (lista) {
    // Descripción de cada serie seleccionable, por clave ('b:ID' para BCRA, 'i:ID' para INDEC)
    const desc = {};
    lista.querySelectorAll('.var-item').forEach((b) => { desc[b.dataset.key] = b.firstChild.textContent.trim(); });

    // Catálogo del INDEC: se carga recién cuando hace falta
    let catalogo = null; // Map id -> {desc, unidad, frec, ini, fin, ds}
    let datasets = [];
    async function cargarCatalogo() {
        if (catalogo) return catalogo;
        const c = await DatAR.indecCatalogo();
        datasets = c.datasets;
        catalogo = new Map(c.series.map(([id, d, unidad, frec, ini, fin, ds]) => [id, { id, desc: d, unidad, frec, ini, fin, ds, q: norm(`${d} ${unidad} ${c.datasets[ds]} ${id}`) }]));
        catalogo.forEach((m) => { desc['i:' + m.id] = m.desc; });
        return catalogo;
    }

    const params = new URLSearchParams(location.search);
    let sel = [
        ...(params.get('ids') ?? (params.get('indec') ? '' : '1')).split(',').filter(Boolean).map((id) => 'b:' + id),
        ...(params.get('indec') || '').split(',').filter(Boolean).map((id) => 'i:' + id),
    ].slice(0, MAX);
    const base100 = document.getElementById('base100');
    base100.checked = params.get('base100') === '1';
    let fuente = sel.length && sel.every((k) => k.startsWith('i:')) ? 'indec' : 'bcra';

    function marcarSeleccion() {
        document.querySelectorAll('.var-item').forEach((b) => b.setAttribute('aria-pressed', String(sel.includes(b.dataset.key))));
    }

    function sync() {
        marcarSeleccion();
        const chips = document.getElementById('chips');
        chips.replaceChildren(...sel.map((k, i) => DatAR.el('span', { class: 'chip' },
            DatAR.el('i', { style: `background:${DatAR.color(i + 1)}` }),
            DatAR.el('span', { class: 'fuente' + (k.startsWith('i:') ? ' fuente-indec' : '') }, k.startsWith('i:') ? 'INDEC' : 'BCRA'),
            DatAR.el('span', { title: desc[k] }, desc[k] || k.slice(2)),
            DatAR.el('button', { type: 'button', 'aria-label': 'Quitar', onclick: () => { sel = sel.filter((x) => x !== k); render(); } }, '×'))));
        const q = new URLSearchParams();
        const b = sel.filter((k) => k.startsWith('b:')).map((k) => k.slice(2));
        const ind = sel.filter((k) => k.startsWith('i:')).map((k) => k.slice(2));
        if (b.length) q.set('ids', b.join(','));
        if (ind.length) q.set('indec', ind.join(','));
        if (base100.checked) q.set('base100', '1');
        history.replaceState(null, '', '?' + q);
    }

    function render() {
        sync();
        const target = document.getElementById('c-explorer');
        if (!sel.length) {
            target.className = '';
            target.replaceChildren(DatAR.el('p', { class: 'muted' }, 'Elegí una serie de la lista.'));
            return;
        }
        const keys = [...sel];
        const soloIndec = keys.every((k) => k.startsWith('i:'));
        DatAR.chart(target, {
            title: keys.length === 1 ? desc[keys[0]] : 'Comparación de series',
            sub: base100.checked ? 'Base 100 al inicio del período' : '',
            fuente: soloIndec ? 'indec' : 'bcra',
            range: soloIndec ? '10A' : '5A', ranges: ['1M', '6M', '1A', '5A', '10A', 'Todo'], tall: true,
            load: async (desde) => {
                const res = new Map();
                const bIds = keys.filter((k) => k.startsWith('b:')).map((k) => k.slice(2));
                if (bIds.length) {
                    (await DatAR.series(bIds, desde)).forEach((s) => res.set('b:' + s.id, { label: s.descripcion, unidad: s.unidad, datos: s.datos }));
                }
                const iKeys = keys.filter((k) => k.startsWith('i:'));
                if (iKeys.length) {
                    const cat = await cargarCatalogo();
                    await Promise.all(iKeys.map(async (k) => {
                        const m = cat.get(k.slice(2)) || { desc: k.slice(2), unidad: '', frec: 'M' };
                        const r = await DatAR.indecDirecta(k.slice(2), m.unidad);
                        res.set(k, { label: m.desc, unidad: r.aPorcentaje ? '%' : m.unidad, periodo: m.frec === 'D' ? undefined : m.frec,
                            datos: r.datos.filter((p) => !desde || p.fecha >= desde) });
                    }));
                }
                return keys.map((k) => res.get(k)).filter(Boolean).map((s) => {
                    const base = s.datos[0]?.valor || 1;
                    return {
                        label: base100.checked || !s.unidad ? s.label : `${s.label} (${s.unidad})`,
                        periodo: s.periodo,
                        data: s.datos.map((p) => ({ x: DatAR.toTs(p.fecha), y: base100.checked ? p.valor / base * 100 : p.valor })),
                    };
                });
            },
        });
    }

    function alternar(key) {
        if (sel.includes(key)) sel = sel.filter((x) => x !== key);
        else if (sel.length < MAX) sel.push(key);
        else { alert(`Podés comparar hasta ${MAX} series a la vez.`); return; }
        render();
    }
    document.querySelector('.explorer aside').addEventListener('click', (e) => {
        const b = e.target.closest('.var-item');
        if (b) alternar(b.dataset.key);
    });
    base100.addEventListener('change', render);

    /* ----- Lista del INDEC ----- */
    function itemIndec(m) {
        return DatAR.el('button', { type: 'button', class: 'var-item', 'data-key': 'i:' + m.id, 'aria-pressed': String(sel.includes('i:' + m.id)) },
            m.desc,
            DatAR.el('span', { class: 'muted' }, `${m.unidad || 'sin unidad'} · ${FREC[m.frec] || m.frec} · ${m.ini}–${m.fin}`));
    }
    function listarIndec() {
        const grupos = datasets.map(() => []);
        catalogo.forEach((m) => grupos[m.ds].push(m));
        listaIndec.replaceChildren(...datasets.map((t, i) => {
            const d = DatAR.el('details', {}, DatAR.el('summary', {}, t + ' ', DatAR.el('span', { class: 'muted' }, `(${grupos[i].length})`)));
            // Los ítems se crean al abrir el grupo, para no dibujar 3.500 botones de entrada
            d.addEventListener('toggle', () => { if (d.open && d.children.length === 1) d.append(...grupos[i].map(itemIndec)); });
            return d;
        }));
    }
    function buscarIndec(palabras) {
        const encontrados = [...catalogo.values()].filter((m) => palabras.every((w) => m.q.includes(w)));
        const visibles = encontrados.slice(0, 150);
        listaIndec.replaceChildren(...visibles.map((m) => {
            const b = itemIndec(m);
            b.querySelector('.muted').append(' · ' + datasets[m.ds]);
            return b;
        }));
        info.textContent = `${encontrados.length} series encontradas` + (encontrados.length > visibles.length ? ` (se muestran ${visibles.length}; afiná la búsqueda)` : '');
    }

    /* ----- Pestañas de fuente y búsqueda ----- */
    const buscar = document.getElementById('buscar');
    const info = document.getElementById('buscar-info');
    const tabs = document.querySelectorAll('.seg-fuentes button');
    async function mostrarFuente(f) {
        fuente = f;
        tabs.forEach((t) => t.setAttribute('aria-pressed', String(t.dataset.fuente === f)));
        lista.hidden = f !== 'bcra';
        listaIndec.hidden = f !== 'indec';
        buscar.placeholder = f === 'bcra' ? 'Buscar: reservas, plazo fijo, M2…' : 'Buscar: desocupación, IPC alimentos, exportaciones China…';
        if (f === 'indec') {
            try {
                await cargarCatalogo();
                if (listaIndec.querySelector('p.muted')) listarIndec();
            } catch (e) {
                listaIndec.replaceChildren(DatAR.el('p', { class: 'alert' }, 'No se pudo cargar el catálogo del INDEC. ' + e.message));
                return;
            }
        }
        filtrar();
    }
    tabs.forEach((t) => t.addEventListener('click', () => mostrarFuente(t.dataset.fuente)));

    function filtrar() {
        const palabras = norm(buscar.value).split(/\s+/).filter(Boolean);
        if (fuente === 'indec') {
            if (!catalogo) return;
            if (palabras.length) buscarIndec(palabras);
            else { listarIndec(); info.textContent = ''; }
            return;
        }
        let n = 0;
        lista.querySelectorAll('details').forEach((d) => {
            let visibles = 0;
            d.querySelectorAll('.var-item').forEach((b) => {
                const ok = palabras.every((w) => norm(b.dataset.q).includes(w));
                b.hidden = !ok;
                visibles += ok;
            });
            d.hidden = !visibles;
            if (palabras.length) d.open = visibles > 0 && visibles <= 40;
            n += visibles;
        });
        info.textContent = palabras.length ? `${n} series encontradas` : '';
    }
    buscar.addEventListener('input', filtrar);

    document.getElementById('compartir').addEventListener('click', async (e) => {
        try { await navigator.clipboard.writeText(location.href); e.target.textContent = '¡Enlace copiado!'; }
        catch { e.target.textContent = location.href; }
        setTimeout(() => { e.target.textContent = 'Copiar enlace a esta vista'; }, 2500);
    });

    (async () => {
        if (sel.some((k) => k.startsWith('i:'))) await cargarCatalogo().catch(() => {});
        if (fuente === 'indec') mostrarFuente('indec');
        render();
    })();
}
JS;
require __DIR__ . '/partials/footer.php';
