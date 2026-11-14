/* Floor Plan Designer — procedural 3D furniture for the 3D view.
 *
 * createFurniture(item) builds a model from simple shapes and materials, sized to the
 * item's width (x) and depth (z) in metres. Models face +z: the back of a bed, sofa or
 * toilet is at -z, which is the top edge of its 2D symbol. Returns null when the item
 * has no 3D model (the caller then shows its 2D symbol instead). */
import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/RoundedBoxGeometry.js';

const M = 0.01;

// ------------------------------------------------------------------ materials
const matCache = {};
function mat(key, make) { return matCache[key] || (matCache[key] = make()); }
const std = (color, roughness = 0.8, metalness = 0, extra = {}) => new THREE.MeshStandardMaterial(Object.assign({ color, roughness, metalness }, extra));
const M_ = {
    wood: () => mat('wood', () => std('#9b6a43', 0.6)),
    woodLight: () => mat('woodLight', () => std('#c9a27a', 0.6)),
    woodDark: () => mat('woodDark', () => std('#5d4030', 0.55)),
    white: () => mat('white', () => std('#f4f4f2', 0.7)),
    linen: () => mat('linen', () => std('#fbfaf6', 0.9)),
    ceramic: () => mat('ceramic', () => std('#fbfbfb', 0.15, 0.05)),
    basin: () => mat('basin', () => std('#e4ecf0', 0.2)),
    steel: () => mat('steel', () => std('#c3c8cd', 0.32, 0.75)),
    steelDark: () => mat('steelDark', () => std('#8f969d', 0.35, 0.7)),
    chrome: () => mat('chrome', () => std('#e1e5e8', 0.12, 0.95)),
    black: () => mat('black', () => std('#262a2e', 0.4)),
    piano: () => mat('piano', () => std('#141518', 0.18, 0.2)),
    glassDark: () => mat('glassDark', () => std('#30373e', 0.1, 0.3)),
    stone: () => mat('stone', () => std('#e7e3dc', 0.35)),
    leaf: () => mat('leaf', () => std('#5d9b4f', 0.85, 0, { flatShading: true })),
    leafDark: () => mat('leafDark', () => std('#3e7b45', 0.85, 0, { flatShading: true })),
    bark: () => mat('bark', () => std('#6d4c35', 0.95)),
    terracotta: () => mat('terracotta', () => std('#c4673f', 0.85)),
    grass: () => mat('grass', () => std('#7cb062', 1)),
    water: () => mat('water', () => std('#3fb5e8', 0.08, 0.1, { transparent: true, opacity: 0.85 })),
    concrete: () => mat('concrete', () => std('#b9bcbf', 0.9)),
    metalGrey: () => mat('metalGrey', () => std('#6f777e', 0.5, 0.5)),
};
const FABRICS = ['#8b97a3', '#a2896f', '#6f8094', '#b7a68c', '#7d8c75', '#9a8ea8', '#a7707a', '#5f7c86'];
const DUVETS = ['#c9d6e3', '#d9c7b4', '#b9cbb9', '#d7c3cf', '#c4c9d8', '#e0d6c3'];
function hash(s) { let h = 0; for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0; return Math.abs(h); }
function fabric(seed) { const c = FABRICS[hash(seed) % FABRICS.length]; return mat('fab' + c, () => std(c, 0.95)); }
function fabricLight(seed) { const c = new THREE.Color(FABRICS[hash(seed) % FABRICS.length]).offsetHSL(0, -0.05, 0.08); const k = '#' + c.getHexString(); return mat('fabL' + k, () => std(k, 0.95)); }
function duvet(seed) { const c = DUVETS[hash(seed + 'd') % DUVETS.length]; return mat('duv' + c, () => std(c, 0.95)); }

