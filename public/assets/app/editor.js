/* Floor Plan Designer — web editor (HTML5 canvas, no libraries).
 * World units are centimetres. Plans are saved with FPStore (browser storage). */
(function () {
    'use strict';
    var root = document.getElementById('editor');
    if (!root || !window.FPStore) return;

    // ------------------------------------------------------------------ state
    var settings = FP.settings();
    var SYMBOL_BASE = root.getAttribute('data-symbols-base');
    var LIBRARY = JSON.parse(root.getAttribute('data-library') || '[]');
    var canvas = document.getElementById('canvas');
    var ctx = canvas.getContext('2d');
    var dpr = window.devicePixelRatio || 1;
    var W = 0, H = 0;
    var view = { x: 0, y: 0, s: 0.5 };          // screen = world * s + (x, y)
    var plan = blankPlan();
    var tool = 'select';
    var sel = null;                               // { type, id }
    var undoStack = [], redoStack = [];
    var draft = null;                             // in-progress drawing
    var hoverPt = null;                           // snapped world point under cursor
    var hoverRaw = null;
    var pendingSymbol = null;                     // library item to place
    var drag = null;                              // active drag op
    var pointers = new Map();
    var pinch = null;
    var spaceDown = false;
    var images = {};
    var saveTimer = null, savedOnce = false;
    var SYMBOL_SCALE = 2.5;
    var COLORS = ['#e3f2fd', '#e8f5e9', '#fff3e0', '#fce4ec', '#ede7f6', '#e0f7fa', '#f1f8e9', '#eceff1'];

    function blankPlan() {
        return { version: 1, name: 'Untitled plan', walls: [], rooms: [], openings: [], items: [], labels: [], dims: [] };
    }
    function uid(p) { return p + Date.now().toString(36) + Math.random().toString(36).slice(2, 6); }

    // ------------------------------------------------------------------ geometry
    function toWorld(sx, sy) { return { x: (sx - view.x) / view.s, y: (sy - view.y) / view.s }; }
    function toScreen(x, y) { return { x: x * view.s + view.x, y: y * view.s + view.y }; }
    function dist(a, b) { return Math.hypot(a.x - b.x, a.y - b.y); }
    function wallA(w) { return { x: w.x1, y: w.y1 }; }
    function wallB(w) { return { x: w.x2, y: w.y2 }; }
    function wallLen(w) { return Math.hypot(w.x2 - w.x1, w.y2 - w.y1); }
    function projT(p, a, b) {
        var dx = b.x - a.x, dy = b.y - a.y, l2 = dx * dx + dy * dy;
        if (!l2) return 0;
        return Math.max(0, Math.min(1, ((p.x - a.x) * dx + (p.y - a.y) * dy) / l2));
    }
    function distToSeg(p, a, b) {
        var t = projT(p, a, b);
        return dist(p, { x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t });
    }
    function pointInPoly(p, pts) {
        var inside = false;
        for (var i = 0, j = pts.length - 1; i < pts.length; j = i++) {
            var a = pts[i], b = pts[j];
            if (((a.y > p.y) !== (b.y > p.y)) && (p.x < (b.x - a.x) * (p.y - a.y) / (b.y - a.y) + a.x)) inside = !inside;
        }
        return inside;
    }
    function polyArea(pts) {
        var s = 0;
        for (var i = 0; i < pts.length; i++) { var a = pts[i], b = pts[(i + 1) % pts.length]; s += a.x * b.y - b.x * a.y; }
        return Math.abs(s) / 2;
    }
    function centroid(pts) {
        var x = 0, y = 0; pts.forEach(function (p) { x += p.x; y += p.y; });
        return { x: x / pts.length, y: y / pts.length };
    }
    function rotPt(p, c, deg) {
        var r = deg * Math.PI / 180, cs = Math.cos(r), sn = Math.sin(r);
        var dx = p.x - c.x, dy = p.y - c.y;
        return { x: c.x + dx * cs - dy * sn, y: c.y + dx * sn + dy * cs };
    }

    // ------------------------------------------------------------------ units
    function fmtLen(cm) {
        if (settings.units === 'imperial') {
            var inches = cm / 2.54, ft = Math.floor(inches / 12), inch = Math.round(inches - ft * 12);
            if (inch === 12) { ft += 1; inch = 0; }
            return ft + "' " + inch + '"';
        }
        return cm < 100 ? Math.round(cm) + ' cm' : (cm / 100).toFixed(2) + ' m';
    }
    function fmtArea(cm2) {
        return settings.units === 'imperial' ? (cm2 / 929.0304).toFixed(1) + ' ft²' : (cm2 / 10000).toFixed(2) + ' m²';
    }
    function lenInputUnit() { return settings.units === 'imperial' ? 'in' : 'cm'; }
    function toInputLen(cm) { return settings.units === 'imperial' ? +(cm / 2.54).toFixed(1) : Math.round(cm * 10) / 10; }
    function fromInputLen(v) { var n = parseFloat(v); if (!(n > 0)) return null; return settings.units === 'imperial' ? n * 2.54 : n; }

    // ------------------------------------------------------------------ snapping
    function snap(p, excludeWallId) {
        var tol = 14 / view.s, best = null, bd = tol;
        plan.walls.forEach(function (w) {
            if (w.id === excludeWallId) return;
            [wallA(w), wallB(w)].forEach(function (e) { var d = dist(p, e); if (d < bd) { bd = d; best = { x: e.x, y: e.y }; } });
        });
        if (draft && draft.kind === 'wall') draft.pts.forEach(function (e) { var d = dist(p, e); if (d < bd) { bd = d; best = { x: e.x, y: e.y }; } });
        if (best) return best;
        if (settings.snap) {
            var g = 10;
            return { x: Math.round(p.x / g) * g, y: Math.round(p.y / g) * g };
        }
        return { x: p.x, y: p.y };
    }
    // Keep a drawn segment straight when Shift is held (0/45/90°).
    function constrain(from, p, ev) {
        if (!ev || !ev.shiftKey || !from) return p;
        var dx = p.x - from.x, dy = p.y - from.y, ang = Math.round(Math.atan2(dy, dx) / (Math.PI / 4)) * (Math.PI / 4), l = Math.hypot(dx, dy);
        return { x: from.x + Math.cos(ang) * l, y: from.y + Math.sin(ang) * l };
    }

    // ------------------------------------------------------------------ undo / save
    function record() { undoStack.push(JSON.stringify(plan)); if (undoStack.length > 150) undoStack.shift(); redoStack = []; updateButtons(); }
    function changed() { scheduleSave(); render(); }
    function undo() {
        if (!undoStack.length) return;
        redoStack.push(JSON.stringify(plan)); plan = JSON.parse(undoStack.pop());
        sel = null; draft = null; showProps(); updateButtons(); changed();
    }
    function redo() {
        if (!redoStack.length) return;
        undoStack.push(JSON.stringify(plan)); plan = JSON.parse(redoStack.pop());
        sel = null; draft = null; showProps(); updateButtons(); changed();
    }
    function updateButtons() {
        root.querySelector('[data-cmd=undo]').disabled = !undoStack.length;
        root.querySelector('[data-cmd=redo]').disabled = !redoStack.length;
    }
    function isEmpty() { return !(plan.walls.length || plan.rooms.length || plan.items.length || plan.labels.length || plan.dims.length); }
    var saveState = document.getElementById('save-state');
    function scheduleSave() {
        if (isEmpty() && !savedOnce) { saveState.textContent = 'Not saved'; return; }
        saveState.textContent = 'Saving…';
        clearTimeout(saveTimer); saveTimer = setTimeout(saveNow, 700);
    }
    function saveNow() {
        clearTimeout(saveTimer);
        plan.name = (document.getElementById('plan-name').value || '').trim() || 'Untitled plan';
        var saved = FPStore.save(plan);
        if (!saved) { saveState.textContent = 'Could not save'; return; }
        plan = saved; savedOnce = true;
        if (!/[?&]plan=/.test(location.search)) { try { history.replaceState(null, '', location.pathname + '?plan=' + encodeURIComponent(plan.id)); } catch (e) { /* ignore */ } }
        saveState.textContent = 'Saved · ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    window.addEventListener('beforeunload', function () { if (saveTimer) saveNow(); });

    // ------------------------------------------------------------------ symbols
    function symbolImg(cat, file) {
        var key = cat + '/' + file;
        if (!images[key]) {
            var img = new Image();
            img.onload = function () { render(); };
            img.src = SYMBOL_BASE + key;
            images[key] = img;
        }
        return images[key];
    }

    // ------------------------------------------------------------------ rendering
    function resize() {
        var r = canvas.getBoundingClientRect();
        W = r.width; H = r.height; dpr = window.devicePixelRatio || 1;
        canvas.width = Math.round(W * dpr); canvas.height = Math.round(H * dpr);
        render();
    }

    function drawPlan(c, v, opts) {
        // c: context, v: view {x,y,s}, opts: {grid, selection, dims}
        var S = function (x, y) { return { x: x * v.s + v.x, y: y * v.s + v.y }; };
        var k = opts.k || 1; // text/line scale (bigger for high-resolution exports)
        // grid
        if (opts.grid) {
            var step = 10; while (step * v.s < 8) step *= 5;
            var tl = { x: -v.x / v.s, y: -v.y / v.s }, br = { x: (opts.w - v.x) / v.s, y: (opts.h - v.y) / v.s };
            c.lineWidth = 1;
            for (var gx = Math.floor(tl.x / step) * step; gx <= br.x; gx += step) {
                var major = Math.round(gx) % (step * 10) === 0;
                c.strokeStyle = major ? '#c9d6e3' : '#edf1f5';
                var sx = Math.round(gx * v.s + v.x) + .5; c.beginPath(); c.moveTo(sx, 0); c.lineTo(sx, opts.h); c.stroke();
            }
            for (var gy = Math.floor(tl.y / step) * step; gy <= br.y; gy += step) {
                var majorY = Math.round(gy) % (step * 10) === 0;
                c.strokeStyle = majorY ? '#c9d6e3' : '#edf1f5';
                var sy = Math.round(gy * v.s + v.y) + .5; c.beginPath(); c.moveTo(0, sy); c.lineTo(opts.w, sy); c.stroke();
            }
        }
        // rooms
        plan.rooms.forEach(function (r) {
            if (r.pts.length < 3) return;
            c.beginPath(); r.pts.forEach(function (p, i) { var q = S(p.x, p.y); if (i) c.lineTo(q.x, q.y); else c.moveTo(q.x, q.y); }); c.closePath();
            c.fillStyle = r.color || COLORS[0]; c.fill();
            if (opts.selection && sel && sel.type === 'room' && sel.id === r.id) { c.strokeStyle = '#1e88e5'; c.setLineDash([6, 4]); c.lineWidth = 2; c.stroke(); c.setLineDash([]); }
        });
        // walls
        c.lineCap = 'square';
        plan.walls.forEach(function (w) {
            var a = S(w.x1, w.y1), b = S(w.x2, w.y2);
            c.strokeStyle = '#37474f'; c.lineWidth = Math.max(2, w.t * v.s);
            c.beginPath(); c.moveTo(a.x, a.y); c.lineTo(b.x, b.y); c.stroke();
        });
        c.lineCap = 'butt';
        // openings
        plan.openings.forEach(function (o) {
            var w = plan.walls.find(function (x) { return x.id === o.wall; }); if (!w) return;
            var L = wallLen(w); if (!L) return;
            var ux = (w.x2 - w.x1) / L, uy = (w.y2 - w.y1) / L, nx = -uy, ny = ux;
            var cx = w.x1 + (w.x2 - w.x1) * o.t, cy = w.y1 + (w.y2 - w.y1) * o.t, hw = o.w / 2;
            var p1 = S(cx - ux * hw, cy - uy * hw), p2 = S(cx + ux * hw, cy + uy * hw);
            c.strokeStyle = '#ffffff'; c.lineWidth = Math.max(2, w.t * v.s) + 2;
            c.beginPath(); c.moveTo(p1.x, p1.y); c.lineTo(p2.x, p2.y); c.stroke();
            var isSel = opts.selection && sel && sel.type === 'opening' && sel.id === o.id;
            c.strokeStyle = isSel ? '#1e88e5' : '#37474f'; c.lineWidth = (isSel ? 2 : 1.4) * k;
            if (o.kind === 'window') {
                var off = w.t / 2;
                [-off, 0, off].forEach(function (k) {
                    var q1 = S(cx - ux * hw + nx * k, cy - uy * hw + ny * k), q2 = S(cx + ux * hw + nx * k, cy + uy * hw + ny * k);
                    c.beginPath(); c.moveTo(q1.x, q1.y); c.lineTo(q2.x, q2.y); c.stroke();
                });
            } else {
                var side = o.flip ? -1 : 1, hingeSign = o.hinge ? -1 : 1;
                var hx = cx - ux * hw * hingeSign, hy = cy - uy * hw * hingeSign;
                var leaf = S(hx + nx * o.w * side, hy + ny * o.w * side), hinge = S(hx, hy);
                c.beginPath(); c.moveTo(hinge.x, hinge.y); c.lineTo(leaf.x, leaf.y); c.stroke();
                var start = Math.atan2(ny * side, nx * side), end = Math.atan2(uy * hingeSign, ux * hingeSign);
                var diff = end - start; while (diff > Math.PI) diff -= 2 * Math.PI; while (diff < -Math.PI) diff += 2 * Math.PI;
                c.setLineDash([4 * k, 3 * k]); c.beginPath(); c.arc(hinge.x, hinge.y, o.w * v.s, start, start + diff, diff < 0); c.stroke(); c.setLineDash([]);
            }
        });
        // items
        plan.items.forEach(function (it) {
            var img = symbolImg(it.cat, it.file), ctr = S(it.x, it.y);
            c.save(); c.translate(ctr.x, ctr.y); c.rotate((it.rot || 0) * Math.PI / 180);
            var w = it.w * v.s, h = it.h * v.s;
            if (img.complete && img.naturalWidth) c.drawImage(img, -w / 2, -h / 2, w, h);
            else { c.strokeStyle = '#90a4ae'; c.strokeRect(-w / 2, -h / 2, w, h); }
            if (opts.selection && sel && sel.type === 'item' && sel.id === it.id) {
                c.strokeStyle = '#1e88e5'; c.setLineDash([5, 4]); c.lineWidth = 1.5; c.strokeRect(-w / 2 - 3, -h / 2 - 3, w + 6, h + 6); c.setLineDash([]);
            }
            c.restore();
        });
        // room names + areas
        plan.rooms.forEach(function (r) {
            if (r.pts.length < 3) return;
            var ce = S(centroid(r.pts).x, centroid(r.pts).y);
            var fs = Math.max(10, Math.min(18, 22 * v.s / 0.5)) * k;
            c.textAlign = 'center'; c.textBaseline = 'middle';
            var rxs = r.pts.map(function (q) { return q.x; }), maxW = Math.max(20, (Math.max.apply(null, rxs) - Math.min.apply(null, rxs)) * v.s - 10 * k);
            c.fillStyle = '#0b1b2b'; c.font = '700 ' + fs + 'px Mulish, sans-serif'; c.fillText(r.name || 'Room', ce.x, ce.y - fs * .6, maxW);
            c.fillStyle = '#5f6368'; c.font = '600 ' + (fs * .78) + 'px Mulish, sans-serif'; c.fillText(fmtArea(polyArea(r.pts)), ce.x, ce.y + fs * .6, maxW);
        });
        // wall lengths
        if (opts.dims) {
            c.font = '600 ' + (11 * k) + 'px Mulish, sans-serif'; c.textAlign = 'center'; c.textBaseline = 'middle';
            plan.walls.forEach(function (w) {
                var L = wallLen(w); if (L * v.s < 40 * k) return;
                var ux = (w.x2 - w.x1) / L, uy = (w.y2 - w.y1) / L, nx = -uy, ny = ux;
                var off = w.t / 2 + 16 * k / v.s;
                var m = S((w.x1 + w.x2) / 2 + nx * off, (w.y1 + w.y2) / 2 + ny * off);
                var ang = Math.atan2(uy, ux); if (ang > Math.PI / 2 || ang < -Math.PI / 2) ang += Math.PI;
                c.save(); c.translate(m.x, m.y); c.rotate(ang);
                var txt = fmtLen(L), tw = c.measureText(txt).width + 8 * k;
                c.fillStyle = 'rgba(255,255,255,.85)'; c.fillRect(-tw / 2, -8 * k, tw, 16 * k);
                c.fillStyle = '#1565c0'; c.fillText(txt, 0, 0); c.restore();
            });
        }
        // measurement lines
        plan.dims.forEach(function (d) {
            var a = S(d.x1, d.y1), b = S(d.x2, d.y2);
            var isSel = opts.selection && sel && sel.type === 'dim' && sel.id === d.id;
            drawDimLine(c, a, b, fmtLen(Math.hypot(d.x2 - d.x1, d.y2 - d.y1)), isSel ? '#1e88e5' : '#d32f2f', k);
        });
        // labels
        plan.labels.forEach(function (l) {
            var p = S(l.x, l.y), fs = Math.max(8, l.size * v.s);
            c.font = '700 ' + fs + 'px Mulish, sans-serif'; c.textAlign = 'center'; c.textBaseline = 'middle';
            c.fillStyle = l.color || '#0b1b2b'; c.fillText(l.text, p.x, p.y);
            if (opts.selection && sel && sel.type === 'label' && sel.id === l.id) {
                var tw = c.measureText(l.text).width; c.strokeStyle = '#1e88e5'; c.setLineDash([4, 3]); c.strokeRect(p.x - tw / 2 - 4, p.y - fs / 2 - 3, tw + 8, fs + 6); c.setLineDash([]);
            }
        });
        // selected wall: highlight + endpoint handles
        if (opts.selection && sel && sel.type === 'wall') {
            var w = getWall(sel.id);
            if (w) {
                var a = S(w.x1, w.y1), b = S(w.x2, w.y2);
                c.strokeStyle = 'rgba(30,136,229,.55)'; c.lineWidth = Math.max(4, w.t * v.s) + 6; c.lineCap = 'round';
                c.beginPath(); c.moveTo(a.x, a.y); c.lineTo(b.x, b.y); c.stroke(); c.lineCap = 'butt';
                [a, b].forEach(function (q) { c.fillStyle = '#fff'; c.strokeStyle = '#1e88e5'; c.lineWidth = 2; c.beginPath(); c.arc(q.x, q.y, 7, 0, Math.PI * 2); c.fill(); c.stroke(); });
            }
        }
    }

    function drawDimLine(c, a, b, txt, color, k) {
        k = k || 1;
        c.strokeStyle = color; c.fillStyle = color; c.lineWidth = 1.5 * k;
        c.beginPath(); c.moveTo(a.x, a.y); c.lineTo(b.x, b.y); c.stroke();
        var ang = Math.atan2(b.y - a.y, b.x - a.x), nx = -Math.sin(ang) * 7 * k, ny = Math.cos(ang) * 7 * k;
        [a, b].forEach(function (q) { c.beginPath(); c.moveTo(q.x - nx, q.y - ny); c.lineTo(q.x + nx, q.y + ny); c.stroke(); });
        var m = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 }, ta = ang; if (ta > Math.PI / 2 || ta < -Math.PI / 2) ta += Math.PI;
        c.save(); c.translate(m.x, m.y); c.rotate(ta); c.font = '700 ' + (12 * k) + 'px Mulish, sans-serif'; c.textAlign = 'center'; c.textBaseline = 'bottom';
        var tw = c.measureText(txt).width + 8 * k; c.fillStyle = 'rgba(255,255,255,.9)'; c.fillRect(-tw / 2, -18 * k, tw, 16 * k); c.fillStyle = color; c.fillText(txt, 0, -3 * k); c.restore();
    }

    function render() {
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, W, H);
        drawPlan(ctx, view, { grid: settings.grid, selection: true, dims: settings.dimensions, w: W, h: H });
        drawDraft();
        document.getElementById('zoom-label').textContent = Math.round(view.s / 0.5 * 100) + '%';
    }

    function drawDraft() {
        var c = ctx;
        if (draft && draft.kind === 'wall') {
            c.strokeStyle = '#37474f'; c.lineCap = 'square'; c.lineWidth = Math.max(2, settings.wallThickness * view.s);
            for (var i = 1; i < draft.pts.length; i++) { var a = toScreen(draft.pts[i - 1].x, draft.pts[i - 1].y), b = toScreen(draft.pts[i].x, draft.pts[i].y); c.beginPath(); c.moveTo(a.x, a.y); c.lineTo(b.x, b.y); c.stroke(); }
            c.lineCap = 'butt';
            if (hoverPt && draft.pts.length) {
                var last = draft.pts[draft.pts.length - 1], la = toScreen(last.x, last.y), lb = toScreen(hoverPt.x, hoverPt.y);
                c.strokeStyle = 'rgba(30,136,229,.75)'; c.lineWidth = Math.max(2, settings.wallThickness * view.s);
                c.beginPath(); c.moveTo(la.x, la.y); c.lineTo(lb.x, lb.y); c.stroke();
                drawDimLine(c, { x: la.x, y: la.y - 22 }, { x: lb.x, y: lb.y - 22 }, fmtLen(dist(last, hoverPt)), '#1565c0');
            }
            draft.pts.forEach(function (p, i) { var q = toScreen(p.x, p.y); c.fillStyle = i === 0 ? '#ff9800' : '#1e88e5'; c.beginPath(); c.arc(q.x, q.y, i === 0 ? 7 : 5, 0, Math.PI * 2); c.fill(); });
        }
        if (draft && (draft.kind === 'room' || draft.kind === 'dim') && draft.b) {
            var a1 = toScreen(draft.a.x, draft.a.y), b1 = toScreen(draft.b.x, draft.b.y);
            if (draft.kind === 'room') {
                c.fillStyle = 'rgba(33,150,243,.12)'; c.strokeStyle = '#1e88e5'; c.lineWidth = 2;
                c.fillRect(Math.min(a1.x, b1.x), Math.min(a1.y, b1.y), Math.abs(b1.x - a1.x), Math.abs(b1.y - a1.y));
                c.strokeRect(Math.min(a1.x, b1.x), Math.min(a1.y, b1.y), Math.abs(b1.x - a1.x), Math.abs(b1.y - a1.y));
                var wcm = Math.abs(draft.b.x - draft.a.x), hcm = Math.abs(draft.b.y - draft.a.y);
                c.font = '700 13px Mulish, sans-serif'; c.fillStyle = '#1565c0'; c.textAlign = 'center'; c.textBaseline = 'middle';
                c.fillText(fmtLen(wcm) + ' × ' + fmtLen(hcm), (a1.x + b1.x) / 2, (a1.y + b1.y) / 2);
            } else drawDimLine(c, a1, b1, fmtLen(dist(draft.a, draft.b)), '#d32f2f');
        }
        // opening preview
        if ((tool === 'door' || tool === 'window') && hoverRaw) {
            var hit = nearestWall(hoverRaw, 30 / view.s);
            if (hit) {
                var w = hit.wall, L = wallLen(w), ow = Math.min(tool === 'door' ? 90 : 120, L - 10);
                var ux = (w.x2 - w.x1) / L, uy = (w.y2 - w.y1) / L, cx = w.x1 + (w.x2 - w.x1) * hit.t, cy = w.y1 + (w.y2 - w.y1) * hit.t;
                var p1 = toScreen(cx - ux * ow / 2, cy - uy * ow / 2), p2 = toScreen(cx + ux * ow / 2, cy + uy * ow / 2);
                c.strokeStyle = 'rgba(30,136,229,.8)'; c.lineWidth = Math.max(4, w.t * view.s) + 2;
                c.beginPath(); c.moveTo(p1.x, p1.y); c.lineTo(p2.x, p2.y); c.stroke();
            }
        }
        // library placement preview
        if (tool === 'furniture' && pendingSymbol && hoverPt) {
            var img = symbolImg(pendingSymbol.cat, pendingSymbol.file), ctr = toScreen(hoverPt.x, hoverPt.y), ww = pendingSymbol.w * view.s, hh = pendingSymbol.h * view.s;
            c.globalAlpha = .6; if (img.complete && img.naturalWidth) c.drawImage(img, ctr.x - ww / 2, ctr.y - hh / 2, ww, hh); c.globalAlpha = 1;
        }
        // snap cursor
        if (hoverPt && tool !== 'select' && tool !== 'pan') {
            var hp = toScreen(hoverPt.x, hoverPt.y);
            c.strokeStyle = '#1e88e5'; c.lineWidth = 1.5; c.beginPath(); c.arc(hp.x, hp.y, 5, 0, Math.PI * 2); c.stroke();
        }
    }

    // ------------------------------------------------------------------ lookup / hit testing
    function getWall(id) { return plan.walls.find(function (w) { return w.id === id; }); }
    function find(type, id) {
        var list = { wall: plan.walls, room: plan.rooms, opening: plan.openings, item: plan.items, label: plan.labels, dim: plan.dims }[type];
        return list ? list.find(function (x) { return x.id === id; }) : null;
    }
    function nearestWall(p, maxD) {
        var best = null;
        plan.walls.forEach(function (w) {
            var d = distToSeg(p, wallA(w), wallB(w));
            if (d <= maxD + w.t / 2 && (!best || d < best.d)) best = { wall: w, d: d, t: projT(p, wallA(w), wallB(w)) };
        });
        return best;
    }
    function hitTest(p) {
        var tol = 8 / view.s, i;
        for (i = plan.labels.length - 1; i >= 0; i--) {
            var l = plan.labels[i]; ctx.font = '700 ' + (l.size * view.s) + 'px Mulish, sans-serif';
            var tw = ctx.measureText(l.text).width / view.s;
            if (Math.abs(p.x - l.x) <= tw / 2 + tol && Math.abs(p.y - l.y) <= l.size / 2 + tol) return { type: 'label', id: l.id };
        }
        for (i = plan.items.length - 1; i >= 0; i--) {
            var it = plan.items[i], q = rotPt(p, { x: it.x, y: it.y }, -(it.rot || 0));
            if (Math.abs(q.x - it.x) <= it.w / 2 && Math.abs(q.y - it.y) <= it.h / 2) return { type: 'item', id: it.id };
        }
        for (i = plan.dims.length - 1; i >= 0; i--) {
            var d = plan.dims[i]; if (distToSeg(p, { x: d.x1, y: d.y1 }, { x: d.x2, y: d.y2 }) <= tol) return { type: 'dim', id: d.id };
        }
        for (i = plan.openings.length - 1; i >= 0; i--) {
            var o = plan.openings[i], w = getWall(o.wall); if (!w) continue;
            var t = projT(p, wallA(w), wallB(w)), L = wallLen(w);
            if (distToSeg(p, wallA(w), wallB(w)) <= w.t / 2 + tol && Math.abs((t - o.t) * L) <= o.w / 2) return { type: 'opening', id: o.id };
        }
        var nw = nearestWall(p, tol); if (nw) return { type: 'wall', id: nw.wall.id };
        for (i = plan.rooms.length - 1; i >= 0; i--) if (pointInPoly(p, plan.rooms[i].pts)) return { type: 'room', id: plan.rooms[i].id };
        return null;
    }

    // ------------------------------------------------------------------ creation
    function addWall(a, b) {
        if (dist(a, b) < 1) return null;
        var w = { id: uid('w'), x1: a.x, y1: a.y, x2: b.x, y2: b.y, t: settings.wallThickness };
        plan.walls.push(w); return w;
    }
    function nextRoomName() { return 'Room ' + (plan.rooms.length + 1); }
    function finishWallDraft(closeLoop) {
        if (!draft || draft.kind !== 'wall') return;
        if (closeLoop && draft.pts.length >= 3) {
            plan.rooms.push({ id: uid('r'), pts: draft.pts.map(function (p) { return { x: p.x, y: p.y }; }), name: nextRoomName(), color: COLORS[plan.rooms.length % COLORS.length] });
        }
        draft = null; changed();
    }
    function deleteSelection() {
        if (!sel) return;
        record();
        if (sel.type === 'wall') { plan.walls = plan.walls.filter(function (w) { return w.id !== sel.id; }); plan.openings = plan.openings.filter(function (o) { return o.wall !== sel.id; }); }
        else {
            var key = { room: 'rooms', opening: 'openings', item: 'items', label: 'labels', dim: 'dims' }[sel.type];
            plan[key] = plan[key].filter(function (x) { return x.id !== sel.id; });
        }
        sel = null; showProps(); changed();
    }

    // ------------------------------------------------------------------ moving
    function moveEndpoint(oldP, newP) {
        // Move every wall end / room corner sitting on oldP so joints stay connected.
        plan.walls.forEach(function (w) {
            if (Math.abs(w.x1 - oldP.x) < .5 && Math.abs(w.y1 - oldP.y) < .5) { w.x1 = newP.x; w.y1 = newP.y; }
            if (Math.abs(w.x2 - oldP.x) < .5 && Math.abs(w.y2 - oldP.y) < .5) { w.x2 = newP.x; w.y2 = newP.y; }
        });
        plan.rooms.forEach(function (r) { r.pts.forEach(function (q) { if (Math.abs(q.x - oldP.x) < .5 && Math.abs(q.y - oldP.y) < .5) { q.x = newP.x; q.y = newP.y; } }); });
    }
    function startDrag(p, hit) {
        var obj = find(hit.type, hit.id); if (!obj) return;
        record();
        if (hit.type === 'wall') {
            var w = obj, endTol = 12 / view.s;
            if (dist(p, wallA(w)) < endTol) { drag = { kind: 'endpoint', last: wallA(w), wall: w.id }; return; }
            if (dist(p, wallB(w)) < endTol) { drag = { kind: 'endpoint', last: wallB(w), wall: w.id }; return; }
            drag = { kind: 'wall', start: p, a: wallA(w), b: wallB(w), wall: w.id, lastA: wallA(w), lastB: wallB(w) };
        } else if (hit.type === 'room') {
            drag = { kind: 'room', start: p, orig: obj.pts.map(function (q) { return { x: q.x, y: q.y }; }), room: obj.id };
        } else if (hit.type === 'opening') {
            drag = { kind: 'opening', id: obj.id };
        } else if (hit.type === 'dim') {
            drag = { kind: 'dim', start: p, orig: { x1: obj.x1, y1: obj.y1, x2: obj.x2, y2: obj.y2 }, id: obj.id };
        } else {
            drag = { kind: 'point', start: p, orig: { x: obj.x, y: obj.y }, type: hit.type, id: obj.id };
        }
        drag.moved = false;
    }
    function doDrag(p, ev) {
        if (!drag) return;
        drag.moved = true;
        var g = function (v) { return settings.snap ? Math.round(v / 10) * 10 : v; };
        if (drag.kind === 'endpoint') {
            var np = snap(p, drag.wall); moveEndpoint(drag.last, np); drag.last = np;
        } else if (drag.kind === 'wall') {
            var dx = g(p.x - drag.start.x), dy = g(p.y - drag.start.y);
            var na = { x: drag.a.x + dx, y: drag.a.y + dy }, nb = { x: drag.b.x + dx, y: drag.b.y + dy };
            moveEndpoint(drag.lastA, na); moveEndpoint(drag.lastB, nb); drag.lastA = na; drag.lastB = nb;
        } else if (drag.kind === 'room') {
            var r = find('room', drag.room), ddx = g(p.x - drag.start.x), ddy = g(p.y - drag.start.y);
            r.pts.forEach(function (q, i) { q.x = drag.orig[i].x + ddx; q.y = drag.orig[i].y + ddy; });
        } else if (drag.kind === 'opening') {
            var o = find('opening', drag.id), w = getWall(o.wall);
            if (w) { var L = wallLen(w), half = o.w / 2 / L; o.t = Math.max(half, Math.min(1 - half, projT(p, wallA(w), wallB(w)))); }
        } else if (drag.kind === 'dim') {
            var d = find('dim', drag.id), mx = g(p.x - drag.start.x), my = g(p.y - drag.start.y);
            d.x1 = drag.orig.x1 + mx; d.y1 = drag.orig.y1 + my; d.x2 = drag.orig.x2 + mx; d.y2 = drag.orig.y2 + my;
        } else if (drag.kind === 'point') {
            var obj = find(drag.type, drag.id);
            obj.x = drag.orig.x + g(p.x - drag.start.x); obj.y = drag.orig.y + g(p.y - drag.start.y);
        }
        render();
    }
    function endDrag() {
        if (!drag) return;
        if (!drag.moved) undoStack.pop(); else changed();
        drag = null; updateButtons(); showProps();
    }

    // ------------------------------------------------------------------ pointer input
    function evPoint(ev) { var r = canvas.getBoundingClientRect(); return { x: ev.clientX - r.left, y: ev.clientY - r.top }; }
    var lastClick = 0;
    canvas.addEventListener('pointerdown', function (ev) {
        canvas.focus();
        canvas.setPointerCapture(ev.pointerId);
        pointers.set(ev.pointerId, evPoint(ev));
        if (pointers.size === 2) { // start pinch
            var pts = Array.from(pointers.values());
            pinch = { d: dist(pts[0], pts[1]), mid: { x: (pts[0].x + pts[1].x) / 2, y: (pts[0].y + pts[1].y) / 2 }, s: view.s, vx: view.x, vy: view.y };
            draft = draft && draft.kind === 'wall' ? draft : null; drag = null; return;
        }
        var sp = evPoint(ev), wp = toWorld(sp.x, sp.y);
        var panning = tool === 'pan' || ev.button === 1 || ev.button === 2 || spaceDown;
        if (panning) { drag = { kind: 'pan', sx: sp.x, sy: sp.y, vx: view.x, vy: view.y }; canvas.classList.add('panning'); return; }
        if (ev.button !== 0) return;
        var now = Date.now(), dbl = now - lastClick < 320; lastClick = now;

        if (tool === 'select') {
            var hit = hitTest(wp);
            sel = hit; showProps();
            if (hit) startDrag(wp, hit);
            else drag = { kind: 'pan', sx: sp.x, sy: sp.y, vx: view.x, vy: view.y };
            render(); return;
        }
        if (tool === 'wall') {
            var p = snap(wp);
            if (draft && draft.pts.length) p = constrain(draft.pts[draft.pts.length - 1], p, ev);
            if (!draft) { draft = { kind: 'wall', pts: [p] }; render(); return; }
            var first = draft.pts[0], last = draft.pts[draft.pts.length - 1];
            if (dbl && dist(p, last) < 1) { finishWallDraft(false); return; }
            if (dist(p, last) < 1) return;
            record(); addWall(last, p);
            if (draft.pts.length >= 2 && dist(p, first) < 1) { finishWallDraft(true); return; }
            draft.pts.push(p); changed(); return;
        }
        if (tool === 'room' || tool === 'dim') { var a = snap(wp); draft = { kind: tool, a: a, b: a }; return; }
        if (tool === 'door' || tool === 'window') {
            var nw = nearestWall(wp, 30 / view.s);
            if (!nw) { flashHint('Click on a wall to add a ' + tool + '.'); return; }
            var L = wallLen(nw.wall), ow = Math.min(tool === 'door' ? 90 : 120, L - 10);
            if (ow < 20) { flashHint('That wall is too short.'); return; }
            record();
            var half = ow / 2 / L, o = { id: uid('o'), kind: tool, wall: nw.wall.id, t: Math.max(half, Math.min(1 - half, nw.t)), w: ow, flip: false, hinge: false };
            plan.openings.push(o); sel = { type: 'opening', id: o.id }; showProps(); changed(); return;
        }
        if (tool === 'furniture') {
            if (!pendingSymbol) { openLibrary(); return; }
            record();
            var ps = snap(wp), it = { id: uid('i'), cat: pendingSymbol.cat, file: pendingSymbol.file, label: pendingSymbol.label, x: ps.x, y: ps.y, w: pendingSymbol.w, h: pendingSymbol.h, rot: 0 };
            plan.items.push(it); sel = { type: 'item', id: it.id };
            setTool('select'); showProps(); changed(); return;
        }
        if (tool === 'label') {
            var text = prompt('Label text', 'Living room');
            if (text && text.trim()) {
                record(); var lp = snap(wp), lb = { id: uid('l'), x: lp.x, y: lp.y, text: text.trim().slice(0, 80), size: 30, color: '#0b1b2b' };
                plan.labels.push(lb); sel = { type: 'label', id: lb.id }; setTool('select'); showProps(); changed();
            }
        }
    });
    canvas.addEventListener('pointermove', function (ev) {
        if (pointers.has(ev.pointerId)) pointers.set(ev.pointerId, evPoint(ev));
        if (pinch && pointers.size === 2) {
            var pts = Array.from(pointers.values()), d = dist(pts[0], pts[1]), mid = { x: (pts[0].x + pts[1].x) / 2, y: (pts[0].y + pts[1].y) / 2 };
            var ns = clampScale(pinch.s * d / pinch.d), wx = (pinch.mid.x - pinch.vx) / pinch.s, wy = (pinch.mid.y - pinch.vy) / pinch.s;
            view.s = ns; view.x = mid.x - wx * ns; view.y = mid.y - wy * ns; render(); return;
        }
        var sp = evPoint(ev), wp = toWorld(sp.x, sp.y);
        hoverRaw = wp; hoverPt = snap(wp);
        if (draft && draft.kind === 'wall' && draft.pts.length) hoverPt = constrain(draft.pts[draft.pts.length - 1], hoverPt, ev);
        setStatus(wp);
        if (drag && drag.kind === 'pan') { view.x = drag.vx + sp.x - drag.sx; view.y = drag.vy + sp.y - drag.sy; render(); return; }
        if (drag) { doDrag(wp, ev); return; }
        if (draft && (draft.kind === 'room' || draft.kind === 'dim')) { draft.b = draft.kind === 'dim' ? constrain(draft.a, snap(wp), ev) : snap(wp); }
        if (tool !== 'select' || draft) render();
    });
    function pointerEnd(ev) {
        pointers.delete(ev.pointerId);
        if (draft && (draft.kind === 'room' || draft.kind === 'dim') && ev.type === 'pointerup') {
            var up = evPoint(ev), wp = toWorld(up.x, up.y);
            draft.b = draft.kind === 'dim' ? constrain(draft.a, snap(wp), ev) : snap(wp);
        }
        if (pointers.size < 2) pinch = null;
        canvas.classList.remove('panning');
        if (drag && drag.kind === 'pan') { drag = null; return; }
        if (drag) { endDrag(); return; }
        if (draft && draft.kind === 'room' && draft.b) {
            var a = draft.a, b = draft.b;
            if (Math.abs(b.x - a.x) >= 20 && Math.abs(b.y - a.y) >= 20) {
                record();
                var pts = [{ x: a.x, y: a.y }, { x: b.x, y: a.y }, { x: b.x, y: b.y }, { x: a.x, y: b.y }];
                for (var i = 0; i < 4; i++) addWall(pts[i], pts[(i + 1) % 4]);
                var r = { id: uid('r'), pts: pts, name: nextRoomName(), color: COLORS[plan.rooms.length % COLORS.length] };
                plan.rooms.push(r); sel = { type: 'room', id: r.id }; showProps();
            }
            draft = null; changed(); return;
        }
        if (draft && draft.kind === 'dim' && draft.b) {
            if (dist(draft.a, draft.b) >= 5) { record(); var d = { id: uid('d'), x1: draft.a.x, y1: draft.a.y, x2: draft.b.x, y2: draft.b.y }; plan.dims.push(d); sel = { type: 'dim', id: d.id }; showProps(); }
            draft = null; changed();
        }
    }
    canvas.addEventListener('pointerup', pointerEnd);
    canvas.addEventListener('pointercancel', pointerEnd);
    canvas.addEventListener('pointerleave', function () { if (!drag) { hoverPt = null; hoverRaw = null; render(); } });
    canvas.addEventListener('dblclick', function (ev) { if (tool === 'wall' && draft) { ev.preventDefault(); finishWallDraft(false); } });
    canvas.addEventListener('contextmenu', function (ev) { ev.preventDefault(); });
    canvas.addEventListener('wheel', function (ev) {
        ev.preventDefault();
        var sp = evPoint(ev);
        if (ev.ctrlKey || ev.metaKey || Math.abs(ev.deltaY) >= Math.abs(ev.deltaX)) {
            zoomAt(sp, Math.exp(-ev.deltaY * (ev.ctrlKey ? 0.01 : 0.0015)));
        } else { view.x -= ev.deltaX; view.y -= ev.deltaY; render(); }
    }, { passive: false });

    function clampScale(s) { return Math.max(0.03, Math.min(6, s)); }
    function zoomAt(sp, factor) {
        var ns = clampScale(view.s * factor), w = toWorld(sp.x, sp.y);
        view.s = ns; view.x = sp.x - w.x * ns; view.y = sp.y - w.y * ns; render();
    }
    function bounds() {
        var xs = [], ys = [];
        plan.walls.forEach(function (w) { xs.push(w.x1, w.x2); ys.push(w.y1, w.y2); });
        plan.rooms.forEach(function (r) { r.pts.forEach(function (p) { xs.push(p.x); ys.push(p.y); }); });
        plan.items.forEach(function (i) { var m = Math.max(i.w, i.h) / 2; xs.push(i.x - m, i.x + m); ys.push(i.y - m, i.y + m); });
        plan.labels.forEach(function (l) { xs.push(l.x - 60, l.x + 60); ys.push(l.y - 20, l.y + 20); });
        plan.dims.forEach(function (d) { xs.push(d.x1, d.x2); ys.push(d.y1, d.y2); });
        if (!xs.length) return null;
        return { x0: Math.min.apply(null, xs), y0: Math.min.apply(null, ys), x1: Math.max.apply(null, xs), y1: Math.max.apply(null, ys) };
    }
    function fit() {
        var b = bounds();
        if (!b) { view.s = 0.5; view.x = W / 2; view.y = H / 2; render(); return; }
        var pad = 80, bw = Math.max(100, b.x1 - b.x0), bh = Math.max(100, b.y1 - b.y0);
        view.s = clampScale(Math.min((W - pad * 2) / bw, (H - pad * 2) / bh));
        view.x = W / 2 - (b.x0 + bw / 2) * view.s; view.y = H / 2 - (b.y0 + bh / 2) * view.s;
        render();
    }

    // ------------------------------------------------------------------ keyboard
    var keyTools = { v: 'select', w: 'wall', r: 'room', d: 'door', n: 'window', f: 'furniture', t: 'label', m: 'dim', h: 'pan' };
    document.addEventListener('keydown', function (ev) {
        var typing = /INPUT|TEXTAREA|SELECT/.test((document.activeElement || {}).tagName || '');
        var mod = ev.ctrlKey || ev.metaKey;
        if (mod && ev.key.toLowerCase() === 's') { ev.preventDefault(); saveNow(); return; }
        if (typing) return;
        if (mod && ev.key.toLowerCase() === 'z') { ev.preventDefault(); if (ev.shiftKey) redo(); else undo(); return; }
        if (mod && ev.key.toLowerCase() === 'y') { ev.preventDefault(); redo(); return; }
        if (ev.key === ' ') { spaceDown = true; canvas.classList.add('panning'); ev.preventDefault(); return; }
        if (ev.key === 'Escape') { if (draft) { draft = null; render(); } else { sel = null; showProps(); render(); } closeMenus(); return; }
        if (ev.key === 'Enter' && draft && draft.kind === 'wall') { finishWallDraft(false); return; }
        if ((ev.key === 'Delete' || ev.key === 'Backspace') && sel) { ev.preventDefault(); deleteSelection(); return; }
        if ((ev.key === '[' || ev.key === ']') && sel && sel.type === 'item') { record(); var it = find('item', sel.id); it.rot = ((it.rot || 0) + (ev.key === ']' ? 90 : -90) + 360) % 360; showProps(); changed(); return; }
        if (!mod && keyTools[ev.key.toLowerCase()]) setTool(keyTools[ev.key.toLowerCase()]);
        if (ev.key === '+' || ev.key === '=') zoomAt({ x: W / 2, y: H / 2 }, 1.2);
        if (ev.key === '-') zoomAt({ x: W / 2, y: H / 2 }, 1 / 1.2);
    });
    document.addEventListener('keyup', function (ev) { if (ev.key === ' ') { spaceDown = false; canvas.classList.remove('panning'); } });

    // ------------------------------------------------------------------ tools UI
    var HINTS = {
        select: 'Click to select. Drag to move — drag a wall end to stretch it. Drag empty space to pan. Del deletes.',
        wall: 'Click to place corners (hold Shift for straight lines). Double-click or Enter to finish; click the first corner to close a room.',
        room: 'Drag to draw a rectangular room with four walls.',
        door: 'Click on a wall to add a door.',
        window: 'Click on a wall to add a window.',
        furniture: 'Pick an item in the library, then click on the plan to place it.',
        label: 'Click where the label should go.',
        dim: 'Drag to measure a distance.',
        pan: 'Drag to move around. Scroll or pinch to zoom.'
    };
    var hintEl = document.getElementById('hint'), hintTimer;
    function flashHint(text) { hintEl.textContent = text; hintEl.hidden = false; clearTimeout(hintTimer); hintTimer = setTimeout(function () { hintEl.hidden = true; }, 4500); }
    function setTool(t) {
        if (draft && t !== tool) { if (draft.kind === 'wall') finishWallDraft(false); draft = null; }
        tool = t;
        root.querySelectorAll('[data-tool]').forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-tool') === t); });
        canvas.className = 'tool-' + t;
        flashHint(HINTS[t]);
        if (t === 'furniture') openLibrary(); else if (!pendingSymbol) document.getElementById('library').hidden = true;
        if (t !== 'furniture') pendingSymbol = null;
        render();
    }
    root.querySelectorAll('[data-tool]').forEach(function (b) { b.addEventListener('click', function () { setTool(b.getAttribute('data-tool')); }); });
    function setStatus(wp) {
        document.getElementById('status').textContent = 'X ' + fmtLen(Math.abs(wp.x)).replace(/^/, wp.x < 0 ? '−' : '') + '   Y ' + fmtLen(Math.abs(wp.y)).replace(/^/, wp.y < 0 ? '−' : '') + '   ·   ' + tool.charAt(0).toUpperCase() + tool.slice(1);
    }

    // ------------------------------------------------------------------ library
    var libCats = document.getElementById('lib-cats'), libGrid = document.getElementById('lib-grid'), libSearch = document.getElementById('lib-search');
    var activeCat = LIBRARY.length ? LIBRARY[0].id : null;
    function renderLibrary() {
        var q = libSearch.value.trim().toLowerCase();
        libCats.innerHTML = LIBRARY.map(function (c) { return '<button type="button" data-cat="' + c.id + '" class="' + (c.id === activeCat && !q ? 'is-active' : '') + '">' + FP.esc(c.name) + '</button>'; }).join('');
        var items = [];
        LIBRARY.forEach(function (c) {
            if (!q && c.id !== activeCat) return;
            c.items.forEach(function (it) { if (!q || it.label.toLowerCase().indexOf(q) !== -1 || c.name.toLowerCase().indexOf(q) !== -1) items.push({ cat: c.id, it: it }); });
        });
        libGrid.innerHTML = items.slice(0, 200).map(function (x) {
            var active = pendingSymbol && pendingSymbol.cat === x.cat && pendingSymbol.file === x.it.file;
            return '<button type="button" class="ed-lib-item' + (active ? ' is-active' : '') + '" data-cat="' + x.cat + '" data-file="' + FP.esc(x.it.file) + '"><img loading="lazy" alt="" src="' + SYMBOL_BASE + x.cat + '/' + encodeURIComponent(x.it.file) + '">' + FP.esc(x.it.label) + '</button>';
        }).join('') || '<p>No matches.</p>';
    }
    libCats.addEventListener('click', function (ev) { var b = ev.target.closest('[data-cat]'); if (!b) return; activeCat = b.getAttribute('data-cat'); libSearch.value = ''; renderLibrary(); });
    libSearch.addEventListener('input', renderLibrary);
    libGrid.addEventListener('click', function (ev) {
        var b = ev.target.closest('.ed-lib-item'); if (!b) return;
        var cat = b.getAttribute('data-cat'), file = b.getAttribute('data-file');
        var c = LIBRARY.find(function (x) { return x.id === cat; }), it = c.items.find(function (x) { return x.file === file; });
        // Library sizes come from the mobile app's symbol scale; ×2.5 gives real-world centimetres (a bed is ~200 × 125 cm).
        pendingSymbol = { cat: cat, file: file, label: it.label, w: Math.round(it.w * SYMBOL_SCALE), h: Math.round(it.h * SYMBOL_SCALE) };
        if (tool !== 'furniture') { tool = 'furniture'; root.querySelectorAll('[data-tool]').forEach(function (x) { x.classList.toggle('is-active', x.getAttribute('data-tool') === 'furniture'); }); }
        flashHint('Click on the plan to place “' + it.label + '”.');
        if (window.matchMedia('(max-width: 760px)').matches) document.getElementById('library').hidden = true;
        renderLibrary();
    });
    function openLibrary() { document.getElementById('library').hidden = false; document.getElementById('props').hidden = true; renderLibrary(); }

    // ------------------------------------------------------------------ properties panel
    var props = document.getElementById('props'), propsBody = document.getElementById('props-body'), propsTitle = document.getElementById('props-title');
    function field(label, html) { return '<div class="ed-row"><label>' + label + '</label>' + html + '</div>'; }
    function num(name, value, step) { return '<input type="number" step="' + (step || 'any') + '" min="0" name="' + name + '" value="' + value + '">'; }
    function showProps() {
        var obj = sel && find(sel.type, sel.id);
        if (!obj) { props.hidden = true; return; }
        document.getElementById('library').hidden = true;
        var u = lenInputUnit(), html = '';
        if (sel.type === 'wall') {
            propsTitle.textContent = 'Wall';
            html = '<div class="ed-row-2">' + field('Length (' + u + ')', num('length', toInputLen(wallLen(obj)))) + field('Thickness (cm)', num('t', obj.t, 1)) + '</div>' +
                '<p style="font-size:12.5px">Drag the round handles on the plan to move a wall end; connected walls follow.</p>';
        } else if (sel.type === 'room') {
            propsTitle.textContent = 'Room';
            html = field('Name', '<input name="name" maxlength="40" value="' + FP.esc(obj.name || '') + '">') +
                field('Area', '<div style="font:400 1.4rem var(--serif);color:var(--navy)">' + fmtArea(polyArea(obj.pts)) + '</div>') +
                field('Floor colour', '<div class="ed-swatches">' + COLORS.map(function (c) { return '<button type="button" data-color="' + c + '" style="background:' + c + '" class="' + (obj.color === c ? 'is-active' : '') + '" aria-label="Colour ' + c + '"></button>'; }).join('') + '</div>');
        } else if (sel.type === 'opening') {
            propsTitle.textContent = obj.kind === 'door' ? 'Door' : 'Window';
            html = field('Width (' + u + ')', num('w', toInputLen(obj.w))) +
                (obj.kind === 'door' ? '<div class="ed-actions-row" style="margin-top:0"><button type="button" class="ed-small-btn" data-act="flip">Flip side</button><button type="button" class="ed-small-btn" data-act="hinge">Swap hinge</button></div>' : '');
        } else if (sel.type === 'item') {
            propsTitle.textContent = obj.label || 'Item';
            html = '<div class="ed-row-2">' + field('Width (' + u + ')', num('w', toInputLen(obj.w))) + field('Depth (' + u + ')', num('h', toInputLen(obj.h))) + '</div>' +
                field('Rotation (°)', num('rot', obj.rot || 0, 1)) +
                '<div class="ed-actions-row" style="margin-top:0"><button type="button" class="ed-small-btn" data-act="rotl">Rotate left</button><button type="button" class="ed-small-btn" data-act="rotr">Rotate right</button><button type="button" class="ed-small-btn" data-act="dup">Duplicate</button></div>';
        } else if (sel.type === 'label') {
            propsTitle.textContent = 'Label';
            html = field('Text', '<input name="text" maxlength="80" value="' + FP.esc(obj.text) + '">') + field('Size (cm)', num('size', obj.size, 1));
        } else if (sel.type === 'dim') {
            propsTitle.textContent = 'Measurement';
            html = field('Length', '<div style="font:400 1.4rem var(--serif);color:var(--navy)">' + fmtLen(Math.hypot(obj.x2 - obj.x1, obj.y2 - obj.y1)) + '</div>');
        }
        html += '<div class="ed-actions-row"><button type="button" class="ed-small-btn danger" data-act="delete">Delete</button></div>';
        propsBody.innerHTML = html; props.hidden = false;
    }
    propsBody.addEventListener('change', function (ev) {
        var obj = sel && find(sel.type, sel.id), el = ev.target; if (!obj || !el.name) return;
        record();
        if (sel.type === 'wall' && el.name === 'length') {
            var L = fromInputLen(el.value), cur = wallLen(obj);
            if (L && cur) { var oldB = wallB(obj); moveEndpoint(oldB, { x: obj.x1 + (obj.x2 - obj.x1) / cur * L, y: obj.y1 + (obj.y2 - obj.y1) / cur * L }); }
        } else if (sel.type === 'wall' && el.name === 't') { var t = parseFloat(el.value); if (t >= 2 && t <= 100) obj.t = t; }
        else if (el.name === 'name' || el.name === 'text') { var v = el.value.trim(); if (v) obj[el.name] = v; }
        else if (el.name === 'rot') { obj.rot = ((parseFloat(el.value) || 0) % 360 + 360) % 360; }
        else if (el.name === 'size') { var s = parseFloat(el.value); if (s >= 5 && s <= 300) obj.size = s; }
        else if (el.name === 'w' || el.name === 'h') {
            var val = fromInputLen(el.value);
            if (val) {
                if (sel.type === 'opening') { var ww = getWall(obj.wall); obj.w = Math.min(val, ww ? wallLen(ww) - 4 : val); }
                else obj[el.name] = val;
            }
        }
        showProps(); changed();
    });
    propsBody.addEventListener('click', function (ev) {
        var obj = sel && find(sel.type, sel.id); if (!obj) return;
        var sw = ev.target.closest('[data-color]');
        if (sw) { record(); obj.color = sw.getAttribute('data-color'); showProps(); changed(); return; }
        var b = ev.target.closest('[data-act]'); if (!b) return;
        var a = b.getAttribute('data-act');
        if (a === 'delete') { deleteSelection(); return; }
        record();
        if (a === 'flip') obj.flip = !obj.flip;
        if (a === 'hinge') obj.hinge = !obj.hinge;
        if (a === 'rotl') obj.rot = ((obj.rot || 0) + 270) % 360;
        if (a === 'rotr') obj.rot = ((obj.rot || 0) + 90) % 360;
        if (a === 'dup') { var copy = JSON.parse(JSON.stringify(obj)); copy.id = uid('i'); copy.x += 30; copy.y += 30; plan.items.push(copy); sel = { type: 'item', id: copy.id }; }
        showProps(); changed();
    });
    root.querySelectorAll('[data-close]').forEach(function (b) {
        b.addEventListener('click', function () {
            var id = b.getAttribute('data-close'); document.getElementById(id).hidden = true;
            if (id === 'props') { sel = null; render(); }
            if (id === 'library' && tool === 'furniture' && !pendingSymbol) setTool('select');
        });
    });

    // ------------------------------------------------------------------ export
    function renderExport(maxPx, title) {
        var b = bounds(); if (!b) return null;
        var margin = 80, bw = b.x1 - b.x0 + margin * 2, bh = b.y1 - b.y0 + margin * 2;
        var s = Math.min(maxPx / bw, maxPx / bh, 4), k = Math.max(1, Math.max(bw, bh) * s / 1100), titleH = title ? Math.round(90 * k) : 0;
        var c = document.createElement('canvas'); c.width = Math.round(bw * s); c.height = Math.round(bh * s) + titleH;
        var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height);
        var v = { s: s, x: (margin - b.x0) * s, y: (margin - b.y0) * s + titleH };
        drawPlan(x, v, { grid: false, selection: false, dims: true, w: c.width, h: c.height, k: k });
        if (title) {
            x.fillStyle = '#001d36'; x.font = '400 ' + Math.round(titleH * .42) + 'px Tinos, serif'; x.textBaseline = 'middle'; x.textAlign = 'left';
            x.fillText(plan.name || 'Floor plan', 30 * k, titleH * .42);
            x.fillStyle = '#5f6368'; x.font = '600 ' + Math.round(titleH * .2) + 'px Mulish, sans-serif';
            x.fillText('Floor Plan Designer · ' + new Date().toLocaleDateString(), 30 * k, titleH * .78);
            // scale bar (1 m or 3 ft)
            var unitCm = settings.units === 'imperial' ? 91.44 : 100, len = unitCm * s, bx = c.width - 30 * k - len, by = titleH * .55;
            x.fillStyle = '#001d36'; x.fillRect(bx, by, len, 6 * k); x.textAlign = 'center'; x.font = '600 ' + Math.round(titleH * .18) + 'px Mulish, sans-serif';
            x.fillText(settings.units === 'imperial' ? "3'" : '1 m', bx + len / 2, by - 14 * k);
            x.fillStyle = '#d9dce0'; x.fillRect(0, titleH - 2 * k, c.width, 2 * k);
        }
        return c;
    }
    function fileBase() { return (plan.name || 'floor-plan').replace(/[^\w\- ]+/g, '_'); }
    function exportPng() {
        var c = renderExport(3000, true);
        if (!c) { flashHint('Draw something first — the plan is empty.'); return; }
        c.toBlob(function (blob) { FP.downloadBlob(blob, fileBase() + '.png'); }, 'image/png');
    }
    // Minimal single-page PDF (A4 landscape) with the plan as a JPEG image.
    function exportPdf() {
        var c = renderExport(2400, true);
        if (!c) { flashHint('Draw something first — the plan is empty.'); return; }
        var jpg = atob(c.toDataURL('image/jpeg', 0.92).split(',')[1]);
        var pw = 842, ph = 595, m = 28, scale = Math.min((pw - 2 * m) / c.width, (ph - 2 * m) / c.height);
        var iw = c.width * scale, ih = c.height * scale, ix = (pw - iw) / 2, iy = (ph - ih) / 2;
        var content = 'q ' + iw.toFixed(2) + ' 0 0 ' + ih.toFixed(2) + ' ' + ix.toFixed(2) + ' ' + iy.toFixed(2) + ' cm /Im0 Do Q';
        var parts = [], offsets = [], length = 0;
        function add(str) { parts.push(str); length += str.length; }
        add('%PDF-1.4\n%\xE2\xE3\xCF\xD3\n');
        var objs = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + pw + ' ' + ph + '] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>',
            null,
            '<< /Length ' + content.length + ' >>\nstream\n' + content + '\nendstream'
        ];
        objs.forEach(function (o, i) {
            offsets.push(length);
            if (i === 3) add('4 0 obj\n<< /Type /XObject /Subtype /Image /Width ' + c.width + ' /Height ' + c.height + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + jpg.length + ' >>\nstream\n' + jpg + '\nendstream\nendobj\n');
            else add((i + 1) + ' 0 obj\n' + o + '\nendobj\n');
        });
        var xref = length;
        add('xref\n0 6\n0000000000 65535 f \n' + offsets.map(function (o) { return String(o).padStart(10, '0') + ' 00000 n \n'; }).join(''));
        add('trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF');
        var all = parts.join(''), bytes = new Uint8Array(all.length);
        for (var i = 0; i < all.length; i++) bytes[i] = all.charCodeAt(i) & 0xff;
        FP.downloadBlob(new Blob([bytes], { type: 'application/pdf' }), fileBase() + '.pdf');
    }
    function closeMenus() { document.getElementById('export-menu').hidden = true; }

    // ------------------------------------------------------------------ commands
    root.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-cmd]'); if (!b) return;
        var cmd = b.getAttribute('data-cmd');
        if (cmd !== 'export') closeMenus();
        if (cmd === 'undo') undo();
        else if (cmd === 'redo') redo();
        else if (cmd === 'fit') fit();
        else if (cmd === 'save') { if (isEmpty() && !savedOnce) flashHint('Draw something first — the plan is empty.'); else saveNow(); }
        else if (cmd === 'export') { var mnu = document.getElementById('export-menu'); mnu.hidden = !mnu.hidden; }
        else if (cmd === 'view3d') {
            if (!plan.walls.length && !plan.rooms.length) { flashHint('Draw some walls or rooms first, then open the 3D view.'); return; }
            saveNow(); location.href = FP.url('/app/3d?plan=' + encodeURIComponent(plan.id));
        }
        else if (cmd === 'export-png') exportPng();
        else if (cmd === 'export-pdf') exportPdf();
        else if (cmd === 'export-json') { if (savedOnce || !isEmpty()) { saveNow(); FPStore.download(plan.id); } }
        else if (cmd === 'delete') { if (sel) deleteSelection(); else flashHint('Select something to delete.'); }
        else if (cmd === 'zoom-in') zoomAt({ x: W / 2, y: H / 2 }, 1.25);
        else if (cmd === 'zoom-out') zoomAt({ x: W / 2, y: H / 2 }, 1 / 1.25);
    });
    document.addEventListener('click', function (ev) { if (!ev.target.closest('#export-menu') && !ev.target.closest('[data-cmd=export]')) closeMenus(); });
    document.getElementById('plan-name').addEventListener('change', function () { if (savedOnce || !isEmpty()) saveNow(); });

    // ------------------------------------------------------------------ start
    var params = new URLSearchParams(location.search), planId = params.get('plan');
    if (planId) {
        var loaded = FPStore.get(planId);
        if (loaded) {
            plan = Object.assign(blankPlan(), loaded); savedOnce = true;
            saveState.textContent = 'Saved'; document.getElementById('plan-name').value = plan.name || 'Untitled plan';
        } else flashHint('That plan was not found in this browser. Starting a new plan.');
    }
    window.addEventListener('resize', resize);
    resize();
    fit();
    updateButtons();
    if (!planId) flashHint('Pick the Wall or Room tool on the left to start drawing.');
    // Expose for automated checks.
    window.FPEditor = { plan: function () { return plan; }, setTool: setTool, fit: fit, save: saveNow };
})();
