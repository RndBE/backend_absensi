@extends('admin.layouts.app')
@section('title', 'Graf Relasi Divisi')

@section('content')
<style>
    .gfx { background: #14172a; }
    .gfx-side { background: #191d33; }
    .gfx-label { font-size: 10.5px; letter-spacing: .09em; text-transform: uppercase; color: #7b86ad; }
    .gfx-row { display: flex; align-items: center; gap: 8px; padding: 5px 0; cursor: pointer; user-select: none; }
    .gfx-row:hover .gfx-name { color: #fff; }
    .gfx-name { color: #c8cee6; font-size: 12.5px; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .gfx-count { color: #6f7aa0; font-size: 11.5px; font-variant-numeric: tabular-nums; }
    .gfx-dot { width: 9px; height: 9px; border-radius: 50%; flex: none; }
    .gfx-box { width: 13px; height: 13px; border-radius: 3px; border: 1.5px solid #3d466e; flex: none; position: relative; }
    .gfx-row.on .gfx-box { background: #4f7cf7; border-color: #4f7cf7; }
    .gfx-row.on .gfx-box::after { content: ''; position: absolute; left: 3.5px; top: 1px; width: 4px; height: 7px; border: solid #fff; border-width: 0 1.6px 1.6px 0; transform: rotate(45deg); }
    .gfx-row.off .gfx-name, .gfx-row.off .gfx-count { color: #545d80; }
    .gfx-row.off .gfx-dot { opacity: .3; }
    .gfx-chip { font-size: 11px; padding: 3px 9px; border-radius: 999px; border: 1px solid #333a5e; color: #9aa4c6; cursor: pointer; user-select: none; }
    .gfx-chip.on { background: #232a4a; border-color: #4f7cf7; color: #dfe6ff; }
    #gfx-tip { position: absolute; pointer-events: none; opacity: 0; transition: opacity .12s;
        background: #0d1020; border: 1px solid #2e3557; border-radius: 8px; padding: 7px 10px;
        color: #e7ebf8; font-size: 12px; line-height: 1.45; max-width: 230px; z-index: 20; }
    #gfx-tip .t-sub { color: #8d97bd; font-size: 11px; }
</style>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-[15px] font-bold text-gray-900">
                <span class="material-symbols-outlined text-[18px] align-text-bottom">account_tree</span> Graf Relasi Divisi
            </h3>
            <p class="text-[12px] text-gray-500 mt-1 max-w-2xl">
                Satu simpul satu karyawan, warna mengikuti divisi, ukuran mengikuti jumlah relasi.
                Simpul terbesar adalah orang dengan garis penilaian terbanyak. Halaman ini hanya
                membaca; mitra disunting di
                <a href="{{ route('admin.kpi-cross-matrix.index') }}" class="text-indigo-600 hover:underline">Matriks Relasi Kerja</a>.
            </p>
        </div>

        @if($periods->isNotEmpty())
        <form method="GET" class="flex items-center gap-2">
            <label class="text-[12px] text-gray-500">Periode</label>
            <select name="period" onchange="this.form.submit()"
                    class="px-3 py-1.5 border border-gray-300 rounded-lg text-[12.5px] text-gray-800 bg-white outline-none focus:border-indigo-500">
                @foreach($periods as $option)
                    <option value="{{ $option->id }}" @selected($period && $period->id === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </form>
        @endif
    </div>

    {{-- minmax(0,1fr), bukan 1fr: 1fr punya lantai auto, jadi satu anak yang lebih lebar dari
         kolomnya akan melebarkan seluruh grid dan bikin kartu meluber ke kanan. --}}
    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_268px]">
        <div class="gfx min-w-0">
            {{-- Chip dan keterangan berada di aliran normal, bukan absolut di atas kanvas.
                 Versi absolut tidak memesan ruang, jadi begitu kanvas memendek keduanya
                 bertumpuk dan menabrak sidebar. --}}
            <div class="flex flex-wrap gap-2 px-4 pt-4">
                <span class="gfx-chip on" data-edge="komando">Garis penilaian</span>
                <span class="gfx-chip on" data-edge="silang">Penilaian silang</span>
                <span class="gfx-chip on" data-edge="kerja">Rantai kerja</span>
                <span class="gfx-chip" id="gfx-relayout">Susun ulang</span>
            </div>

            <div class="relative mt-3" id="gfx-stage">
                <svg id="graph" viewBox="0 0 900 600" preserveAspectRatio="xMidYMid meet"
                     class="block w-full touch-none" style="height:clamp(300px,44vw,540px)"></svg>

                <div id="gfx-tip"></div>
                <div id="gfx-empty" class="hidden absolute inset-0 flex items-center justify-center text-[13px] text-slate-400">
                    Belum ada divisi atau karyawan yang bisa digambar.
                </div>
            </div>

            <p class="gfx-label px-4 pb-4 pt-3" style="line-height:1.7">
                simpul = karyawan · warna = divisi · besar = paling banyak relasi ·
                garis utuh = penilaian · garis putus = rantai kerja nyata
            </p>
        </div>

        <aside class="gfx-side min-w-0 px-4 py-4 border-t lg:border-t-0 border-slate-800">
            <p class="gfx-label mb-2">Divisi</p>

            <div class="gfx-row on" id="gfx-all">
                <span class="gfx-box"></span>
                <span class="gfx-name">Pilih semua</span>
            </div>

            <div id="gfx-legend" class="mt-0.5"></div>

            <div class="mt-5 pt-4 border-t border-slate-700/60">
                <p class="gfx-label mb-2">Rincian</p>
                <div id="gfx-detail" class="text-[12px] text-slate-400">Klik simpul untuk melihat rinciannya.</div>
            </div>
        </aside>
    </div>
</div>

<script id="graph-data" type="application/json">@json($graph)</script>

<script>
(function () {
    const data = JSON.parse(document.getElementById('graph-data').textContent);
    const svg = document.getElementById('graph');
    const stage = document.getElementById('gfx-stage');
    const tip = document.getElementById('gfx-tip');
    const legend = document.getElementById('gfx-legend');
    const detail = document.getElementById('gfx-detail');
    const empty = document.getElementById('gfx-empty');
    const W = 900, H = 600;
    const NS = 'http://www.w3.org/2000/svg';

    if (!data.nodes.length) {
        empty.classList.remove('hidden');
        return;
    }

    const el = (name, attrs) => {
        const node = document.createElementNS(NS, name);
        for (const k in attrs) node.setAttribute(k, attrs[k]);
        return node;
    };
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

    const colorOf = {};
    data.divisions.forEach(d => { colorOf[d.id === null ? 'null' : d.id] = d.color; });
    const tint = (node) => colorOf[node.division === null ? 'null' : node.division] || '#94a3b8';

    const hidden = new Set();
    const activeKinds = new Set(['komando', 'silang', 'kerja']);
    let selected = null, hovered = null;

    const maxDegree = Math.max(1, ...data.nodes.map(n => n.degree));
    const radiusOf = (n) => 3.4 + 6.6 * Math.sqrt(n.degree / maxDegree);

    const nodes = data.nodes.map((n, i) => {
        const angle = (i / data.nodes.length) * Math.PI * 2;
        return { ...n, x: W / 2 + 200 * Math.cos(angle), y: H / 2 + 160 * Math.sin(angle), vx: 0, vy: 0 };
    });
    const index = Object.fromEntries(nodes.map(n => [n.id, n]));

    const edges = data.edges
        .filter(e => index[e.source] && index[e.target])
        .map(e => ({
            ...e, a: index[e.source], b: index[e.target],
            // Sisi komando pendek, sisi silang panjang: gugusan per divisi mengencang sendiri
            // sementara jalur antar divisi merentang, dan bentuk jaringannya jadi terbaca.
            // Rantai kerja di antara keduanya — cukup menarik agar pasangan yang benar-benar
            // bekerja bersama berdekatan, tanpa meruntuhkan gugusan divisinya.
            rest: e.kind === 'komando' ? 62 : (e.kind === 'kerja' ? 150 : 230),
            stiff: e.kind === 'komando' ? 0.035 : (e.kind === 'kerja' ? 0.016 : 0.006),
        }));

    const visible = (n) => !hidden.has(n.division === null ? 'null' : String(n.division));
    const liveEdges = () => edges.filter(e => activeKinds.has(e.kind) && visible(e.a) && visible(e.b));

    /**
     * Tata letak gaya tarik-tolak. Dijalankan sampai tenang lalu berhenti — bukan animasi
     * abadi: 30 simpul tidak butuh itu, dan loop yang tak pernah berhenti hanya membakar
     * baterai di halaman yang biasanya dibiarkan terbuka.
     */
    function relax(steps) {
        const live = liveEdges();

        for (let step = 0; step < steps; step++) {
            const cooling = 1 - step / steps;

            for (let i = 0; i < nodes.length; i++) {
                if (!visible(nodes[i])) continue;
                for (let j = i + 1; j < nodes.length; j++) {
                    if (!visible(nodes[j])) continue;
                    const p = nodes[i], q = nodes[j];
                    let dx = q.x - p.x, dy = q.y - p.y;
                    const dist = Math.hypot(dx, dy) || 0.01;
                    const gap = radiusOf(p) + radiusOf(q) + 9;
                    const push = 1400 / (dist * dist) + (dist < gap ? (gap - dist) * 0.6 : 0);
                    dx /= dist; dy /= dist;
                    p.vx -= dx * push; p.vy -= dy * push;
                    q.vx += dx * push; q.vy += dy * push;
                }
            }

            live.forEach(edge => {
                let dx = edge.b.x - edge.a.x, dy = edge.b.y - edge.a.y;
                const dist = Math.hypot(dx, dy) || 0.01;
                const pull = (dist - edge.rest) * edge.stiff;
                dx /= dist; dy /= dist;
                edge.a.vx += dx * pull; edge.a.vy += dy * pull;
                edge.b.vx -= dx * pull; edge.b.vy -= dy * pull;
            });

            nodes.forEach(n => {
                n.vx += (W / 2 - n.x) * 0.0022;
                n.vy += (H / 2 - n.y) * 0.0022;
                n.x += n.vx * cooling * 0.55;
                n.y += n.vy * cooling * 0.55;
                n.vx *= 0.85; n.vy *= 0.85;
                const pad = radiusOf(n) + 14;
                n.x = Math.min(W - pad, Math.max(pad, n.x));
                n.y = Math.min(H - pad, Math.max(pad, n.y));
            });
        }
    }

    /**
     * Setelah tata letak tenang, gugusan hampir selalu berhenti jauh lebih kecil daripada
     * bidangnya — hasilnya graf mungil di tengah kanvas kosong. Menyetel ulang tetapan
     * tarik-tolak tidak menyelesaikannya: skalanya berubah setiap kali jumlah simpul yang
     * tampak berubah, misalnya saat divisi disaring. Jadi hasilnya diskalakan dan digeser
     * supaya mengisi bidang, dengan satu faktor untuk kedua sumbu agar bentuknya tidak gepeng.
     */
    function fitToView() {
        const shown = nodes.filter(visible);
        if (shown.length < 2) return;

        const pad = 26;
        let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;

        shown.forEach(n => {
            const r = radiusOf(n) + 4;
            minX = Math.min(minX, n.x - r); maxX = Math.max(maxX, n.x + r);
            minY = Math.min(minY, n.y - r); maxY = Math.max(maxY, n.y + r);
        });

        const spanX = Math.max(1, maxX - minX), spanY = Math.max(1, maxY - minY);
        // Dibatasi 1.9 supaya sisa dua-tiga simpul tidak melar jadi bulatan raksasa.
        const scale = Math.min((W - pad * 2) / spanX, (H - pad * 2) / spanY, 1.9);

        const offsetX = (W - spanX * scale) / 2 - minX * scale;
        const offsetY = (H - spanY * scale) / 2 - minY * scale;

        shown.forEach(n => {
            n.x = n.x * scale + offsetX;
            n.y = n.y * scale + offsetY;
        });
    }

    const neighbours = (id) => {
        const set = new Set();
        liveEdges().forEach(e => {
            if (e.a.id === id) set.add(e.b.id);
            if (e.b.id === id) set.add(e.a.id);
        });
        return set;
    };

    function render() {
        svg.textContent = '';

        // Semua divisi dimatikan lewat penyaring menghasilkan bidang kosong. Tanpa keterangan,
        // itu tidak bisa dibedakan dari halaman yang gagal memuat.
        const anyVisible = nodes.some(visible) && activeKinds.size > 0;
        empty.classList.toggle('hidden', anyVisible);
        empty.textContent = nodes.some(visible)
            ? 'Semua jenis garis dimatikan. Nyalakan salah satu tombol di atas.'
            : 'Semua divisi disembunyikan. Nyalakan lewat daftar Divisi di samping.';

        const focus = selected || hovered;
        const near = focus ? neighbours(focus) : null;

        const gEdges = el('g', { fill: 'none', 'stroke-linecap': 'round' });
        liveEdges().forEach(edge => {
            const lit = focus && (edge.a.id === focus || edge.b.id === focus);
            const base = edge.kind === 'komando' ? 0.9 : (edge.kind === 'kerja' ? 0.85 : 0.6);
            const faint = edge.kind === 'komando' ? 0.5 : (edge.kind === 'kerja' ? 0.4 : 0.22);

            gEdges.appendChild(el('line', {
                x1: edge.a.x, y1: edge.a.y, x2: edge.b.x, y2: edge.b.y,
                stroke: tint(edge.a),
                'stroke-width': lit ? 1.6 : base,
                'stroke-opacity': focus ? (lit ? 0.85 : 0.06) : faint,
                // Rantai kerja digambar putus-putus supaya tidak tertukar dengan garis
                // penilaian — keduanya sering menghubungkan orang yang sama tetapi artinya beda.
                'stroke-dasharray': edge.kind === 'kerja' ? '4 3' : '',
            }));
        });
        svg.appendChild(gEdges);

        const gNodes = el('g', {});
        nodes.filter(visible).forEach(node => {
            const dim = focus && node.id !== focus && !near.has(node.id);
            const group = el('g', { 'data-id': node.id, style: 'cursor:pointer' });

            group.appendChild(el('circle', {
                cx: node.x, cy: node.y, r: radiusOf(node) + (node.id === focus ? 2.5 : 0),
                fill: tint(node),
                'fill-opacity': dim ? 0.16 : 1,
                stroke: node.id === focus ? '#ffffff' : '#14172a',
                'stroke-width': node.id === focus ? 1.8 : 0.9,
            }));

            // Cincin tipis menandai staf lintas fungsi — mereka yang dinilai personal di Lapis B.
            if (node.cross_functional && !dim) {
                group.appendChild(el('circle', {
                    cx: node.x, cy: node.y, r: radiusOf(node) + 3.2,
                    fill: 'none', stroke: tint(node), 'stroke-opacity': 0.5, 'stroke-width': 0.9,
                }));
            }

            gNodes.appendChild(group);
        });
        svg.appendChild(gNodes);
    }

    function renderLegend() {
        legend.textContent = '';
        data.divisions.forEach(division => {
            const key = division.id === null ? 'null' : String(division.id);
            const row = document.createElement('div');
            row.className = 'gfx-row ' + (hidden.has(key) ? 'off' : 'on');
            row.dataset.division = key;
            row.innerHTML = `<span class="gfx-box"></span>
                <span class="gfx-dot" style="background:${esc(division.color)}"></span>
                <span class="gfx-name" title="${esc(division.name)}">${esc(division.name)}</span>
                <span class="gfx-count">${division.members}</span>`;
            legend.appendChild(row);
        });
        document.getElementById('gfx-all').className = 'gfx-row ' + (hidden.size ? 'off' : 'on');
    }

    function describe(node) {
        if (!node) {
            detail.innerHTML = '<span class="text-slate-400">Klik simpul untuk melihat rinciannya.</span>';
            return;
        }
        const division = data.divisions.find(d => (d.id === null ? null : d.id) === node.division);
        const rows = [
            ['Divisi', division ? division.name : 'Di luar divisi'],
            ['Level', node.level || '—'],
            ['Relasi', node.degree],
        ];
        if (node.assessor) rows.push(['Penilai silang', node.layer_b ? 'Lapis A + B' : 'Lapis A']);
        if (node.cross_functional) rows.push(['Lintas fungsi', 'Ya']);
        if (!node.assessed) rows.push(['Dinilai', 'Tidak']);

        const work = (node.work || []).length
            ? `<p class="gfx-label mt-3.5 mb-1.5">Rantai kerja</p>
               <ul class="space-y-1">${node.work.map(label =>
                    `<li class="text-slate-300 leading-snug">· ${esc(label)}</li>`).join('')}</ul>`
            : '';

        detail.innerHTML = `<p class="text-[13px] text-slate-100 font-semibold leading-snug">${esc(node.name)}</p>
            <p class="text-[11.5px] text-slate-400 mt-0.5">${esc(node.position || '—')}</p>
            <dl class="mt-2.5 space-y-1">${rows.map(([k, v]) =>
                `<div class="flex justify-between gap-2"><dt class="text-slate-500">${esc(k)}</dt><dd class="text-slate-200 text-right">${esc(v)}</dd></div>`
            ).join('')}</dl>${work}`;
    }

    function showTip(node, event) {
        const box = stage.getBoundingClientRect();
        tip.innerHTML = `${esc(node.name)}<br><span class="t-sub">${esc(node.position || '—')} · ${esc(node.level || '—')} · ${node.degree} relasi</span>`;
        tip.style.opacity = '1';
        const left = Math.min(box.width - 240, event.clientX - box.left + 12);
        tip.style.left = Math.max(8, left) + 'px';
        tip.style.top = Math.max(8, event.clientY - box.top - 12) + 'px';
    }

    let dragging = null, moved = 0;
    const pointOf = (event) => {
        const box = svg.getBoundingClientRect();
        return { x: (event.clientX - box.left) / box.width * W, y: (event.clientY - box.top) / box.height * H };
    };

    svg.addEventListener('pointerdown', (event) => {
        const group = event.target.closest('g[data-id]');
        if (!group) { selected = null; describe(null); render(); return; }
        dragging = index[group.dataset.id] || null;
        moved = 0;
        svg.setPointerCapture(event.pointerId);
    });

    svg.addEventListener('pointermove', (event) => {
        if (dragging) {
            const point = pointOf(event);
            moved += Math.hypot(point.x - dragging.x, point.y - dragging.y);
            dragging.x = point.x; dragging.y = point.y; dragging.vx = dragging.vy = 0;
            render();
            return;
        }
        const group = event.target.closest('g[data-id]');
        const node = group ? index[group.dataset.id] : null;
        if (node) {
            showTip(node, event);
            if (hovered !== node.id && !selected) { hovered = node.id; render(); }
        } else {
            tip.style.opacity = '0';
            if (hovered && !selected) { hovered = null; render(); }
        }
    });

    svg.addEventListener('pointerup', (event) => {
        if (dragging && moved < 4) {
            selected = selected === dragging.id ? null : dragging.id;
            hovered = null;
            describe(selected ? index[selected] : null);
        }
        dragging = null;
        try { svg.releasePointerCapture(event.pointerId); } catch (e) { /* pointer sudah lepas */ }
        render();
    });

    svg.addEventListener('pointerleave', () => {
        tip.style.opacity = '0';
        if (hovered) { hovered = null; render(); }
    });

    legend.addEventListener('click', (event) => {
        const row = event.target.closest('.gfx-row');
        if (!row) return;
        const key = row.dataset.division;
        hidden.has(key) ? hidden.delete(key) : hidden.add(key);
        if (selected && !visible(index[selected])) { selected = null; describe(null); }
        renderLegend(); relax(260); fitToView(); render();
    });

    document.getElementById('gfx-all').addEventListener('click', () => {
        hidden.size ? hidden.clear() : data.divisions.forEach(d => hidden.add(d.id === null ? 'null' : String(d.id)));
        if (selected && !visible(index[selected])) { selected = null; describe(null); }
        renderLegend(); relax(260); fitToView(); render();
    });

    document.querySelectorAll('.gfx-chip[data-edge]').forEach(chip => {
        chip.addEventListener('click', () => {
            const kind = chip.dataset.edge;
            activeKinds.has(kind) ? activeKinds.delete(kind) : activeKinds.add(kind);
            chip.classList.toggle('on', activeKinds.has(kind));
            relax(260); fitToView(); render();
        });
    });

    document.getElementById('gfx-relayout').addEventListener('click', () => {
        nodes.forEach((n, i) => {
            const angle = (i / nodes.length) * Math.PI * 2;
            n.x = W / 2 + 200 * Math.cos(angle); n.y = H / 2 + 160 * Math.sin(angle);
            n.vx = n.vy = 0;
        });
        relax(520); fitToView(); render();
    });

    renderLegend();
    relax(520);
    fitToView();
    render();
    describe(null);
})();
</script>
@endsection
