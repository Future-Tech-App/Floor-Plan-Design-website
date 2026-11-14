/* Floor Plan Designer — AI tools (generator + analyzer). The PHP endpoints do the
 * AI calls and layout work; this script sends requests, previews and saves plans. */
(function () {
    'use strict';

    // ------------------------------------------------------------------ shared
    async function postJson(url, body) {
        let res;
        try {
            res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin' });
        } catch (e) {
            throw new Error('Could not reach the server. Check your internet connection and try again.');
        }
        let data = null;
        try { data = await res.json(); } catch (e) { /* not JSON */ }
        if (!res.ok || !data || data.ok === false) {
            throw new Error((data && data.error) || ('Something went wrong (HTTP ' + res.status + '). Please try again.'));
        }
        return data;
    }
    function setStatus(el, text, kind) {
        el.hidden = !text;
        el.className = 'ai-status' + (kind === 'error' ? ' is-error' : '');
        el.innerHTML = (kind === 'busy' ? '<span class="spin" aria-hidden="true"></span>' : '') + '<span>' + FP.esc(text || '') + '</span>';
    }
    function planBounds(p) {
        const xs = [], ys = [];
        (p.walls || []).forEach(w => { xs.push(w.x1, w.x2); ys.push(w.y1, w.y2); });
        (p.rooms || []).forEach(r => (r.pts || []).forEach(q => { xs.push(q.x); ys.push(q.y); }));
        if (!xs.length) return null;
        return { x0: Math.min(...xs), y0: Math.min(...ys), x1: Math.max(...xs), y1: Math.max(...ys) };
    }
    function polyArea(pts) { let s = 0; pts.forEach((a, i) => { const b = pts[(i + 1) % pts.length]; s += a.x * b.y - b.x * a.y; }); return Math.abs(s) / 2; }
    function fmtArea(cm2) { return FP.settings().units === 'imperial' ? Math.round(cm2 / 929.0304).toLocaleString() + ' ft²' : (cm2 / 10000).toFixed(1) + ' m²'; }

    // Small static preview of a web plan on a canvas.
    function drawPreview(canvas, p) {
        const dpr = window.devicePixelRatio || 1, r = canvas.getBoundingClientRect();
        canvas.width = Math.round(r.width * dpr); canvas.height = Math.round(r.height * dpr);
        const c = canvas.getContext('2d'); c.setTransform(dpr, 0, 0, dpr, 0, 0);
        c.fillStyle = '#fff'; c.fillRect(0, 0, r.width, r.height);
        const b = planBounds(p); if (!b) return;
        const pad = 18, s = Math.min((r.width - pad * 2) / Math.max(1, b.x1 - b.x0), (r.height - pad * 2) / Math.max(1, b.y1 - b.y0));
        const ox = (r.width - (b.x1 - b.x0) * s) / 2 - b.x0 * s, oy = (r.height - (b.y1 - b.y0) * s) / 2 - b.y0 * s;
        const X = x => x * s + ox, Y = y => y * s + oy;
        (p.rooms || []).forEach(rm => {
            if (!rm.pts || rm.pts.length < 3) return;
            c.beginPath(); rm.pts.forEach((q, i) => (i ? c.lineTo(X(q.x), Y(q.y)) : c.moveTo(X(q.x), Y(q.y)))); c.closePath();
            c.fillStyle = rm.color || '#e3f2fd'; c.fill();
        });
        c.strokeStyle = '#37474f'; c.lineCap = 'square';
        (p.walls || []).forEach(w => { c.lineWidth = Math.max(1.5, (w.t || 15) * s); c.beginPath(); c.moveTo(X(w.x1), Y(w.y1)); c.lineTo(X(w.x2), Y(w.y2)); c.stroke(); });
        c.lineCap = 'butt';
        (p.openings || []).forEach(o => {
            const w = (p.walls || []).find(x => x.id === o.wall); if (!w) return;
            const L = Math.hypot(w.x2 - w.x1, w.y2 - w.y1); if (!L) return;
            const ux = (w.x2 - w.x1) / L, uy = (w.y2 - w.y1) / L, cx = w.x1 + (w.x2 - w.x1) * o.t, cy = w.y1 + (w.y2 - w.y1) * o.t;
            c.strokeStyle = o.kind === 'window' ? '#64b5f6' : '#ffffff'; c.lineWidth = Math.max(2, (w.t || 15) * s) + 1;
            c.beginPath(); c.moveTo(X(cx - ux * o.w / 2), Y(cy - uy * o.w / 2)); c.lineTo(X(cx + ux * o.w / 2), Y(cy + uy * o.w / 2)); c.stroke();
        });
        c.fillStyle = '#0b1b2b'; c.textAlign = 'center'; c.textBaseline = 'middle';
        (p.rooms || []).forEach(rm => {
            if (!rm.pts || rm.pts.length < 3) return;
            let cx = 0, cy = 0; rm.pts.forEach(q => { cx += q.x; cy += q.y; }); cx /= rm.pts.length; cy /= rm.pts.length;
            const xs = rm.pts.map(q => q.x), wpx = (Math.max(...xs) - Math.min(...xs)) * s;
            const fs = Math.max(8, Math.min(12, wpx / 8)); if (wpx < 34) return;
            c.font = '700 ' + fs + 'px Mulish, sans-serif'; c.fillText(rm.name || '', X(cx), Y(cy), wpx - 6);
        });
    }
    function savePlan(p, name) {
        const copy = JSON.parse(JSON.stringify(p));
        delete copy.id; delete copy.createdAt; delete copy.modifiedAt;
        copy.version = 1; copy.name = name;
        ['walls', 'rooms', 'openings', 'items', 'labels', 'dims'].forEach(k => { if (!Array.isArray(copy[k])) copy[k] = []; });
        return FPStore.save(copy);
    }
    function shortName(text) {
        const t = String(text || 'AI floor plan').replace(/\s+/g, ' ').trim();
        return t.length > 48 ? t.slice(0, 46).replace(/\s+\S*$/, '') + '…' : t;
    }

    // ------------------------------------------------------------------ generator
    const gen = document.querySelector('[data-ai-generator]');
    if (gen) {
        const form = document.getElementById('ai-form'), desc = document.getElementById('ai-desc'), go = document.getElementById('ai-go');
        const status = document.getElementById('ai-status'), results = document.getElementById('ai-results'), floorsEl = document.getElementById('ai-floors');
        const count = document.getElementById('ai-count');
        let last = null, savedIds = {};
        const updateCount = () => { count.textContent = desc.value.length; };
        desc.addEventListener('input', updateCount);
        try { desc.value = sessionStorage.getItem('fpd_ai_desc') || ''; } catch (e) { /* ignore */ }
        updateCount();
        gen.querySelectorAll('[data-example]').forEach(b => b.addEventListener('click', () => { desc.value = b.dataset.example; updateCount(); desc.focus(); }));

        const steps = ['Reading your description…', 'Designing the room layout…', 'Placing walls, doors and windows…', 'Almost done…'];
        async function run() {
            const text = desc.value.trim();
            if (text.length < 3) { setStatus(status, 'Please describe the plan you want (at least a few words).', 'error'); desc.focus(); return; }
            try { sessionStorage.setItem('fpd_ai_desc', text); } catch (e) { /* ignore */ }
            go.disabled = true; let i = 0;
            setStatus(status, steps[0], 'busy');
            const timer = setInterval(() => { i = Math.min(i + 1, steps.length - 1); setStatus(status, steps[i], 'busy'); }, 3500);
            try {
                const data = await postJson(gen.dataset.endpoint, { description: text });
                last = { data, text }; savedIds = {};
                showResults();
                setStatus(status, '', null);
                results.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (e) {
                setStatus(status, e.message, 'error');
            } finally { clearInterval(timer); go.disabled = false; }
        }
        form.addEventListener('submit', e => { e.preventDefault(); run(); });
        document.getElementById('ai-again').addEventListener('click', () => { window.scrollTo({ top: 0, behavior: 'smooth' }); run(); });

        function floorName(i) { const f = last.data.floors[i]; return shortName(last.text) + (last.data.floors.length > 1 ? ' — ' + f.name : ''); }
        function saveFloor(i) {
            if (savedIds[i]) return savedIds[i];
            const saved = savePlan(last.data.floors[i].plan, floorName(i));
            if (saved) savedIds[i] = saved.id;
            return savedIds[i];
        }
        function showResults() {
            const d = last.data;
            results.hidden = false;
            document.getElementById('ai-summary').textContent = d.description && d.description !== last.text ? d.description : '';
            document.getElementById('ai-local').hidden = d.source !== 'local';
            floorsEl.innerHTML = d.floors.map((f, i) => {
                const rooms = (f.plan.rooms || []), area = rooms.reduce((s, r) => s + (r.pts && r.pts.length > 2 ? polyArea(r.pts) : 0), 0);
                return '<article class="ai-floor"><canvas data-floor="' + i + '" aria-label="' + FP.esc(f.name) + ' preview"></canvas>' +
                    '<div class="ai-floor-body"><h3>' + FP.esc(f.name) + '</h3>' +
                    '<p>' + rooms.length + ' rooms · ' + fmtArea(area) + '</p>' +
                    '<p>' + FP.esc(rooms.slice(0, 8).map(r => r.name).join(', ') + (rooms.length > 8 ? '…' : '')) + '</p>' +
                    '<div class="ai-floor-actions"><button type="button" class="btn" data-open="' + i + '">Open in Editor</button>' +
                    '<button type="button" class="btn btn-outline" data-3d="' + i + '">3D View</button></div></div></article>';
            }).join('');
            floorsEl.querySelectorAll('canvas[data-floor]').forEach(cv => drawPreview(cv, d.floors[+cv.dataset.floor].plan));
        }
        floorsEl.addEventListener('click', e => {
            const open = e.target.closest('[data-open]'), v3 = e.target.closest('[data-3d]');
            if (!open && !v3) return;
            const id = saveFloor(+(open || v3).dataset[open ? 'open' : '3d']);
            if (id) location.href = FP.url((open ? '/app/editor?plan=' : '/app/3d?plan=') + encodeURIComponent(id));
        });
        document.getElementById('ai-save-all').addEventListener('click', e => {
            if (!last) return;
            last.data.floors.forEach((f, i) => saveFloor(i));
            e.target.closest('button').textContent = 'Saved ' + last.data.floors.length + (last.data.floors.length > 1 ? ' plans' : ' plan') + ' ✓';
            setTimeout(() => { location.href = FP.url('/app/plans'); }, 700);
        });
        window.addEventListener('resize', () => { if (last && !results.hidden) floorsEl.querySelectorAll('canvas[data-floor]').forEach(cv => drawPreview(cv, last.data.floors[+cv.dataset.floor].plan)); });
    }

    // ------------------------------------------------------------------ analyzer
    const an = document.querySelector('[data-ai-analyzer]');
    if (an) {
        const file = document.getElementById('ai-file'), drop = document.getElementById('ai-drop'), preview = document.getElementById('ai-preview');
        const emptyEl = document.getElementById('ai-drop-empty'), go = document.getElementById('ai-analyze'), clear = document.getElementById('ai-clear');
        const status = document.getElementById('ai-status'), results = document.getElementById('ai-results');
        let image = null, last = null, savedId = null;

        // Shrink to at most 1600 px on the long side and re-encode as JPEG (keeps uploads small).
        function loadImage(f) {
            if (!f) return;
            if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { setStatus(status, 'Please choose a JPG, PNG or WebP image.', 'error'); return; }
            if (f.size > 25 * 1024 * 1024) { setStatus(status, 'That image is too large (over 25 MB).', 'error'); return; }
            const url = URL.createObjectURL(f), img = new Image();
            img.onload = () => {
                const max = 1600, k = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
                const c = document.createElement('canvas'); c.width = Math.round(img.naturalWidth * k); c.height = Math.round(img.naturalHeight * k);
                const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.drawImage(img, 0, 0, c.width, c.height);
                image = c.toDataURL('image/jpeg', 0.85);
                URL.revokeObjectURL(url);
                preview.src = image; preview.hidden = false; emptyEl.hidden = true;
                go.disabled = false; clear.hidden = false; results.hidden = true; setStatus(status, '', null);
            };
            img.onerror = () => { URL.revokeObjectURL(url); setStatus(status, 'That file could not be read as an image.', 'error'); };
            img.src = url;
        }
        file.addEventListener('change', () => loadImage(file.files[0]));
        ['dragenter', 'dragover'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.add('is-over'); }));
        ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, e => { e.preventDefault(); drop.classList.remove('is-over'); }));
        drop.addEventListener('drop', e => loadImage(e.dataTransfer.files[0]));
        clear.addEventListener('click', () => { file.value = ''; image = null; preview.hidden = true; emptyEl.hidden = false; go.disabled = true; clear.hidden = true; results.hidden = true; file.click(); });

        const steps = ['Uploading your image…', 'Reading the floor plan…', 'Finding rooms and measurements…', 'Checking for missed rooms…', 'Building your editable plan…'];
        go.addEventListener('click', async () => {
            if (!image) return;
            go.disabled = true; let i = 0;
            setStatus(status, steps[0], 'busy');
            const timer = setInterval(() => { i = Math.min(i + 1, steps.length - 1); setStatus(status, steps[i], 'busy'); }, 4000);
            try {
                const data = await postJson(an.dataset.endpoint, { image });
                last = data; savedId = null;
                render(data);
                setStatus(status, '', null);
                results.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (e) { setStatus(status, e.message, 'error'); }
            finally { clearInterval(timer); go.disabled = false; }
        });

        function num(v, d) { const n = Number(v); return Number.isFinite(n) ? n.toLocaleString(undefined, { maximumFractionDigits: d == null ? 1 : d }) : FP.esc(String(v)); }
        function render(data) {
            const r = data.result || {}, rooms = Array.isArray(r.rooms) ? r.rooms : [];
            results.hidden = false;
            // Annotated image: boxes are normalised 0..1.
            document.getElementById('ai-annot').innerHTML = '<img src="' + image + '" alt="Analysed floor plan">' + rooms.map(rm => {
                const b = rm.box; if (!b) return '';
                const col = rm.color || '#1e88e5';
                return '<div class="ai-box" style="left:' + (b.x * 100) + '%;top:' + (b.y * 100) + '%;width:' + (b.w * 100) + '%;height:' + (b.h * 100) + '%;border-color:' + FP.esc(col) + ';background:' + FP.esc(col) + '22">' +
                    '<span style="background:' + FP.esc(col) + '">' + (rm.number ? rm.number + '. ' : '') + FP.esc(rm.name || '') + '</span></div>';
            }).join('');
            // Summary
            const stats = statRows(r);
            document.getElementById('ai-summary-box').innerHTML = '<h2>Summary</h2>' +
                (stats.length ? '<dl class="ai-stats">' + stats.map(s => '<div><dt>' + FP.esc(s[0]) + '</dt><dd>' + s[1] + '</dd></div>').join('') + '</dl>' : '') +
                (r.summary ? '<p>' + FP.esc(r.summary) + '</p>' : '');
            // Rooms table
            document.getElementById('ai-rooms').innerHTML = rooms.length ? '<table class="ai-table"><thead><tr><th>#</th><th>Room</th><th>Type</th><th>Size</th><th>Area</th><th>Door / window</th></tr></thead><tbody>' + rooms.map(rm => {
                const u = rm.unit || '', est = rm.estimated ? '≈ ' : '';
                const size = rm.width && rm.length ? est + num(rm.width) + ' × ' + num(rm.length) + (u ? ' ' + FP.esc(u) : '') : '—';
                const area = rm.area ? est + num(rm.area) + ' ' + FP.esc(rm.area_unit || (u ? u + '²' : '')) : '—';
                const ow = [rm.has_door ? 'Door' : '', rm.has_window ? 'Window' : ''].filter(Boolean).join(', ') || '—';
                return '<tr><td>' + FP.esc(String(rm.number || '')) + '</td><td><span class="sw" style="background:' + FP.esc(rm.color || '#90a4ae') + '"></span>' + FP.esc(rm.name || 'Room') + '</td><td>' + FP.esc(rm.label || String(rm.type || '').replace(/_/g, ' ')) + '</td><td>' + size + '</td><td>' + area + '</td><td>' + ow + '</td></tr>';
            }).join('') + '</tbody></table>' + (rooms.some(rm => rm.estimated) ? '<p class="hint" style="margin-top:10px">≈ sizes were measured from the image because the plan had no readable dimensions.</p>' : '') : '<p>No rooms listed.</p>';
            const hasPlan = data.plan && ((data.plan.walls || []).length || (data.plan.rooms || []).length);
            document.getElementById('ai-open').disabled = !hasPlan;
            document.getElementById('ai-3d').disabled = !hasPlan;
        }
        function statRows(r) {
            const rows = [];
            if (r.total_area != null) {
                const t = r.total_area;
                rows.push(['Total area', typeof t === 'object' ? num(t.value) + ' <small>' + FP.esc(t.unit || '') + '</small>' : num(t) + (r.area_unit ? ' <small>' + FP.esc(r.area_unit) + '</small>' : '')]);
            }
            if (r.total_area != null && r.total_area_estimated) rows[rows.length - 1][1] = '≈ ' + rows[rows.length - 1][1];
            if (r.room_count != null) rows.push(['Rooms', num(r.room_count, 0)]);
            if (r.total_walls != null) rows.push(['Walls', num(r.total_walls, 0)]);
            const counts = r.counts && typeof r.counts === 'object' ? r.counts : {};
            Object.keys(counts).forEach(k => { if (counts[k]) rows.push([k.replace(/_/g, ' '), num(counts[k], 0)]); });
            return rows;
        }
        function saveResult() {
            if (savedId) return savedId;
            const name = (last.plan && last.plan.name) || ('Analyzed plan ' + new Date().toLocaleDateString());
            const s = savePlan(last.plan, name); savedId = s && s.id; return savedId;
        }
        document.getElementById('ai-open').addEventListener('click', () => { const id = last && saveResult(); if (id) location.href = FP.url('/app/editor?plan=' + encodeURIComponent(id)); });
        document.getElementById('ai-3d').addEventListener('click', () => { const id = last && saveResult(); if (id) location.href = FP.url('/app/3d?plan=' + encodeURIComponent(id)); });
    }

    window.FPAi = { postJson, setStatus, drawPreview, savePlan, shortName, polyArea, fmtArea };
})();
