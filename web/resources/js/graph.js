// Graf koordinasi interaktif. Tata letak sudah dihitung worker Python (koordinat 0..1000),
// jadi di sini hanya menggambar dan menangani interaksi. Semua elemen dibuat lewat DOM API
// (tanpa innerHTML), sehingga data tidak pernah ditafsirkan sebagai HTML.

const NS = 'http://www.w3.org/2000/svg';
const COLOR = { flagged: '#c2620c', cluster: '#6f7f92', other: '#b9bfb6', edge: '#8a939c', ink: '#17201c' };

function el(name, attrs = {}) {
    const node = document.createElementNS(NS, name);
    for (const [key, value] of Object.entries(attrs)) node.setAttribute(key, value);
    return node;
}

function initGraph(root) {
    let data;
    try {
        data = JSON.parse(root.dataset.graph);
    } catch {
        return;
    }

    const svg = root.querySelector('[data-graph-canvas]');
    const tip = root.querySelector('[data-graph-tip]');
    const panel = root.querySelector('[data-graph-panel]');
    const byId = new Map(data.nodes.map((n) => [n.id, { ...n, neighbors: new Set() }]));
    const edges = data.edges.filter((e) => byId.has(e.a) && byId.has(e.b));
    for (const e of edges) {
        byId.get(e.a).neighbors.add(e.b);
        byId.get(e.b).neighbors.add(e.a);
    }

    const view = { x: -30, y: -30, size: 1060 };
    const applyView = () => svg.setAttribute('viewBox', `${view.x} ${view.y} ${view.size} ${view.size}`);
    applyView();

    const edgeLayer = svg.appendChild(el('g'));
    const nodeLayer = svg.appendChild(el('g'));
    const labelLayer = svg.appendChild(el('g', { 'pointer-events': 'none' }));

    const radius = (n) => 5 + Math.min(9, Math.sqrt(n.posts) * 1.6);
    const fill = (n) => (n.flagged ? COLOR.flagged : n.cluster ? COLOR.cluster : COLOR.other);

    for (const e of edges) {
        e.line = edgeLayer.appendChild(el('line', {
            stroke: COLOR.edge,
            'stroke-width': e.text && e.time ? 2 : 1.2,
            'stroke-opacity': 0.35,
            ...(e.time && !e.text ? { 'stroke-dasharray': '5 4' } : {}),
        }));
    }
    for (const n of byId.values()) {
        n.circle = nodeLayer.appendChild(el('circle', {
            r: radius(n), fill: fill(n), stroke: '#ffffff', 'stroke-width': 2, tabindex: 0, role: 'button',
            'aria-label': `${n.label}${n.cluster ? ', klaster ' + n.cluster : ''}, ${n.posts} post, ${n.links} hubungan`,
        }));
        n.circle.style.cursor = 'pointer';
    }

    // Label langsung untuk klaster ditandai, di atas anggotanya.
    const labels = new Map();
    for (const n of byId.values()) {
        if (n.flagged && !labels.has(n.cluster)) {
            const text = labelLayer.appendChild(el('text', {
                'text-anchor': 'middle', 'font-size': 22, 'font-weight': 600, fill: COLOR.ink,
                stroke: '#fbfbfa', 'stroke-width': 5, 'paint-order': 'stroke',
            }));
            text.textContent = n.cluster;
            labels.set(n.cluster, text);
        }
    }

    function position() {
        for (const n of byId.values()) {
            n.circle.setAttribute('cx', n.x);
            n.circle.setAttribute('cy', n.y);
        }
        for (const e of edges) {
            const a = byId.get(e.a);
            const b = byId.get(e.b);
            e.line.setAttribute('x1', a.x); e.line.setAttribute('y1', a.y);
            e.line.setAttribute('x2', b.x); e.line.setAttribute('y2', b.y);
        }
        for (const [cluster, text] of labels) {
            const members = [...byId.values()].filter((n) => n.cluster === cluster && !n.hidden);
            text.setAttribute('visibility', members.length ? 'visible' : 'hidden');
            if (!members.length) continue;
            text.setAttribute('x', members.reduce((sum, n) => sum + n.x, 0) / members.length);
            text.setAttribute('y', Math.min(...members.map((n) => n.y)) - 22);
        }
    }

    // ---- Filter
    const filters = { text: true, time: true, flagged: false };
    function applyFilters() {
        for (const n of byId.values()) {
            n.hidden = filters.flagged && !n.flagged;
            n.circle.setAttribute('visibility', n.hidden ? 'hidden' : 'visible');
        }
        for (const e of edges) {
            const kindOn = (e.text && filters.text) || (e.time && filters.time);
            e.hidden = !kindOn || byId.get(e.a).hidden || byId.get(e.b).hidden;
            e.line.setAttribute('visibility', e.hidden ? 'hidden' : 'visible');
        }
        position();
        highlight(selected);
    }
    root.querySelectorAll('[data-graph-filter]').forEach((input) => {
        input.addEventListener('change', () => {
            filters[input.dataset.graphFilter] = input.checked;
            applyFilters();
        });
    });

    // ---- Sorotan dan panel
    let selected = null;
    function highlight(node) {
        const active = node && !node.hidden ? node : null;
        const near = new Set();
        if (active) {
            near.add(active.id);
            for (const e of edges) {
                if (e.hidden) continue;
                if (e.a === active.id) near.add(e.b);
                if (e.b === active.id) near.add(e.a);
            }
        }
        for (const n of byId.values()) {
            n.circle.setAttribute('opacity', !active || near.has(n.id) ? 1 : 0.15);
            n.circle.setAttribute('stroke', active && n.id === active.id ? COLOR.ink : '#ffffff');
        }
        for (const e of edges) {
            const on = active && (e.a === active.id || e.b === active.id);
            e.line.setAttribute('stroke-opacity', !active ? 0.35 : on ? 0.9 : 0.05);
            e.line.setAttribute('stroke', on ? COLOR.ink : COLOR.edge);
        }
    }

    function describe(n) {
        const where = n.cluster ? `Klaster ${n.cluster}${n.flagged ? ' (ditandai)' : ' (tidak ditandai)'}` : 'Tidak masuk klaster';
        return `${where} · ${n.posts} post · ${n.links} hubungan`;
    }

    function showTip(n, event) {
        tip.textContent = '';
        const title = document.createElement('strong');
        title.className = 'font-mono';
        title.textContent = n.label;
        const body = document.createElement('div');
        body.className = 'text-muted';
        body.textContent = describe(n);
        tip.append(title, body);
        tip.hidden = false;
        const box = svg.getBoundingClientRect();
        const x = Math.min(event.clientX - box.left + 14, box.width - 270);
        tip.style.left = `${Math.max(8, x)}px`;
        tip.style.top = `${Math.max(8, event.clientY - box.top + 14)}px`;
    }

    function select(n) {
        selected = n;
        highlight(n);
        panel.hidden = !n;
        if (!n) return;
        panel.querySelector('[data-graph-panel-title]').textContent = n.label;
        panel.querySelector('[data-graph-panel-body]').textContent = describe(n);
        const link = panel.querySelector('[data-graph-panel-link]');
        const url = n.cluster ? data.clusterUrls[n.cluster] : null;
        link.hidden = !url;
        if (url) {
            link.href = url;
            link.textContent = `Lihat bukti klaster ${n.cluster}`;
        }
    }

    // ---- Koordinat layar -> koordinat graf
    function toGraph(event) {
        const box = svg.getBoundingClientRect();
        const scale = view.size / Math.min(box.width, box.height);
        const offsetX = (box.width - view.size / scale) / 2;
        const offsetY = (box.height - view.size / scale) / 2;
        return { x: view.x + (event.clientX - box.left - offsetX) * scale, y: view.y + (event.clientY - box.top - offsetY) * scale, scale };
    }

    // ---- Seret simpul, geser kanvas, perbesar
    let drag = null;
    for (const n of byId.values()) {
        n.circle.addEventListener('pointerenter', (event) => { if (!drag) { showTip(n, event); if (!selected) highlight(n); } });
        n.circle.addEventListener('pointermove', (event) => { if (!drag) showTip(n, event); });
        n.circle.addEventListener('pointerleave', () => { tip.hidden = true; if (!drag) highlight(selected); });
        n.circle.addEventListener('pointerdown', (event) => {
            event.stopPropagation();
            n.circle.setPointerCapture(event.pointerId);
            drag = { node: n, moved: false };
            tip.hidden = true;
        });
        n.circle.addEventListener('pointerup', () => {
            if (drag && !drag.moved) select(selected === n ? null : n);
            drag = null;
        });
        n.circle.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); select(selected === n ? null : n); }
        });
    }
    svg.addEventListener('pointerdown', (event) => {
        if (event.target !== svg) return;
        svg.setPointerCapture(event.pointerId);
        drag = { pan: toGraph(event), moved: false };
        svg.style.cursor = 'grabbing';
    });
    svg.addEventListener('pointermove', (event) => {
        if (!drag) return;
        const point = toGraph(event);
        drag.moved = true;
        if (drag.node) {
            drag.node.x = point.x;
            drag.node.y = point.y;
            position();
        } else if (drag.pan) {
            view.x -= point.x - drag.pan.x;
            view.y -= point.y - drag.pan.y;
            applyView();
        }
    });
    svg.addEventListener('pointerup', () => {
        if (drag && drag.pan && !drag.moved) select(null);
        drag = null;
        svg.style.cursor = '';
    });

    function zoom(factor, center) {
        const size = Math.min(2400, Math.max(120, view.size * factor));
        const c = center || { x: view.x + view.size / 2, y: view.y + view.size / 2 };
        view.x = c.x - (c.x - view.x) * (size / view.size);
        view.y = c.y - (c.y - view.y) * (size / view.size);
        view.size = size;
        applyView();
    }
    svg.addEventListener('wheel', (event) => {
        event.preventDefault();
        zoom(event.deltaY > 0 ? 1.15 : 1 / 1.15, toGraph(event));
    }, { passive: false });
    root.querySelectorAll('[data-graph-zoom]').forEach((button) => {
        button.addEventListener('click', () => {
            const kind = button.dataset.graphZoom;
            if (kind === 'reset') {
                Object.assign(view, { x: -30, y: -30, size: 1060 });
                applyView();
            } else {
                zoom(kind === 'in' ? 1 / 1.3 : 1.3);
            }
        });
    });

    applyFilters();
}

document.querySelectorAll('[data-graph]').forEach(initGraph);
