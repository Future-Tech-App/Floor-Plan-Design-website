/* Floor Plan Designer — 3D view (three.js). Reads a saved plan from browser storage
 * and builds walls (with door/window openings), floors, doors, windows and furniture.
 * Plan units are centimetres; the 3D scene uses metres (plan x → x, plan y → z). */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/OrbitControls.js';

const root = document.getElementById('viewer');
const stage = document.getElementById('stage');
const SYMBOL_BASE = root.dataset.symbolsBase;
const M = 0.01;                          // cm → m
const BASE_WALL_H = 280;                 // cm, scaled by the wall height setting
const DOOR_H = 210, SILL_H = 90, WIN_TOP = 210;
const DEFAULTS = { wallHeight: 1, wallColor: '#ede8e0', floor: 'room', furniture: true, doors: true, doorsOpen: false, windows: true, labels: true, shadows: true };
// Typical heights (cm) for library categories, so furniture reads as 3D blocks.
const ITEM_H = { furniture: 55, tables: 75, desk: 75, appliance: 90, cooking: 90, dishwashers: 85, sinks: 85, bathtub: 55, toilet: 40, stairs: 120, architecture: 100, garden: 40, pool: 5, posts: 120, electrical: 30, havc: 60, communication: 40, basic: 40, doors: 5, windows: 5, gates: 120, linegates: 120, 'number-gates': 120, rooms: 2, custom: 2 };

let opts = loadOpts();
function loadOpts() {
    try { return Object.assign({}, DEFAULTS, JSON.parse(localStorage.getItem('fpd_3d') || '{}')); } catch (e) { return Object.assign({}, DEFAULTS); }
}
function saveOpts() { try { localStorage.setItem('fpd_3d', JSON.stringify(opts)); } catch (e) { /* ignore */ } }

// ------------------------------------------------------------------ plan
const params = new URLSearchParams(location.search);
const planId = params.get('plan');
const plan = planId && window.FPStore ? FPStore.get(planId) : null;
if (planId) document.getElementById('back-link').href = FP.url('/app/editor?plan=' + encodeURIComponent(planId));
const hasContent = plan && ((plan.walls || []).length || (plan.rooms || []).length);
if (!hasContent) {
    document.getElementById('empty').hidden = false;
    if (plan) document.getElementById('empty-text').textContent = 'This plan has no walls or rooms yet. Draw some in the editor first.';
    else if (planId) document.getElementById('empty-text').textContent = 'That plan was not found in this browser.';
    root.querySelectorAll('.ed-actions button').forEach(b => { b.disabled = true; });
} else {
    document.getElementById('plan-title').textContent = (plan.name || 'Untitled plan') + ' — 3D';
    document.title = (plan.name || 'Plan') + ' — 3D View — Floor Plan Designer';
    init();
}