// ------------------------------------------------------------------ shape helpers
// Every helper takes the bottom height (y0) so parts stack naturally.
function rbox(g, w, h, d, r, material, x = 0, y0 = 0, z = 0) {
    if (w <= 0.001 || h <= 0.001 || d <= 0.001) return null;
    const rr = Math.min(r, w / 2 - 0.001, h / 2 - 0.001, d / 2 - 0.001);
    const geo = rr > 0.004 ? new RoundedBoxGeometry(w, h, d, 3, rr) : new THREE.BoxGeometry(w, h, d);
    const m = new THREE.Mesh(geo, material);
    m.position.set(x, y0 + h / 2, z); m.castShadow = true; m.receiveShadow = true;
    g.add(m); return m;
}
function cyl(g, rTop, rBot, h, material, x = 0, y0 = 0, z = 0, seg = 24) {
    const m = new THREE.Mesh(new THREE.CylinderGeometry(rTop, rBot, h, seg), material);
    m.position.set(x, y0 + h / 2, z); m.castShadow = true; m.receiveShadow = true;
    g.add(m); return m;
}
function sphere(g, r, material, x, y, z, detail = 1) {
    const m = new THREE.Mesh(new THREE.IcosahedronGeometry(r, detail), material);
    m.position.set(x, y, z); m.castShadow = true; m.receiveShadow = true;
    g.add(m); return m;
}
// A flat ellipse lying on a surface (basins, burners, rugs).
function disc(g, rx, rz, material, x, y, z) {
    const m = new THREE.Mesh(new THREE.CircleGeometry(1, 32), material);
    m.rotation.x = -Math.PI / 2; m.scale.set(rx, rz, 1); m.position.set(x, y, z); m.receiveShadow = true;
    g.add(m); return m;
}
function legs(g, W, D, h, inset, size, material, round = false) {
    const xs = [-W / 2 + inset, W / 2 - inset], zs = [-D / 2 + inset, D / 2 - inset];
    xs.forEach(x => zs.forEach(z => { if (round) cyl(g, size / 2, size / 2.4, h, material, x, 0, z, 10); else rbox(g, size, h, size, 0, material, x, 0, z); }));
}
const num = (label, re, def) => { const m = String(label).match(re); return m ? parseInt(m[1], 10) : def; };

