/* DatAR — utilidades compartidas para consultar los datos (api.php o JSON estáticos) y dibujar gráficos con Chart.js */
const DatAR = (() => {
    const DAY = 86400000;
    const RANGOS = { '1M': 31, '3M': 92, '6M': 183, '1A': 366, '2A': 731, '5A': 1827, '10A': 3653, 'Todo': null };
    const charts = [];
    const memo = new Map();

    /* ---------- Formato ---------- */
    const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    const color = (slot) => css(typeof slot === 'number' ? `--s${slot}` : slot);
    const nf = (dec) => new Intl.NumberFormat('es-AR', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    const fmt = (v, dec = 2) => (v == null || isNaN(v) ? '—' : nf(dec).format(v));
    const compact = (v) => new Intl.NumberFormat('es-AR', { notation: 'compact', maximumFractionDigits: 1 }).format(v);
    const toTs = (iso) => Date.parse(iso + 'T12:00:00');
    const isoDate = (d) => d.toISOString().slice(0, 10);
    const fechaLarga = (ts) => new Date(ts).toLocaleDateString('es-AR', { day: 'numeric', month: 'short', year: 'numeric' });
    const fechaCorta = (ts, span) => new Date(ts).toLocaleDateString('es-AR',
        span < 120 * DAY ? { day: 'numeric', month: 'short' } : span < 3 * 365 * DAY ? { month: 'short', year: '2-digit' } : { year: 'numeric' });
    const desdeRango = (r) => (RANGOS[r] ? isoDate(new Date(Date.now() - RANGOS[r] * DAY)) : '');

    /* ---------- Datos ---------- */
    async function getJSON(url) {
        if (!memo.has(url)) {
            memo.set(url, fetch(url).then(async (r) => {
                const j = await r.json();
                if (!r.ok) throw new Error(j.error || `HTTP ${r.status}`);
                return j;
            }).catch((e) => { memo.delete(url); throw e; }));
        }
        return memo.get(url);
    }

    /* En GitHub Pages no hay PHP: se leen los JSON que genera build.php (data/) y,
       si una serie no está pregenerada, se consulta directo a la API del BCRA (permite CORS). */
    const STATIC = !!window.DATAR_STATIC;
    const BCRA = 'https://api.bcra.gob.ar';
    const enRango = (f, desde, hasta) => (!desde || f >= desde) && (!hasta || f <= hasta);

    /** Series de la API monetaria. Devuelve [{id, descripcion, unidad, datos:[{fecha, valor}]}] */
    async function series(ids, desde = '', hasta = '') {
        if (STATIC) return Promise.all([].concat(ids).map((id) => serieEstatica(id, desde, hasta)));
        const q = new URLSearchParams({ action: 'serie', ids: [].concat(ids).join(',') });
        if (desde) q.set('desde', desde);
        if (hasta) q.set('hasta', hasta);
        return (await getJSON('api.php?' + q)).series;
    }

    async function serieEstatica(id, desde, hasta) {
        let s;
        try {
            s = await getJSON(`data/serie/${id}.json`);
        } catch (e) {
            s = await serieDirecta(id, desde, hasta);
        }
        return {
            id: s.id, descripcion: s.descripcion, unidad: s.unidad,
            datos: s.datos.filter(([f]) => enRango(f, desde, hasta)).map(([fecha, valor]) => ({ fecha, valor })),
        };
    }

    /** Serie pedida al BCRA desde el navegador (solo el rango pedido), con el mismo formato que los JSON pregenerados. */
    async function serieDirecta(id, desde = '', hasta = '') {
        const key = `directa:${id}:${desde}:${hasta}`;
        if (!memo.has(key)) {
            memo.set(key, (async () => {
                const { variables } = await getJSON('data/variables.json');
                const v = variables.find((x) => x.idVariable == id) || {};
                const datos = [];
                for (let offset = 0, total = 1; offset < total; offset += 3000) {
                    const q = new URLSearchParams({ limit: 3000, offset });
                    if (desde) q.set('desde', desde);
                    if (hasta) q.set('hasta', hasta);
                    const j = await getJSON(`${BCRA}/estadisticas/v4.0/monetarias/${id}?${q}`);
                    total = j.metadata?.resultset?.count ?? 0;
                    const det = j.results?.[0]?.detalle ?? [];
                    if (!det.length) break;
                    det.forEach((p) => datos.push([p.fecha, p.valor]));
                }
                datos.sort((a, b) => a[0].localeCompare(b[0]));
                return { id: Number(id), descripcion: v.descripcion || `Serie ${id}`, unidad: v.unidadExpresion || '', datos };
            })().catch((e) => { memo.delete(key); throw e; }));
        }
        return memo.get(key);
    }

    async function cotizacion(moneda, desde = '') {
        if (STATIC) {
            try {
                const { datos } = await getJSON(`data/cotizacion/${moneda}.json`);
                if (!desde || datos[0]?.[0] <= desde) {
                    return datos.filter(([f]) => enRango(f, desde)).map(([fecha, valor]) => ({ fecha, valor }));
                }
            } catch (e) { /* no está pregenerada: se consulta al BCRA */ }
            const hasta = isoDate(new Date());
            const d = desde || isoDate(new Date(Date.now() - 366 * DAY));
            const out = [];
            for (let offset = 0, total = 1; offset < total; offset += 1000) {
                const j = await getJSON(`${BCRA}/estadisticascambiarias/v1.0/Cotizaciones/${moneda}?fechadesde=${d}&fechahasta=${hasta}&limit=1000&offset=${offset}`);
                total = j.metadata?.resultset?.count ?? 0;
                if (!j.results?.length) break;
                for (const dia of j.results) {
                    const c = dia.detalle?.[0];
                    if (c && c.tipoCotizacion > 0) out.push({ fecha: dia.fecha, valor: c.tipoCotizacion });
                }
            }
            return out.sort((a, b) => a.fecha.localeCompare(b.fecha));
        }
        const q = new URLSearchParams({ action: 'cotizacion', moneda });
        if (desde) q.set('desde', desde);
        return (await getJSON('api.php?' + q)).datos;
    }

    const toXY = (datos) => datos.map((p) => ({ x: toTs(p.fecha), y: p.valor }));

    /* ---------- INDEC (API de Series de Tiempo de datos.gob.ar) ---------- */
    const DATOSGOB = 'https://apis.datos.gob.ar/series/api';

    /** Series completas del INDEC. Devuelve [{id, descripcion, unidad, datos:[{fecha, valor}]}] */
    async function indec(ids) {
        ids = [].concat(ids);
        if (!STATIC) return (await getJSON('api.php?' + new URLSearchParams({ action: 'indec', ids: ids.join(',') }))).series;
        return Promise.all(ids.map(async (id) => {
            let s;
            try {
                s = await getJSON(`data/indec/${id}.json`);
            } catch (e) { // no está pregenerada: se pide directo a datos.gob.ar
                const cat = await getJSON('data/indec/_catalogo.json');
                const [nombre, unidad, escala = 1] = cat[id] || [id, ''];
                const j = await getJSON(`${DATOSGOB}/series/?ids=${encodeURIComponent(id)}&limit=5000&format=json&metadata=none`);
                s = { id, descripcion: nombre, unidad, datos: j.data.filter((p) => p[1] != null).map(([f, v]) => [f, v * escala]) };
            }
            return { id: s.id, descripcion: s.descripcion, unidad: s.unidad, datos: s.datos.map(([fecha, valor]) => ({ fecha, valor })) };
        }));
    }

    const esTrimestral = (datos) => datos.length > 1 && toTs(datos.at(-1).fecha) - toTs(datos.at(-2).fecha) > 45 * DAY;

    /** 'var_m': variación contra el período anterior; 'var_ia': contra el mismo período del año anterior. */
    function transformar(datos, modo) {
        const lag = modo === 'var_m' ? 1 : modo === 'var_ia' ? (esTrimestral(datos) ? 4 : 12) : 0;
        if (!lag) return datos;
        return datos.slice(lag).map((p, i) => ({ fecha: p.fecha, valor: (p.valor / datos[i].valor - 1) * 100 }));
    }

    /** Loader para gráficos: items = [{id, label, modo, kind, signed, dash, color, fill}] */
    function fromIndec(items) {
        return async (desde) => {
            const ss = await indec(items.map((it) => it.id));
            return ss.map((s, i) => {
                const { id, modo, ...opts } = items[i];
                const datos = transformar(s.datos, modo).filter((p) => !desde || p.fecha >= desde);
                return { label: s.descripcion, periodo: esTrimestral(s.datos) ? 'T' : 'M', ...opts, data: toXY(datos) };
            });
        };
    }

    /** '2.º trim. 2026', 'ago 2026' o '30 sept 2026' según la periodicidad. */
    function periodoTxt(ts, periodo) {
        const d = new Date(ts);
        if (periodo === 'T') return `${Math.floor(d.getMonth() / 3) + 1}.º trim. ${d.getFullYear()}`;
        if (periodo === 'M') return d.toLocaleDateString('es-AR', { month: 'short', year: 'numeric' });
        return fechaLarga(ts);
    }
    const fuentePagina = () => (document.documentElement.classList.contains('indec') ? 'indec' : 'bcra');

    /** Loader simple: una o más variables tal cual vienen de la API (divididas por `div`, p. ej. 1e6 para pasar millones a billones). */
    function fromIds(ids, labels = [], opts = [], div = 1) {
        return async (desde) => (await series(ids, desde)).map((s, i) => ({
            label: labels[i] || s.descripcion,
            data: s.datos.map((p) => ({ x: toTs(p.fecha), y: p.valor / div })),
            ...(opts[i] || {}),
        }));
    }

    /** Une dos series por fecha y aplica fn(a, b). */
    function combinar(a, b, fn) {
        const mapB = new Map(b.map((p) => [p.fecha, p.valor]));
        return a.filter((p) => mapB.has(p.fecha)).map((p) => ({ fecha: p.fecha, valor: fn(p.valor, mapB.get(p.fecha)) }));
    }

    /** Promedio mensual: {'2026-08': valor} */
    function promedioMensual(datos) {
        const acc = {};
        for (const p of datos) {
            const k = p.fecha.slice(0, 7);
            (acc[k] ||= []).push(p.valor);
        }
        return Object.fromEntries(Object.entries(acc).map(([k, v]) => [k, v.reduce((s, x) => s + x, 0) / v.length]));
    }

    /* ---------- Gráficos ---------- */
    function el(tag, attrs = {}, ...children) {
        const e = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs)) {
            if (k === 'class') e.className = v; else if (k.startsWith('on')) e.addEventListener(k.slice(2), v); else e.setAttribute(k, v);
        }
        e.append(...children.filter((c) => c != null));
        return e;
    }

    /**
     * Dibuja una tarjeta con gráfico en `target` (selector o elemento).
     * cfg: { title, sub, load(desde) => [{label, data:[{x,y}], kind:'line'|'bar', color, dash, signed}],
     *        range, ranges, decimals, unit, tall, beginAtZero, note }
     */
    function chart(target, cfg) {
        const root = typeof target === 'string' ? document.querySelector(target) : target;
        // Si ya había un gráfico en este contenedor, liberarlo antes de reemplazarlo.
        root.querySelectorAll('canvas').forEach((c) => Chart.getChart(c)?.destroy());
        if (root._datar) charts.splice(charts.indexOf(root._datar), 1);
        const ranges = cfg.ranges || ['1M', '6M', '1A', '5A', 'Todo'];
        let range = cfg.range || '1A';
        let current = [];
        let instance = null;

        const seg = el('div', { class: 'seg', role: 'group', 'aria-label': 'Rango de fechas' });
        const legend = el('div', { class: 'legend' });
        const canvas = el('canvas', { role: 'img', 'aria-label': cfg.title });
        const status = el('div', { class: 'chart-status' }, 'Cargando…');
        const box = el('div', { class: 'chart-box' + (cfg.tall ? ' tall' : '') }, canvas, status);
        const tableBox = el('div', { class: 'table-wrap', hidden: '' });
        const updated = el('span');
        const btnTabla = el('button', { class: 'btn-link', type: 'button', onclick: () => {
            tableBox.hidden = !tableBox.hidden;
            btnTabla.textContent = tableBox.hidden ? 'Ver tabla' : 'Ocultar tabla';
            if (!tableBox.hidden) renderTabla();
        } }, 'Ver tabla');
        const btnCsv = el('button', { class: 'btn-link', type: 'button', onclick: () => descargarCSV(cfg.title, current) }, 'Descargar CSV');

        for (const r of ranges) {
            seg.append(el('button', { type: 'button', 'aria-pressed': String(r === range), onclick: (ev) => {
                range = r;
                seg.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b === ev.currentTarget)));
                cargar();
            } }, r));
        }

        const fuente = cfg.fuente || fuentePagina();
        root.classList.add('card');
        root.classList.toggle('card-indec', fuente === 'indec');
        root.replaceChildren(
            el('div', { class: 'card-head' },
                el('div', {}, el('h3', { class: 'card-title' }, fuente === 'indec' ? el('span', { class: 'badge-indec' }, 'INDEC') : null, cfg.title),
                    cfg.sub ? el('p', { class: 'card-sub' }, cfg.sub) : null),
                ranges.length > 1 ? seg : null),
            legend, box,
            el('div', { class: 'card-foot' }, updated, el('span', {}, btnTabla, ' · ', btnCsv)),
            tableBox,
            cfg.note ? el('p', { class: 'card-sub', style: 'margin-top:.5rem' }, cfg.note) : '',
        );

        function renderTabla() {
            const filas = unirPorFecha(current).reverse();
            const dec = cfg.decimals ?? 2;
            tableBox.replaceChildren(el('table', {},
                el('thead', {}, el('tr', {}, el('th', {}, 'Fecha'), ...current.map((s) => el('th', { class: 'num' }, s.label)))),
                el('tbody', {}, ...filas.map((f) => el('tr', {},
                    el('td', {}, current[0].periodo ? periodoTxt(f.x, current[0].periodo) : new Date(f.x).toLocaleDateString('es-AR')),
                    ...f.vals.map((v) => el('td', { class: 'num' }, fmt(v, dec))))))));
        }

        function dibujar() {
            instance?.destroy();
            const surface = css('--surface');
            const decimals = cfg.decimals ?? 2;
            const span = current.length ? Math.max(...current.map((s) => (s.data.at(-1)?.x ?? 0) - (s.data[0]?.x ?? 0))) : 0;

            const datasets = current.map((s, i) => {
                const c = color(s.color ?? i + 1);
                if (s.kind === 'bar') {
                    const neg = color('--neg');
                    return {
                        type: 'bar', label: s.label, data: s.data,
                        backgroundColor: s.signed ? s.data.map((p) => (p.y < 0 ? neg : c)) : c,
                        borderRadius: 4, borderSkipped: 'start', maxBarThickness: 24,
                        categoryPercentage: 0.9, barPercentage: 0.9, order: 2,
                    };
                }
                return {
                    type: 'line', label: s.label, data: s.data, borderColor: c, backgroundColor: c + '1a',
                    borderWidth: 2, borderDash: s.dash ? [5, 4] : [], pointRadius: 0, pointHoverRadius: 5,
                    pointHoverBorderWidth: 2, pointHoverBorderColor: surface, pointHoverBackgroundColor: c,
                    fill: s.fill ? 'origin' : false, tension: 0, stepped: s.stepped || false, order: 1,
                };
            });

            instance = new Chart(canvas, {
                data: { datasets },
                options: {
                    responsive: true, maintainAspectRatio: false, animation: false,
                    parsing: false, normalized: true,
                    interaction: { mode: 'index', intersect: false, axis: 'x' },
                    plugins: {
                        legend: { display: false },
                        decimation: { enabled: true, algorithm: 'lttb', samples: 600 },
                        tooltip: {
                            backgroundColor: css('--surface'), titleColor: css('--ink'), bodyColor: css('--ink-2'),
                            borderColor: css('--axis'), borderWidth: 1, padding: 10, boxPadding: 4, usePointStyle: true,
                            callbacks: {
                                title: (items) => items.length ? periodoTxt(items[0].parsed.x, current[0].periodo) : '',
                                label: (it) => ` ${it.dataset.label}: ${fmt(it.parsed.y, decimals)}${cfg.unit ? ' ' + cfg.unit : ''}`,
                                labelColor: (it) => ({ borderColor: 'transparent', backgroundColor: Array.isArray(it.dataset.backgroundColor) ? it.dataset.backgroundColor[it.dataIndex] : (it.dataset.type === 'bar' ? it.dataset.backgroundColor : it.dataset.borderColor) }),
                            },
                        },
                    },
                    scales: {
                        x: {
                            type: 'time', offset: datasets.some((d) => d.type === 'bar'),
                            time: { unit: span > 3 * 365 * DAY ? 'year' : span > 120 * DAY ? 'month' : 'day' },
                            grid: { display: false }, border: { color: css('--axis') },
                            ticks: { color: css('--muted'), maxRotation: 0, autoSkipPadding: 18, callback: (v) => fechaCorta(v, span) },
                        },
                        y: {
                            beginAtZero: !!cfg.beginAtZero,
                            grid: { color: css('--grid') }, border: { display: false },
                            ticks: { color: css('--muted'), callback: (v) => (Math.abs(v) >= 10000 ? compact(v) : fmt(v, Math.abs(v) < 10 && v % 1 ? Math.min(Math.max(cfg.decimals ?? 1, 1), 2) : 0)) + (cfg.unit === '%' ? '%' : '') },
                        },
                    },
                },
            });

            // Leyenda HTML (solo con 2+ series; con una sola el título ya la nombra)
            legend.replaceChildren(...(current.length > 1 || current[0]?.signed ? current.flatMap((s, i) => {
                const c = color(s.color ?? i + 1);
                if (s.signed) {
                    return [el('span', {}, el('i', { class: 'bar', style: `background:${c}` }), 'Positiva'),
                        el('span', {}, el('i', { class: 'bar', style: `background:${color('--neg')}` }), 'Negativa')];
                }
                return [el('span', {}, el('i', { class: (s.kind === 'bar' ? 'bar' : '') + (s.dash ? ' dash' : ''), style: `background:${c};color:${c}` }), s.label)];
            }) : []));
        }

        async function cargar() {
            status.hidden = false;
            status.textContent = 'Cargando…';
            try {
                current = (await cfg.load(desdeRango(range), range)).filter((s) => s.data.length);
                if (!current.length) throw new Error('No hay datos para este rango.');
                status.hidden = true;
                const ult = current[0].data.at(-1).x; // la primera serie es la principal (otras pueden ser proyecciones)
                updated.textContent = 'Último dato: ' + periodoTxt(ult, current[0].periodo) + (fuente === 'indec' ? ' · Fuente: INDEC' : '');
                dibujar();
                if (!tableBox.hidden) renderTabla();
            } catch (e) {
                instance?.destroy();
                instance = null;
                status.textContent = 'No se pudieron cargar los datos. ' + e.message;
            }
        }

        const api = { redraw: () => current.length && dibujar(), reload: cargar, root };
        charts.push(api);
        root._datar = api;
        cargar();
        return api;
    }

    /**
     * Ranking en barras horizontales (p. ej. qué sectores crecen y cuáles caen).
     * cfg: { title, sub, modos: [{key, label}], modo, load(modo) => {periodo, items:[{label, valor}]}, unit, decimals, note }
     */
    function ranking(target, cfg) {
        const root = typeof target === 'string' ? document.querySelector(target) : target;
        const fuente = cfg.fuente || fuentePagina();
        let modo = cfg.modo || cfg.modos?.[0]?.key;
        let items = [];
        const canvas = el('canvas', { role: 'img', 'aria-label': cfg.title });
        const status = el('div', { class: 'chart-status' }, 'Cargando…');
        const box = el('div', { class: 'chart-box' }, canvas, status);
        const updated = el('span');
        const seg = el('div', { class: 'seg', role: 'group', 'aria-label': 'Medida' });
        (cfg.modos || []).forEach((m) => seg.append(el('button', { type: 'button', 'aria-pressed': String(m.key === modo), onclick: (ev) => {
            modo = m.key;
            seg.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b === ev.currentTarget)));
            cargar();
        } }, m.label)));
        root.classList.add('card');
        root.classList.toggle('card-indec', fuente === 'indec');
        root.replaceChildren(
            el('div', { class: 'card-head' },
                el('div', {}, el('h3', { class: 'card-title' }, fuente === 'indec' ? el('span', { class: 'badge-indec' }, 'INDEC') : null, cfg.title),
                    cfg.sub ? el('p', { class: 'card-sub' }, cfg.sub) : null),
                cfg.modos?.length > 1 ? seg : null),
            el('div', { class: 'legend' },
                el('span', {}, el('i', { class: 'bar', style: `background:${color(1)}` }), 'Sube'),
                el('span', {}, el('i', { class: 'bar', style: `background:${color('--neg')}` }), 'Baja')),
            box,
            el('div', { class: 'card-foot' }, updated, el('button', { class: 'btn-link', type: 'button', onclick: () =>
                descargarCSV(cfg.title, [{ label: cfg.title, data: [] }], items) }, 'Descargar CSV')),
            cfg.note ? el('p', { class: 'card-sub', style: 'margin-top:.5rem' }, cfg.note) : '',
        );
        box.style.height = Math.max(260, 26 * 16 + 40) + 'px';

        function dibujar() {
            Chart.getChart(canvas)?.destroy();
            const dec = cfg.decimals ?? 1;
            const pos = color(1), neg = color('--neg');
            box.style.height = Math.max(200, items.length * 26 + 40) + 'px';
            // Si un valor extremo aplasta al resto (p. ej. Pesca +400%), se recorta el eje y el valor va en la etiqueta.
            const abs = items.map((i) => Math.abs(i.valor)).sort((a, b) => b - a);
            const tope = abs.length > 2 && abs[0] > 3 * abs[1] ? abs[1] * 1.4 : null;
            const etiqueta = (i) => (tope && Math.abs(i.valor) > tope ? `${i.label} (${i.valor > 0 ? '+' : ''}${fmt(i.valor, dec)}${cfg.unit === '%' ? '%' : ''}) ›` : i.label);
            new Chart(canvas, {
                type: 'bar',
                data: { labels: items.map(etiqueta), datasets: [{ data: items.map((i) => i.valor), backgroundColor: items.map((i) => (i.valor < 0 ? neg : pos)),
                    borderRadius: 4, borderSkipped: 'start', maxBarThickness: 18, categoryPercentage: 0.8, barPercentage: 0.9 }] },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false, animation: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: css('--surface'), titleColor: css('--ink'), bodyColor: css('--ink-2'), borderColor: css('--axis'), borderWidth: 1, padding: 10,
                            callbacks: { label: (it) => ` ${fmt(it.parsed.x, dec)}${cfg.unit === '%' ? '%' : ''}` },
                        },
                    },
                    scales: {
                        x: { min: tope ? Math.max(-tope, Math.min(0, ...items.map((i) => i.valor))) : undefined,
                            max: tope ? Math.min(tope, Math.max(0, ...items.map((i) => i.valor))) : undefined, grid: { color: css('--grid') }, border: { display: false }, ticks: { color: css('--muted'), callback: (v) => fmt(v, Number.isInteger(v) ? 0 : 1) + (cfg.unit === '%' ? '%' : '') } },
                        y: { grid: { display: false }, border: { color: css('--axis') }, ticks: { color: css('--ink-2'), autoSkip: false } },
                    },
                },
            });
        }

        async function cargar() {
            status.hidden = false;
            status.textContent = 'Cargando…';
            try {
                const r = await cfg.load(modo);
                items = r.items.filter((i) => isFinite(i.valor)).sort((a, b) => b.valor - a.valor);
                updated.textContent = r.periodo + (fuente === 'indec' ? ' · Fuente: INDEC' : '');
                status.hidden = true;
                dibujar();
            } catch (e) {
                status.textContent = 'No se pudieron cargar los datos. ' + e.message;
            }
        }
        const api = { redraw: () => items.length && dibujar(), reload: cargar, root };
        charts.push(api);
        cargar();
        return api;
    }

    function unirPorFecha(series) {
        const map = new Map();
        series.forEach((s, i) => s.data.forEach((p) => {
            if (!map.has(p.x)) map.set(p.x, { x: p.x, vals: Array(series.length).fill(null) });
            map.get(p.x).vals[i] = p.y;
        }));
        return [...map.values()].sort((a, b) => a.x - b.x);
    }

    function descargarCSV(nombre, series, items = null) {
        const q = (t) => `"${String(t).replace(/"/g, '""')}"`;
        const filas = items ? ['categoria,valor', ...items.map((i) => `${q(i.label)},${i.valor}`)]
            : [['fecha', ...series.map((s) => q(s.label))].join(',')];
        if (!items) for (const f of unirPorFecha(series)) filas.push([isoDate(new Date(f.x)), ...f.vals.map((v) => v ?? '')].join(','));
        const a = el('a', { href: URL.createObjectURL(new Blob([filas.join('\n')], { type: 'text/csv' })), download: nombre.replace(/[^\w\-áéíóúñ ]+/gi, '').trim().replace(/\s+/g, '_') + '.csv' });
        document.body.append(a);
        a.click();
        a.remove();
    }

    /** Minigráfico para las tarjetas de indicadores. */
    const sparks = [];
    function sparkline(canvas, data, colorVar = '--accent') {
        const draw = () => {
            Chart.getChart(canvas)?.destroy();
            const c = css(colorVar);
            new Chart(canvas, {
                type: 'line',
                data: { datasets: [{ data, borderColor: c, borderWidth: 1.5, pointRadius: 0, fill: 'origin', backgroundColor: c + '1a' }] },
                options: {
                    parsing: false, animation: false, responsive: true, maintainAspectRatio: false, events: [],
                    plugins: { legend: { display: false }, tooltip: { enabled: false } },
                    scales: { x: { type: 'linear', display: false }, y: { display: false } },
                },
            });
        };
        sparks.push(draw);
        draw();
    }

    /* ---------- Tema y navegación ---------- */
    function redibujarTodo() {
        charts.forEach((c) => c.redraw());
        sparks.forEach((d) => d());
    }

    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.querySelector('.nav-toggle');
        const nav = document.getElementById('nav');
        toggle?.addEventListener('click', () => {
            const open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(open));
        });
        document.querySelector('.theme-toggle')?.addEventListener('click', () => {
            const dark = document.documentElement.dataset.theme
                ? document.documentElement.dataset.theme === 'dark'
                : matchMedia('(prefers-color-scheme: dark)').matches;
            const nuevo = dark ? 'light' : 'dark';
            document.documentElement.dataset.theme = nuevo;
            try { localStorage.setItem('datar-tema', nuevo); } catch (e) {}
            redibujarTodo();
        });
    });
    matchMedia('(prefers-color-scheme: dark)').addEventListener('change', redibujarTodo);

    if (window.Chart) {
        Chart.defaults.font.family = css('--font') || 'system-ui, sans-serif';
        Chart.defaults.font.size = 12;
    }

    return { chart, ranking, series, cotizacion, indec, fromIds, fromIndec, transformar, esTrimestral, periodoTxt, combinar, promedioMensual, toXY, toTs, fmt, compact, sparkline, desdeRango, isoDate, el, color };
})();