function init() {
    // ------------------------------------------------------------------ renderer / scene
    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.domElement.tabIndex = 0;
    renderer.domElement.setAttribute('aria-label', '3D view of the floor plan');
    stage.prepend(renderer.domElement);

    const scene = new THREE.Scene();
    scene.background = new THREE.Color('#e8eef5');
    scene.fog = new THREE.Fog('#e8eef5', 40, 120);

    const camera = new THREE.PerspectiveCamera(50, 1, 0.05, 500);
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.maxPolarAngle = Math.PI / 2 - 0.03;
    controls.minDistance = 1;
    controls.maxDistance = 120;

    scene.add(new THREE.HemisphereLight('#ffffff', '#c8ced4', 2.2));
    const sun = new THREE.DirectionalLight('#fff8ee', 1.6);
    sun.castShadow = true;
    sun.shadow.mapSize.set(2048, 2048);
    sun.shadow.bias = -0.0005;
    scene.add(sun, sun.target);

    // ------------------------------------------------------------------ bounds
    const xs = [], ys = [];
    (plan.walls || []).forEach(w => { xs.push(w.x1, w.x2); ys.push(w.y1, w.y2); });
    (plan.rooms || []).forEach(r => (r.pts || []).forEach(p => { xs.push(p.x); ys.push(p.y); }));
    const b = { x0: Math.min(...xs), y0: Math.min(...ys), x1: Math.max(...xs), y1: Math.max(...ys) };
    const center = new THREE.Vector3((b.x0 + b.x1) / 2 * M, 0, (b.y0 + b.y1) / 2 * M);
    const span = Math.max((b.x1 - b.x0) * M, (b.y1 - b.y0) * M, 4);

    // Ground and sun cover the whole plan.
    const ground = new THREE.Mesh(new THREE.PlaneGeometry(span * 6, span * 6), new THREE.MeshStandardMaterial({ color: '#d7dde3', roughness: 1 }));
    ground.rotation.x = -Math.PI / 2; ground.position.set(center.x, -0.01, center.z); ground.receiveShadow = true;
    scene.add(ground);
    sun.position.set(center.x + span * 0.35, span * 2.2, center.z + span * 0.55);
    sun.target.position.copy(center);
    const sc = sun.shadow.camera; sc.left = sc.bottom = -span; sc.right = sc.top = span; sc.near = 0.1; sc.far = span * 4; sc.updateProjectionMatrix();

    // ------------------------------------------------------------------ textures
    function canvasTexture(draw, size = 512) {
        const c = document.createElement('canvas'); c.width = c.height = size;
        draw(c.getContext('2d'), size);
        const t = new THREE.CanvasTexture(c);
        t.wrapS = t.wrapT = THREE.RepeatWrapping; t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = 4;
        return t;
    }
    const rnd = (seed => () => (seed = (seed * 16807) % 2147483647) / 2147483647)(12345);
    const floorTex = {
        wood: () => canvasTexture((x, s) => {
            const rows = 8, h = s / rows;
            for (let r = 0; r < rows; r++) {
                let xx = -rnd() * s;
                while (xx < s) {
                    const len = s * (0.45 + rnd() * 0.5), shade = 150 + rnd() * 40;
                    x.fillStyle = `rgb(${shade + 30},${shade - 15},${shade - 70})`; x.fillRect(xx, r * h, len, h);
                    x.strokeStyle = 'rgba(60,35,15,.12)';
                    for (let g = 0; g < 5; g++) { x.beginPath(); const gy = r * h + rnd() * h; x.moveTo(xx, gy); x.bezierCurveTo(xx + len / 3, gy + 3, xx + len * 2 / 3, gy - 3, xx + len, gy); x.stroke(); }
                    x.fillStyle = 'rgba(40,20,5,.45)'; x.fillRect(xx, r * h, 2, h); xx += len;
                }
                x.fillStyle = 'rgba(40,20,5,.4)'; x.fillRect(0, r * h, s, 2);
            }
        }),
        tiles: () => canvasTexture((x, s) => {
            x.fillStyle = '#b9b4ad'; x.fillRect(0, 0, s, s);
            const n = 2, t = s / n;
            for (let i = 0; i < n; i++) for (let j = 0; j < n; j++) { const v = 228 + rnd() * 14; x.fillStyle = `rgb(${v},${v - 3},${v - 8})`; x.fillRect(i * t + 3, j * t + 3, t - 6, t - 6); }
        }),
        marble: () => canvasTexture((x, s) => {
            x.fillStyle = '#f4f3f1'; x.fillRect(0, 0, s, s);
            for (let v = 0; v < 14; v++) {
                x.strokeStyle = `rgba(120,120,130,${0.08 + rnd() * 0.15})`; x.lineWidth = 0.5 + rnd() * 2;
                x.beginPath(); let px = rnd() * s, py = 0; x.moveTo(px, py);
                while (py < s) { px += (rnd() - 0.5) * 60; py += 20 + rnd() * 30; x.lineTo(px, py); }
                x.stroke();
            }
            x.strokeStyle = 'rgba(150,150,150,.35)'; x.lineWidth = 2; x.strokeRect(0, 0, s, s);
        }),
        concrete: () => canvasTexture((x, s) => {
            x.fillStyle = '#a9adb1'; x.fillRect(0, 0, s, s);
            for (let i = 0; i < 9000; i++) { const v = 140 + rnd() * 60; x.fillStyle = `rgba(${v},${v},${v + 4},.35)`; x.fillRect(rnd() * s, rnd() * s, 2, 2); }
        })
    };
    const TILE_M = { wood: 2, tiles: 1.2, marble: 1.2, concrete: 3 };
    const texCache = {};
    function getFloorTex(kind) { return texCache[kind] || (texCache[kind] = floorTex[kind]()); }

    // ------------------------------------------------------------------ build
    const group = new THREE.Group(); scene.add(group);
    const labelGroup = new THREE.Group(); scene.add(labelGroup);
    const symbolImages = {};
    const materials = {
        wall: new THREE.MeshStandardMaterial({ color: opts.wallColor, roughness: 0.92 }),
        wallTop: new THREE.MeshStandardMaterial({ color: '#4a5560', roughness: 0.8 }),
        door: new THREE.MeshStandardMaterial({ color: '#8d6e63', roughness: 0.6 }),
        frame: new THREE.MeshStandardMaterial({ color: '#f5f5f5', roughness: 0.5 }),
        glass: new THREE.MeshPhysicalMaterial({ color: '#9fd3f0', transparent: true, opacity: 0.35, roughness: 0.05, metalness: 0, side: THREE.DoubleSide }),
        side: new THREE.MeshStandardMaterial({ color: '#eceff1', roughness: 0.8 })
    };

    function box(w, h, d, mat, cast = true) {
        const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), mat);
        m.castShadow = cast; m.receiveShadow = true; return m;
    }
    // Place a box along a wall: s0..s1 cm from the wall start, between heights h0..h1 cm.
    function wallPiece(w, ux, uy, ang, s0, s1, h0, h1, depth, mat) {
        const len = s1 - s0; if (len <= 0.5 || h1 - h0 <= 0.5) return null;
        const mid = (s0 + s1) / 2;
        const m = box(len * M, (h1 - h0) * M, depth * M, mat);
        m.position.set((w.x1 + ux * mid) * M, (h0 + h1) / 2 * M, (w.y1 + uy * mid) * M);
        m.rotation.y = -ang;
        group.add(m); return m;
    }

    function build() {
        group.clear(); labelGroup.clear();
        const wallH = BASE_WALL_H * opts.wallHeight;
        materials.wall.color.set(opts.wallColor);

        // Floors
        (plan.rooms || []).forEach(r => {
            if (!r.pts || r.pts.length < 3) return;
            const shape = new THREE.Shape(r.pts.map(p => new THREE.Vector2(p.x * M, -p.y * M)));
            const geo = new THREE.ShapeGeometry(shape); geo.rotateX(-Math.PI / 2);
            let mat;
            if (opts.floor === 'room') mat = new THREE.MeshStandardMaterial({ color: r.color || '#e3f2fd', roughness: 0.9 });
            else {
                const t = getFloorTex(opts.floor).clone(); t.needsUpdate = true; t.repeat.set(1 / TILE_M[opts.floor], 1 / TILE_M[opts.floor]);
                mat = new THREE.MeshStandardMaterial({ map: t, roughness: opts.floor === 'marble' ? 0.3 : 0.85 });
            }
            const mesh = new THREE.Mesh(geo, mat); mesh.position.y = 0.002; mesh.receiveShadow = true; group.add(mesh);
            if (opts.labels) labelGroup.add(makeLabel(r));
        });

        // Walls with openings
        (plan.walls || []).forEach(w => {
            const L = Math.hypot(w.x2 - w.x1, w.y2 - w.y1); if (L < 1) return;
            const ux = (w.x2 - w.x1) / L, uy = (w.y2 - w.y1) / L, ang = Math.atan2(uy, ux), t = w.t || 15;
            const ops = (plan.openings || []).filter(o => o.wall === w.id)
                .map(o => ({ o, s0: Math.max(0, o.t * L - o.w / 2), s1: Math.min(L, o.t * L + o.w / 2) }))
                .sort((a, c) => a.s0 - c.s0);
            // Extend both ends by half the thickness so corners close.
            let cur = -t / 2;
            ops.forEach(({ o, s0, s1 }) => {
                wallPiece(w, ux, uy, ang, cur, s0, 0, wallH, t, materials.wall);
                const top = Math.min(o.kind === 'door' ? DOOR_H : WIN_TOP, wallH);
                wallPiece(w, ux, uy, ang, s0, s1, top, wallH, t, materials.wall);                         // lintel
                if (o.kind === 'window') {
                    const sill = Math.min(SILL_H, top);
                    wallPiece(w, ux, uy, ang, s0, s1, 0, sill, t, materials.wall);                         // sill wall
                    if (opts.windows) {
                        wallPiece(w, ux, uy, ang, s0, s1, sill, sill + 3, t + 4, materials.frame);          // sill board
                        const g = wallPiece(w, ux, uy, ang, s0 + 2, s1 - 2, sill + 3, top - 2, 2, materials.glass);
                        if (g) g.castShadow = false;
                        wallPiece(w, ux, uy, ang, (s0 + s1) / 2 - 2, (s0 + s1) / 2 + 2, sill + 3, top, 6, materials.frame); // mullion
                    }
                } else if (opts.doors) addDoor(w, ux, uy, o, s0, s1, Math.min(DOOR_H, wallH) - 5);
                cur = s1;
            });
            wallPiece(w, ux, uy, ang, cur, L + t / 2, 0, wallH, t, materials.wall);
        });

        // Furniture
        if (opts.furniture) (plan.items || []).forEach(addItem);
    }

    function addDoor(w, ux, uy, o, s0, s1, h) {
        const width = s1 - s0, hs = o.hinge ? -1 : 1, side = o.flip ? -1 : 1;
        const nx = -uy, ny = ux, c = (s0 + s1) / 2;
        const hx = w.x1 + ux * (c - hs * width / 2), hy = w.y1 + uy * (c - hs * width / 2);
        const closed = Math.atan2(uy * hs, ux * hs), open = Math.atan2(ny * side, nx * side);
        // Rotate from the closed direction towards the open side by ~80°.
        let diff = open - closed; while (diff > Math.PI) diff -= 2 * Math.PI; while (diff < -Math.PI) diff += 2 * Math.PI;
        const angle = opts.doorsOpen ? closed + diff * (80 / 90) : closed;
        const pivot = new THREE.Group(); pivot.position.set(hx * M, 0, hy * M); pivot.rotation.y = -angle;
        const leaf = box((width - 2) * M, h * M, 4 * M, materials.door); leaf.position.set((width / 2) * M, h / 2 * M, 0);
        const knob = box(0.03, 0.03, 0.09, materials.frame, false); knob.position.set((width - 12) * M, 1.0, 0);
        pivot.add(leaf, knob); group.add(pivot);
    }

    function addItem(it) {
        const h = ITEM_H[it.cat] ?? 45;
        const topMat = new THREE.MeshStandardMaterial({ color: '#ffffff', roughness: 0.8 });
        const key = it.cat + '/' + it.file;
        const img = symbolImages[key] || (symbolImages[key] = loadSymbol(key));
        const apply = () => {
            const c = document.createElement('canvas'), res = 256;
            const ar = it.w / it.h; c.width = ar >= 1 ? res : Math.round(res * ar); c.height = ar >= 1 ? Math.round(res / ar) : res;
            const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height);
            x.drawImage(img, 0, 0, c.width, c.height);
            const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; topMat.map = t; topMat.needsUpdate = true;
        };
        if (img.complete && img.naturalWidth) apply(); else img.addEventListener('load', apply, { once: true });
        const mats = [materials.side, materials.side, topMat, materials.side, materials.side, materials.side];
        const m = new THREE.Mesh(new THREE.BoxGeometry(it.w * M, Math.max(h, 1) * M, it.h * M), mats);
        m.castShadow = h > 10; m.receiveShadow = true;
        m.position.set(it.x * M, Math.max(h, 1) / 2 * M, it.y * M);
        m.rotation.y = -(it.rot || 0) * Math.PI / 180;
        group.add(m);
    }
    function loadSymbol(key) {
        const img = new Image(); img.decoding = 'async';
        img.src = SYMBOL_BASE + key.split('/').map(encodeURIComponent).join('/');
        return img;
    }

    function makeLabel(r) {
        const name = r.name || 'Room';
        const c = document.createElement('canvas'), x = c.getContext('2d'), fs = 48;
        x.font = `800 ${fs}px Mulish, sans-serif`;
        const w = Math.ceil(x.measureText(name).width) + 40; c.width = w; c.height = fs + 30;
        x.font = `800 ${fs}px Mulish, sans-serif`;
        x.fillStyle = 'rgba(0,29,54,.82)'; x.beginPath(); x.roundRect(0, 0, w, c.height, 18); x.fill();
        x.fillStyle = '#fff'; x.textAlign = 'center'; x.textBaseline = 'middle'; x.fillText(name, w / 2, c.height / 2 + 2);
        const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace;
        const s = new THREE.Sprite(new THREE.SpriteMaterial({ map: t, depthTest: false, transparent: true }));
        const hgt = 0.32; s.scale.set(hgt * w / c.height, hgt, 1);
        let cx = 0, cy = 0; r.pts.forEach(p => { cx += p.x; cy += p.y; });
        s.position.set(cx / r.pts.length * M, BASE_WALL_H * opts.wallHeight * M + 0.4, cy / r.pts.length * M);
        s.renderOrder = 10;
        return s;
    }

    // ------------------------------------------------------------------ camera modes
    let mode = 'orbit';
    const walk = { yaw: 0, pitch: 0, keys: new Set(), joy: { x: 0, y: 0 }, look: null };
    const EYE = 1.6;
    function resetView() {
        if (mode === 'orbit') {
            camera.fov = 50; camera.updateProjectionMatrix();
            controls.target.copy(center);
            const k = Math.max(1, 0.85 / camera.aspect); // narrow (portrait) screens need the camera further back
            camera.position.set(center.x + span * 0.3 * k, span * 1.15 * k, center.z + span * 0.85 * k);
            controls.update();
        } else {
            camera.fov = 70; camera.updateProjectionMatrix();
            // Start inside the largest room, looking along its longer side.
            const rooms = (plan.rooms || []).filter(r => r.pts && r.pts.length >= 3);
            let start = { x: center.x, z: center.z };
            if (rooms.length) {
                const area = r => { let s = 0; r.pts.forEach((p, i) => { const q = r.pts[(i + 1) % r.pts.length]; s += p.x * q.y - q.x * p.y; }); return Math.abs(s); };
                const big = rooms.reduce((a, c) => (area(c) > area(a) ? c : a));
                const bx = big.pts.map(p => p.x), by = big.pts.map(p => p.y);
                const x0 = Math.min(...bx), x1 = Math.max(...bx), y0 = Math.min(...by), y1 = Math.max(...by);
                // Stand near one end of the room and look down its longer side.
                if (y1 - y0 >= x1 - x0) { start = { x: (x0 + x1) / 2 * M, z: (y1 - (y1 - y0) * 0.15) * M }; walk.yaw = 0; }
                else { start = { x: (x0 + (x1 - x0) * 0.15) * M, z: (y0 + y1) / 2 * M }; walk.yaw = -Math.PI / 2; }
            } else walk.yaw = 0;
            camera.position.set(start.x, EYE, start.z);
            walk.pitch = -0.08;
        }
    }
    function setMode(m) {
        mode = m;
        root.querySelectorAll('[data-mode]').forEach(bn => bn.classList.toggle('is-active', bn.dataset.mode === m));
        controls.enabled = m === 'orbit';
        stage.classList.toggle('walk', m === 'walk');
        const touch = matchMedia('(pointer: coarse)').matches;
        document.getElementById('joystick').hidden = !(m === 'walk' && touch);
        labelGroup.visible = m === 'orbit' && opts.labels;
        document.getElementById('plan-sub').textContent = m === 'orbit'
            ? 'Drag to rotate · right-drag to pan · scroll or pinch to zoom'
            : (touch ? 'Joystick to move · drag to look around' : 'W A S D or arrow keys to move · drag to look around');
        resetView();
    }
    root.querySelectorAll('[data-mode]').forEach(bn => bn.addEventListener('click', () => setMode(bn.dataset.mode)));

    // Walk: drag to look
    const cv = renderer.domElement;
    cv.addEventListener('pointerdown', e => { if (mode !== 'walk') return; walk.look = { x: e.clientX, y: e.clientY, yaw: walk.yaw, pitch: walk.pitch }; cv.setPointerCapture(e.pointerId); });
    cv.addEventListener('pointermove', e => {
        if (mode !== 'walk' || !walk.look) return;
        walk.yaw = walk.look.yaw - (e.clientX - walk.look.x) * 0.005;
        walk.pitch = Math.max(-1.2, Math.min(1.2, walk.look.pitch - (e.clientY - walk.look.y) * 0.005));
    });
    const endLook = () => { walk.look = null; };
    cv.addEventListener('pointerup', endLook); cv.addEventListener('pointercancel', endLook);
    document.addEventListener('keydown', e => { if (/INPUT|SELECT|TEXTAREA/.test(document.activeElement.tagName)) return; walk.keys.add(e.key.toLowerCase()); });
    document.addEventListener('keyup', e => walk.keys.delete(e.key.toLowerCase()));
    window.addEventListener('blur', () => walk.keys.clear());

    // Walk: touch joystick
    const joy = document.getElementById('joystick'), knob = joy.querySelector('span');
    let joyId = null;
    joy.addEventListener('pointerdown', e => { joyId = e.pointerId; joy.setPointerCapture(e.pointerId); moveJoy(e); });
    joy.addEventListener('pointermove', e => { if (e.pointerId === joyId) moveJoy(e); });
    const endJoy = () => { joyId = null; walk.joy.x = walk.joy.y = 0; knob.style.transform = ''; };
    joy.addEventListener('pointerup', endJoy); joy.addEventListener('pointercancel', endJoy);
    function moveJoy(e) {
        const r = joy.getBoundingClientRect(), max = r.width / 2 - 23;
        let dx = e.clientX - (r.left + r.width / 2), dy = e.clientY - (r.top + r.height / 2);
        const l = Math.hypot(dx, dy); if (l > max) { dx *= max / l; dy *= max / l; }
        knob.style.transform = `translate(${dx}px, ${dy}px)`;
        walk.joy.x = dx / max; walk.joy.y = dy / max;
    }

    function updateWalk(dt) {
        const k = walk.keys;
        let fwd = (k.has('w') || k.has('arrowup') ? 1 : 0) - (k.has('s') || k.has('arrowdown') ? 1 : 0) - walk.joy.y;
        let strafe = (k.has('d') ? 1 : 0) - (k.has('a') ? 1 : 0) + walk.joy.x;
        if (k.has('arrowleft')) walk.yaw += dt * 1.8;
        if (k.has('arrowright')) walk.yaw -= dt * 1.8;
        const speed = (k.has('shift') ? 3.2 : 1.6) * dt;
        const fx = -Math.sin(walk.yaw), fz = -Math.cos(walk.yaw);
        camera.position.x += (fx * fwd - fz * strafe) * speed;
        camera.position.z += (fz * fwd + fx * strafe) * speed;
        camera.position.y = EYE;
        camera.rotation.set(walk.pitch, walk.yaw, 0, 'YXZ');
    }

    // ------------------------------------------------------------------ settings panel
    const panel = document.getElementById('settings');
    function syncPanel() {
        document.getElementById('wall-height').value = opts.wallHeight;
        document.getElementById('wall-height-label').textContent = '(' + (BASE_WALL_H * opts.wallHeight / 100).toFixed(2) + ' m)';
        document.getElementById('floor-finish').value = opts.floor;
        root.querySelectorAll('#wall-colors [data-color]').forEach(s => s.classList.toggle('is-active', s.dataset.color === opts.wallColor));
        root.querySelectorAll('[data-opt]').forEach(c => { c.checked = !!opts[c.dataset.opt]; });
    }
    function changed(rebuild = true) {
        saveOpts(); syncPanel();
        renderer.shadowMap.enabled = opts.shadows; sun.castShadow = opts.shadows;
        scene.traverse(o => { if (o.material) [].concat(o.material).forEach(m => { m.needsUpdate = true; }); });
        if (rebuild) build();
        labelGroup.visible = mode === 'orbit' && opts.labels;
    }
    document.getElementById('wall-height').addEventListener('input', e => { opts.wallHeight = +e.target.value; changed(); });
    document.getElementById('floor-finish').addEventListener('change', e => { opts.floor = e.target.value; changed(); });
    document.getElementById('wall-colors').addEventListener('click', e => { const s = e.target.closest('[data-color]'); if (!s) return; opts.wallColor = s.dataset.color; materials.wall.color.set(opts.wallColor); changed(false); });
    root.querySelectorAll('[data-opt]').forEach(c => c.addEventListener('change', () => { opts[c.dataset.opt] = c.checked; changed(); }));
    root.querySelector('[data-close="settings"]').addEventListener('click', () => { panel.hidden = true; });

    root.addEventListener('click', e => {
        const bn = e.target.closest('[data-cmd]'); if (!bn) return;
        const cmd = bn.dataset.cmd;
        if (cmd === 'settings') panel.hidden = !panel.hidden;
        else if (cmd === 'reset') resetView();
        else if (cmd === 'defaults') { opts = Object.assign({}, DEFAULTS); changed(); }
        else if (cmd === 'snapshot') {
            // Read the pixels in the same task as the render (the drawing buffer isn't preserved).
            renderer.render(scene, camera);
            const dataUrl = renderer.domElement.toDataURL('image/png');
            fetch(dataUrl).then(r => r.blob()).then(blob => FP.downloadBlob(blob, (plan.name || 'floor-plan').replace(/[^\w\- ]+/g, '_') + '-3d.png'));
        }
    });

    // ------------------------------------------------------------------ loop
    function resize() {
        const r = stage.getBoundingClientRect();
        renderer.setSize(r.width, r.height, false);
        camera.aspect = r.width / Math.max(1, r.height); camera.updateProjectionMatrix();
    }
    window.addEventListener('resize', resize);
    const clock = new THREE.Clock();
    renderer.setAnimationLoop(() => {
        const dt = Math.min(clock.getDelta(), 0.1);
        if (mode === 'orbit') controls.update(); else updateWalk(dt);
        renderer.render(scene, camera);
    });

    // Snapshot reads the canvas right after a render, so no preserveDrawingBuffer is needed.
    renderer.domElement.addEventListener('webglcontextlost', e => { e.preventDefault(); flash('3D graphics were reset by the browser. Reload the page to continue.'); });
    function flash(text) { const h = document.getElementById('hint'); h.textContent = text; h.hidden = false; clearTimeout(flash.t); flash.t = setTimeout(() => { h.hidden = true; }, 5000); }

    syncPanel(); changed(); resize(); setMode('orbit');
    flash('Switch to Walk to look around inside. Use Settings to change walls, floors and furniture.');
    window.FP3D = { scene, camera, controls, setMode, opts: () => opts };
}