// ------------------------------------------------------------------ models
function bed(g, W, D, seed) {
    const frameH = 0.32;
    legs(g, W, D, 0.08, 0.06, 0.06, M_.woodDark());
    rbox(g, W, frameH - 0.08, D, 0.02, M_.wood(), 0, 0.08, 0);
    rbox(g, W - 0.06, 0.2, D - 0.1, 0.05, M_.linen(), 0, frameH, 0.02);                       // mattress
    rbox(g, W + 0.02, 0.62, 0.08, 0.03, fabric(seed), 0, frameH + 0.1, -D / 2 + 0.04);        // headboard (fabric)
    rbox(g, W + 0.04, frameH + 0.1, 0.06, 0.02, M_.wood(), 0, 0, -D / 2 + 0.03);              // headboard base
    const n = W > 1.25 ? 2 : 1, pw = (W - 0.16) / n - 0.06;
    for (let i = 0; i < n; i++) rbox(g, pw, 0.13, 0.36, 0.06, M_.white(), -W / 2 + 0.11 + (pw + 0.06) * i + pw / 2, frameH + 0.19, -D / 2 + 0.3);
    const dl = D * 0.64;
    rbox(g, W - 0.02, 0.07, dl, 0.035, duvet(seed), 0, frameH + 0.17, D / 2 - dl / 2 - 0.03); // duvet
    rbox(g, W - 0.01, 0.075, 0.22, 0.035, M_.linen(), 0, frameH + 0.17, D / 2 - dl - 0.06);   // folded sheet
}
function sofa(g, W, D, seed, arms = true) {
    const f = fabric(seed), fl = fabricLight(seed), armW = arms && W > 1.0 ? Math.min(0.18, W * 0.12) : 0;
    legs(g, W, D, 0.07, 0.07, 0.05, M_.woodDark());
    rbox(g, W, 0.24, D, 0.04, f, 0, 0.07, 0);                                                  // base
    rbox(g, W, 0.48, Math.min(0.22, D * 0.28), 0.06, f, 0, 0.3, -D / 2 + Math.min(0.11, D * 0.14)); // back
    if (armW) [-1, 1].forEach(s => rbox(g, armW, 0.33, D, 0.06, f, s * (W / 2 - armW / 2), 0.3, 0));
    const inner = W - armW * 2, backD = Math.min(0.22, D * 0.28), n = Math.max(1, Math.round(inner / 0.7)), cw = inner / n;
    for (let i = 0; i < n; i++) {
        const x = -inner / 2 + cw * i + cw / 2;
        rbox(g, cw - 0.02, 0.13, D - backD - 0.03, 0.05, fl, x, 0.31, backD / 2 + 0.01);       // seat cushion
        rbox(g, cw - 0.04, 0.36, 0.14, 0.06, fl, x, 0.43, -D / 2 + backD + 0.06);              // back cushion
    }
}
function chaise(g, W, D, seed) {
    const f = fabric(seed);
    legs(g, W, D, 0.08, 0.06, 0.05, M_.woodDark());
    rbox(g, W, 0.3, D, 0.05, f, 0, 0.08, 0);
    const back = rbox(g, W - 0.04, 0.12, D * 0.35, 0.05, fabricLight(seed), 0, 0.38, -D / 2 + D * 0.2);
    if (back) { back.rotation.x = 0.55; back.position.y += 0.12; }
}
function chair(g, x, z, angle, material = M_.wood(), seatMat = null) {
    const c = new THREE.Group(); c.position.set(x, 0, z); c.rotation.y = angle;
    legs(c, 0.42, 0.42, 0.44, 0.04, 0.035, material);
    rbox(c, 0.44, 0.05, 0.44, 0.015, seatMat || material, 0, 0.44, 0);
    rbox(c, 0.44, 0.42, 0.04, 0.015, material, 0, 0.49, -0.2);
    g.add(c); return c;
}
function table(g, W, D, h, shape, material = M_.wood()) {
    if (shape === 'round' || shape === 'oval') {
        const top = cyl(g, 0.5, 0.5, 0.04, material, 0, h - 0.04, 0, 40); top.scale.set(W, 1, D);
        cyl(g, 0.05, 0.07, h - 0.04, M_.woodDark(), 0, 0, 0, 16);
        const foot = cyl(g, 0.5, 0.5, 0.03, M_.woodDark(), 0, 0, 0, 24); foot.scale.set(Math.min(W, D) * 0.55, 1, Math.min(W, D) * 0.55);
    } else {
        rbox(g, W, 0.04, D, 0.012, material, 0, h - 0.04, 0);
        legs(g, W, D, h - 0.04, 0.06, 0.05, M_.woodDark());
    }
}
function diningSet(g, W, D, label) {
    const lab = label.toLowerCase();
    const n = Math.max(2, Math.min(14, num(label, /(\d+)\s*chairs?/i, 4)));
    const shape = /round/.test(lab) ? 'round' : /oval/.test(lab) ? 'oval' : 'rect';
    const tw = Math.max(0.6, W - 1.0), td = Math.max(0.6, D - 1.0);
    table(g, shape === 'round' ? Math.min(tw, td) : tw, shape === 'round' ? Math.min(tw, td) : td, 0.75, shape);
    const seat = mat('seatFab', () => std('#c8b79e', 0.95));
    if (shape !== 'rect') {
        const rx = (shape === 'round' ? Math.min(tw, td) : tw) / 2 + 0.3, rz = (shape === 'round' ? Math.min(tw, td) : td) / 2 + 0.3;
        for (let i = 0; i < n; i++) { const a = i / n * Math.PI * 2; const x = Math.cos(a) * rx, z = Math.sin(a) * rz; chair(g, x, z, Math.atan2(x, z) + Math.PI, M_.wood(), seat); }
        return;
    }
    const longX = tw >= td, ends = n >= 6 ? 2 : 0, perSide = Math.ceil((n - ends) / 2);
    const L = longX ? tw : td, S = longX ? td : tw;
    for (let side = 0; side < 2; side++) {
        const count = side === 0 ? perSide : n - ends - perSide;
        for (let i = 0; i < count; i++) {
            const t = -L / 2 + L * (i + 0.5) / count, off = (side ? 1 : -1) * (S / 2 + 0.3);
            if (longX) chair(g, t, off, side ? Math.PI : 0, M_.wood(), seat); else chair(g, off, t, side ? -Math.PI / 2 : Math.PI / 2, M_.wood(), seat);
        }
    }
    if (ends) [-1, 1].forEach(s => { const off = s * (L / 2 + 0.3); if (longX) chair(g, off, 0, s > 0 ? -Math.PI / 2 : Math.PI / 2, M_.wood(), seat); else chair(g, 0, off, s > 0 ? Math.PI : 0, M_.wood(), seat); });
}
function desk(g, W, D, label) {
    rbox(g, W, 0.035, D, 0.01, M_.woodLight(), 0, 0.72, 0);
    const pw = Math.min(0.42, W * 0.35);
    rbox(g, pw, 0.72, D - 0.04, 0.01, M_.white(), W / 2 - pw / 2 - 0.02, 0, 0);               // drawer unit
    [0.18, 0.42].forEach(y => rbox(g, pw - 0.06, 0.01, 0.01, 0, M_.steelDark(), W / 2 - pw / 2 - 0.02, y + 0.12, D / 2 - 0.02));
    rbox(g, 0.04, 0.72, D - 0.08, 0, M_.white(), -W / 2 + 0.04, 0, 0);
    if (/computer|workstation/i.test(label)) {
        rbox(g, Math.min(0.6, W * 0.45), 0.36, 0.025, 0.01, M_.black(), -0.1, 0.86, -D / 2 + 0.14);
        rbox(g, 0.06, 0.13, 0.06, 0, M_.steelDark(), -0.1, 0.755, -D / 2 + 0.14);
        rbox(g, 0.42, 0.02, 0.14, 0.005, M_.black(), -0.1, 0.755, 0.05);
    }
    chair(g, -0.05, D / 2 + 0.25, Math.PI, M_.black(), mat('deskChair', () => std('#3c4a57', 0.9)));
}
function piano(g, W, D) {
    const body = rbox(g, W, 0.32, D, 0.12, M_.piano(), 0, 0.6, 0);
    [[-W / 2 + 0.15, -D / 2 + 0.2], [W / 2 - 0.15, -D / 2 + 0.2], [0, D / 2 - 0.2]].forEach(([x, z]) => cyl(g, 0.045, 0.035, 0.6, M_.piano(), x, 0, z, 12));
    rbox(g, W * 0.92, 0.04, 0.16, 0.01, M_.white(), 0, 0.88, -D / 2 + 0.08);                 // keys
    // Lid propped open on its stick.
    const lidD = D * 0.8, lid = new THREE.Group(); lid.position.set(W / 2 - 0.02, 0.93, 0.05); lid.rotation.z = -0.45; g.add(lid);
    rbox(lid, W * 0.95, 0.02, lidD, 0.03, M_.piano(), -W * 0.475, 0, 0);
    cyl(g, 0.01, 0.01, W * 0.42, M_.woodDark(), -W * 0.1, 0.93, 0.05, 6);
    rbox(g, 0.9, 0.48, 0.36, 0.04, M_.piano(), 0, 0, -D / 2 - 0.28);                          // bench
    return body;
}
function toilet(g, W, D) {
    const tankD = Math.min(0.2, D * 0.3);
    rbox(g, W * 0.92, 0.42, tankD, 0.03, M_.ceramic(), 0, 0.38, -D / 2 + tankD / 2);
    const bowl = cyl(g, 0.5, 0.36, 0.4, M_.ceramic(), 0, 0, -D / 2 + tankD + (D - tankD) / 2, 28); bowl.scale.set(W * 0.82, 1, (D - tankD) * 0.95);
    const seat = cyl(g, 0.5, 0.5, 0.03, M_.white(), 0, 0.4, bowl.position.z, 28); seat.scale.set(W * 0.86, 1, (D - tankD) * 0.98);
    rbox(g, 0.05, 0.02, 0.03, 0, M_.chrome(), W * 0.3, 0.8, -D / 2 + tankD / 2);
}
function tub(g, W, D) {
    rbox(g, W, 0.55, D, 0.05, M_.ceramic(), 0, 0, 0);
    const inset = 0.08;
    rbox(g, W - inset * 2, 0.012, D - inset * 2, 0.08, M_.basin(), 0, 0.548, 0);
    rbox(g, W - inset * 2 - 0.04, 0.012, D - inset * 2 - 0.04, 0.08, M_.water(), 0, 0.552, 0);
    const longX = W >= D;
    cyl(g, 0.018, 0.018, 0.16, M_.chrome(), longX ? -W / 2 + 0.05 : 0, 0.55, longX ? 0 : -D / 2 + 0.05, 10);
}
function cornerTub(g, W, D) {
    rbox(g, W, 0.55, D, 0.05, M_.ceramic(), 0, 0, 0);
    const q = new THREE.Mesh(new THREE.CircleGeometry(1, 32, 0, Math.PI / 2), M_.water());
    q.rotation.x = -Math.PI / 2; q.scale.set(W * 0.82, D * 0.82, 1); q.position.set(-W / 2 + 0.06, 0.553, -D / 2 + 0.06); q.rotation.z = -Math.PI / 2;
    g.add(q);
}
function vanity(g, W, D, basins) {
    rbox(g, W, 0.78, D, 0.01, M_.woodLight(), 0, 0, 0);
    rbox(g, W + 0.02, 0.04, D + 0.02, 0.01, M_.stone(), 0, 0.78, 0);
    for (let i = 0; i < basins; i++) {
        const x = -W / 2 + W * (i + 0.5) / basins;
        disc(g, Math.min(0.2, W / basins * 0.32), Math.min(0.15, D * 0.3), M_.basin(), x, 0.822, 0.03);
        cyl(g, 0.014, 0.014, 0.16, M_.chrome(), x, 0.82, -D / 2 + 0.07, 10);
        rbox(g, 0.025, 0.02, 0.12, 0, M_.chrome(), x, 0.96, -D / 2 + 0.12);
    }
}
function kitchenSink(g, W, D, basins) {
    rbox(g, W, 0.86, D, 0.01, M_.white(), 0, 0, 0);
    rbox(g, W + 0.02, 0.04, D + 0.02, 0.01, M_.stone(), 0, 0.86, 0);
    const bw = Math.min(0.5, W / (basins + 0.6));
    for (let i = 0; i < basins; i++) {
        const x = -((basins - 1) * (bw + 0.05)) / 2 + i * (bw + 0.05) - (W > bw * basins + 0.4 ? W * 0.15 : 0);
        rbox(g, bw, 0.005, Math.min(0.42, D * 0.7), 0.03, M_.steelDark(), x, 0.9, 0.02);
    }
    cyl(g, 0.016, 0.016, 0.3, M_.chrome(), -W * 0.15 + 0, 0.9, -D / 2 + 0.06, 10);
}
function fridge(g, W, D) {
    rbox(g, W, 1.82, D, 0.025, M_.steel(), 0, 0, 0);
    rbox(g, W - 0.02, 0.008, 0.01, 0, M_.steelDark(), 0, 1.2, D / 2 + 0.002);
    if (W > 0.8) rbox(g, 0.008, 1.8, 0.01, 0, M_.steelDark(), 0, 0.01, D / 2 + 0.002);
    const hx = W > 0.8 ? [-0.06, 0.06] : [W / 2 - 0.08];
    hx.forEach(x => { rbox(g, 0.025, 0.5, 0.03, 0.01, M_.chrome(), x, 1.25, D / 2 + 0.02); rbox(g, 0.025, 0.45, 0.03, 0.01, M_.chrome(), x, 0.65, D / 2 + 0.02); });
}
function stove(g, W, D, label) {
    rbox(g, W, 0.88, D, 0.015, M_.steel(), 0, 0, 0);
    rbox(g, W - 0.02, 0.02, D - 0.02, 0.01, M_.black(), 0, 0.88, 0);
    const n = Math.max(1, Math.min(8, num(label, /(\d)\s*burner/i, 4))), cols = n <= 2 ? n : Math.ceil(n / 2), rows = n <= 2 ? 1 : 2;
    let k = 0;
    for (let r = 0; r < rows; r++) for (let c = 0; c < cols && k < n; c++, k++) {
        const x = -W / 2 + W * (c + 0.5) / cols, z = -D / 2 + D * (r + 0.5) / rows;
        cyl(g, Math.min(0.1, W / cols * 0.3), Math.min(0.1, W / cols * 0.3), 0.015, M_.steelDark(), x, 0.9, z, 20);
    }
    rbox(g, W - 0.08, 0.42, 0.01, 0.01, M_.glassDark(), 0, 0.22, D / 2 + 0.003);
    rbox(g, W - 0.16, 0.025, 0.03, 0.01, M_.chrome(), 0, 0.7, D / 2 + 0.02);
}
function applianceBox(g, W, D, h, material, front) {
    rbox(g, W, h, D, 0.02, material, 0, 0, 0);
    if (front === 'glass') rbox(g, W - 0.08, h * 0.5, 0.01, 0.01, M_.glassDark(), 0, h * 0.2, D / 2 + 0.003);
    if (front === 'round') { const d = cyl(g, Math.min(W, h) * 0.28, Math.min(W, h) * 0.28, 0.02, M_.glassDark(), 0, 0, D / 2, 28); d.rotation.x = Math.PI / 2; d.position.y = h * 0.45; }
    if (front === 'handle') rbox(g, W - 0.12, 0.025, 0.03, 0.01, M_.chrome(), 0, h - 0.08, D / 2 + 0.02);
}
function hood(g, W, D) {
    const m = cyl(g, 0.5, 0.5, 0.18, M_.steel(), 0, 1.6, 0, 4); m.rotation.y = Math.PI / 4; m.scale.set(W * 1.41, 1, D * 1.41);
    rbox(g, Math.min(0.3, W * 0.4), 0.9, Math.min(0.25, D * 0.5), 0, M_.steel(), 0, 1.78, -D / 2 + Math.min(0.125, D * 0.25));
}
function baseCabinet(g, W, D, label) {
    rbox(g, W, 0.86, D, 0.008, M_.white(), 0, 0, 0);
    rbox(g, W + 0.02, 0.04, D + 0.02, 0.008, M_.stone(), 0, 0.86, 0);
    const doors = Math.max(1, Math.round(W / 0.5)), drawers = /drawer|dr\b|\ddr/i.test(label);
    for (let i = 0; i < doors; i++) {
        const x = -W / 2 + W * (i + 0.5) / doors;
        if (i) rbox(g, 0.006, 0.82, 0.006, 0, M_.steelDark(), -W / 2 + W * i / doors, 0.02, D / 2 + 0.002);
        rbox(g, 0.12, 0.015, 0.02, 0, M_.chrome(), x, drawers ? 0.74 : 0.7, D / 2 + 0.012);
    }
    if (drawers) rbox(g, W - 0.01, 0.006, 0.006, 0, M_.steelDark(), 0, 0.66, D / 2 + 0.002);
}
function wallCabinet(g, W, D) {
    rbox(g, W, 0.7, D, 0.008, M_.white(), 0, 1.45, 0);
    rbox(g, 0.015, 0.12, 0.02, 0, M_.chrome(), 0, 1.5, D / 2 + 0.012);
}
function stairs(g, W, D) {
    const alongZ = D >= W, L = alongZ ? D : W, S = alongZ ? W : D, n = Math.max(4, Math.min(20, Math.round(L / 0.28))), rise = 2.6 / n, run = L / n;
    for (let i = 0; i < n; i++) {
        const h = rise * (i + 1), c = -L / 2 + run * (i + 0.5);
        rbox(g, alongZ ? S : run + 0.002, h, alongZ ? run + 0.002 : S, 0, i % 2 ? M_.woodLight() : M_.wood(), alongZ ? 0 : c, 0, alongZ ? -c : 0);
    }
    const rail = M_.steelDark(), side = S / 2 - 0.03;
    for (let i = 0; i < n; i += 3) { const c = -L / 2 + run * (i + 0.5); cyl(g, 0.015, 0.015, 0.9, rail, alongZ ? side : c, rise * (i + 1), alongZ ? -c : side, 8); }
}
function spiralStairs(g, W, D) {
    const r = Math.min(W, D) / 2, n = 14, rise = 2.6 / n;
    cyl(g, 0.06, 0.06, 2.8, M_.steelDark(), 0, 0, 0, 12);
    for (let i = 0; i < n; i++) {
        const step = new THREE.Mesh(new THREE.CylinderGeometry(r, r, 0.04, 16, 1, false, 0, Math.PI * 2 / 10), M_.wood());
        step.rotation.y = i * Math.PI * 2 / 12; step.position.y = rise * (i + 1); step.castShadow = step.receiveShadow = true; g.add(step);
    }
}
function tree(g, W, D, kind) {
    const r = Math.max(0.3, Math.min(W, D) / 2);
    if (kind === 'pine') {
        cyl(g, 0.07, 0.1, 0.5, M_.bark(), 0, 0, 0, 8);
        [0, 1, 2].forEach(i => { const c = new THREE.Mesh(new THREE.ConeGeometry(r * (1 - i * 0.25), r * 1.4, 9), M_.leafDark()); c.position.y = 0.5 + i * r * 0.75 + r * 0.7; c.castShadow = true; g.add(c); });
        return;
    }
    if (kind === 'palm') {
        const t = cyl(g, 0.07, 0.11, 2.6, M_.bark(), 0, 0, 0, 8); t.rotation.z = 0.06;
        for (let i = 0; i < 7; i++) { const f = sphere(g, 1, M_.leaf(), 0, 2.6, 0, 0); f.scale.set(r * 0.9, 0.04, 0.16); const a = i / 7 * Math.PI * 2; f.position.x = Math.cos(a) * r * 0.55; f.position.z = Math.sin(a) * r * 0.55; f.rotation.y = -a; f.rotation.z = -0.35; }
        return;
    }
    cyl(g, 0.07, 0.11, 1.3, M_.bark(), 0, 0, 0, 8);
    sphere(g, r * 0.85, M_.leaf(), 0, 1.3 + r * 0.6, 0);
    sphere(g, r * 0.6, M_.leafDark(), r * 0.35, 1.2 + r * 0.4, r * 0.2);
    sphere(g, r * 0.55, M_.leaf(), -r * 0.3, 1.35 + r * 0.5, -r * 0.25);
}
function pottedPlant(g, W, D) {
    const r = Math.max(0.12, Math.min(W, D) / 2);
    cyl(g, r * 0.7, r * 0.5, r * 1.2, M_.terracotta(), 0, 0, 0, 16);
    sphere(g, r * 0.95, M_.leaf(), 0, r * 1.2 + r * 0.7, 0);
    sphere(g, r * 0.6, M_.leafDark(), r * 0.3, r * 1.2 + r * 1.2, -r * 0.1);
}
function pool(g, W, D) {
    const t = 0.18;
    [[0, -D / 2 + t / 2, W, t], [0, D / 2 - t / 2, W, t], [-W / 2 + t / 2, 0, t, D], [W / 2 - t / 2, 0, t, D]].forEach(([x, z, w, d]) => rbox(g, w, 0.06, d, 0.01, M_.stone(), x, 0, z));
    rbox(g, W - t * 2, 0.02, D - t * 2, 0, M_.water(), 0, 0.02, 0);
}
function gate(g, W, D) {
    const alongX = W >= D, L = Math.max(W, D), h = 1.2, bars = Math.max(3, Math.round(L / 0.12));
    const frame = (len, x, z) => rbox(g, alongX ? len : 0.04, 0.04, alongX ? 0.04 : len, 0, M_.metalGrey(), x, 0, z);
    [0.05, h - 0.04].forEach(y => { const m = frame(L, 0, 0); if (m) m.position.y = y + 0.02; });
    for (let i = 0; i <= bars; i++) { const t = -L / 2 + L * i / bars; rbox(g, 0.02, h, 0.02, 0, M_.metalGrey(), alongX ? t : 0, 0, alongX ? 0 : t); }
}

// ------------------------------------------------------------------ classification
function classify(cat, label, file) {
    const s = (label + ' ' + file).toLowerCase();
    if (['basic', 'communication', 'rooms', 'doors', 'windows', 'electrical'].includes(cat) && !/water heater/.test(s)) return null;
    if (cat === 'custom' && !/cab|drawer|cabinet|blind corner|pantry|vanity|shelf/.test(s)) return null; // room-name labels
    if (cat === 'bathtub' || /\btub\b/.test(s)) return /corner/.test(s) ? 'cornerTub' : 'tub';
    if (cat === 'toilet' || /toilet/.test(s)) return 'toilet';
    if (cat === 'sinks' || /sink/.test(s)) return /dryboard|double|triple|mop|offset/.test(s) ? 'kitchenSink' : 'vanity';
    if (cat === 'dishwashers' || /dish ?washer|\bdw\b/.test(s)) return 'dishwasher';
    if (/fridge|refrigerator|\bref\b/.test(s)) return 'fridge';
    if (/dryer|washer/.test(s)) return 'washer';
    if (/burner|griddle|range|stove|cooktop/.test(s)) return 'stove';
    if (/vent hood|hood/.test(s)) return 'hood';
    if (/microwave/.test(s)) return 'microwave';
    if (/oven/.test(s)) return 'oven';
    if (/fryer|grill|espresso/.test(s)) return 'smallAppliance';
    if (/\bac\b/.test(s)) return 'ac';
    if (/chaise/.test(s)) return 'chaise';
    if (/sofa|couch|loveseat/.test(s)) return 'sofa';
    if (/\bbed\b/.test(s) || /^bed/.test(s)) return 'bed';
    if (/piano/.test(s)) return 'piano';
    if (/outdoor set|chairs?\b/.test(s) && /table|set/.test(s)) return 'dining';
    if (/coffee table/.test(s)) return 'coffeeTable';
    if (/side table/.test(s)) return 'sideTable';
    if (cat === 'desk' || /desk|workstation/.test(s)) return 'desk';
    if (/table/.test(s)) return 'table';
    if (/wall cab/.test(s)) return 'wallCab';
    if (/cab|drawer|cabinet|blind corner|pantry/.test(s)) return 'baseCab';
    if (/spiral/.test(s) && (cat === 'stairs' || /stair/.test(s))) return 'spiral';
    if (cat === 'stairs' || /stair/.test(s)) return 'stairs';
    if (/pillar circle/.test(s)) return 'pillarRound';
    if (/pillar/.test(s)) return 'pillar';
    if (/elevator/.test(s)) return 'elevator';
    if (cat === 'garden') {
        if (/palm/.test(s)) return 'palm';
        if (/pine/.test(s)) return 'pine';
        if (/tree/.test(s)) return 'tree';
        if (/potted|plant/.test(s)) return 'plant';
        if (/hedge|bush/.test(s)) return 'hedge';
        if (/grass/.test(s)) return 'grass';
    }
    if (cat === 'pool') return 'pool';
    if (cat === 'posts') return 'post';
    if (cat === 'gates' || cat === 'linegates' || cat === 'number-gates') return 'gate';
    if (/water heater/.test(s)) return 'heater';
    if (cat === 'havc') return 'machine';
    return null;
}

/** Build a model for a plan item ({cat, file, label, w, h, rot, id} in cm). */
export function createFurniture(it) {
    const type = classify(it.cat || '', it.label || '', it.file || '');
    if (!type) return null;
    let W = Math.max(0.05, it.w * M), D = Math.max(0.05, it.h * M);
    const seed = String(it.id || it.file);
    // Many library symbols are drawn from the front, so the footprint doesn't tell which way an
    // item faces. Items with a natural long axis (beds run head-to-foot, toilets front-to-back)
    // turn so that axis follows the longer side of the footprint; the head then sits at -x.
    const longDepth = { bed: 1.05, toilet: 1, stairs: 0 }[type];
    const turn = longDepth !== undefined && longDepth > 0 && W > D * longDepth;
    const outer = new THREE.Group(), g = new THREE.Group();
    outer.add(g);
    if (turn) { const t = W; W = D; D = t; g.rotation.y = Math.PI / 2; }
    switch (type) {
        case 'bed': bed(g, W, D, seed); break;
        case 'sofa': sofa(g, W, D, seed); break;
        case 'chaise': chaise(g, W, D, seed); break;
        case 'piano': piano(g, W, D); break;
        case 'dining': diningSet(g, W, D, it.label || ''); break;
        case 'coffeeTable': table(g, W, D, 0.42, /round|oval/i.test(it.label) ? 'round' : 'rect', M_.woodLight()); break;
        case 'sideTable': table(g, W, D, 0.55, 'rect', M_.wood()); break;
        case 'table': table(g, W, D, 0.75, /round/i.test(it.label) ? 'round' : /oval/i.test(it.label) ? 'oval' : 'rect'); break;
        case 'desk': desk(g, W, D, it.label || ''); break;
        case 'toilet': toilet(g, W, D); break;
        case 'tub': tub(g, W, D); break;
        case 'cornerTub': cornerTub(g, W, D); break;
        case 'vanity': vanity(g, W, D, 1); break;
        case 'kitchenSink': kitchenSink(g, W, D, /triple/i.test(it.label) ? 3 : /double/i.test(it.label) ? 2 : 1); break;
        case 'fridge': fridge(g, W, D); break;
        case 'stove': stove(g, W, D, it.label || ''); break;
        case 'oven': applianceBox(g, W, D, 0.88, M_.steel(), 'glass'); break;
        case 'microwave': applianceBox(g, W, D, 0.32, M_.black(), 'glass'); break;
        case 'dishwasher': applianceBox(g, W, D, 0.86, M_.steel(), 'handle'); break;
        case 'washer': applianceBox(g, W, D, 0.86, M_.white(), 'round'); break;
        case 'smallAppliance': applianceBox(g, W, D, 0.9, M_.steel(), 'handle'); break;
        case 'ac': { const m = rbox(g, W, 0.3, Math.min(D, 0.25), 0.04, M_.white(), 0, 2.0, 0); if (m) rbox(g, W * 0.85, 0.02, 0.01, 0, M_.steelDark(), 0, 2.05, Math.min(D, 0.25) / 2); break; }
        case 'hood': hood(g, W, D); break;
        case 'baseCab': baseCabinet(g, W, D, it.label || ''); break;
        case 'wallCab': wallCabinet(g, W, D); break;
        case 'stairs': stairs(g, W, D); break;
        case 'spiral': spiralStairs(g, W, D); break;
        case 'pillar': rbox(g, W, 2.8, D, 0.01, M_.concrete(), 0, 0, 0); break;
        case 'pillarRound': { const c = cyl(g, 0.5, 0.5, 2.8, M_.concrete(), 0, 0, 0, 24); c.scale.set(W, 1, D); break; }
        case 'elevator': rbox(g, W, 2.8, D, 0.01, M_.metalGrey(), 0, 0, 0); rbox(g, W * 0.6, 2.1, 0.01, 0, M_.steel(), 0, 0, D / 2 + 0.003); break;
        case 'tree': tree(g, W, D, 'tree'); break;
        case 'pine': tree(g, W, D, 'pine'); break;
        case 'palm': tree(g, W, D, 'palm'); break;
        case 'plant': pottedPlant(g, W, D); break;
        case 'hedge': rbox(g, W, 0.9, D, Math.min(0.2, Math.min(W, D) / 2), M_.leafDark(), 0, 0, 0); break;
        case 'grass': rbox(g, W, 0.02, D, 0, M_.grass(), 0, 0, 0); break;
        case 'pool': pool(g, W, D); break;
        case 'post': rbox(g, Math.min(W, 0.15), 1.25, Math.min(D, 0.15), 0.01, M_.metalGrey(), 0, 0, 0); break;
        case 'gate': gate(g, W, D); break;
        case 'heater': { const c = cyl(g, 0.5, 0.5, 1.3, M_.white(), 0, 0, 0, 24); c.scale.set(W, 1, D); break; }
        case 'machine': rbox(g, W, 0.9, D, 0.03, M_.metalGrey(), 0, 0, 0); rbox(g, W * 0.7, 0.05, D * 0.7, 0.02, M_.steelDark(), 0, 0.9, 0); break;
        default: return null;
    }
    outer.userData.type = type;
    return outer;
}
