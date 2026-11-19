/* Floor Plan Designer — live 3D house in the home page hero.
 *
 * Builds a small furnished house with three.js and the web app's own furniture models
 * (public/assets/app/furniture3d.js), plays a build-up animation (floors, then walls rising,
 * then furniture dropping in), and afterwards turns slowly, leans toward the pointer and can
 * be dragged to spin. The hero image stays in place until the first frame is drawn, so
 * browsers without WebGL, Save-Data users and any loading error simply keep the image.
 * With "reduce motion" the house is shown finished and only moves when dragged. */
(function () {
    'use strict';

    var host = document.querySelector('[data-hero-3d]');
    if (!host || !window.WebGLRenderingContext) return;
    var conn = navigator.connection || {};
    if (conn.saveData) return;

    function start() {
        Promise.all([import('three'), import(host.getAttribute('data-furniture')), import('three/addons/RoomEnvironment.js')])
            .then(function (mods) { build(mods[0], mods[1].createFurniture, mods[2].RoomEnvironment); })
            .catch(function () { /* keep the static image */ });
    }
    // Wait until the page has loaded and the browser is idle, so the hero text and image paint first.
    function whenIdle() { (window.requestIdleCallback || function (f) { setTimeout(f, 200); })(start, { timeout: 2500 }); }
    if (document.readyState === 'complete') whenIdle(); else window.addEventListener('load', whenIdle, { once: true });

    function build(THREE, createFurniture, RoomEnvironment) {
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var visual = host.closest('.hero-visual');
        var renderer;
        try {
            renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
        } catch (e) { return; }
        renderer.setClearColor(0x000000, 0);
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 0.96;
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        var canvas = renderer.domElement;
        canvas.setAttribute('aria-hidden', 'true');
        host.appendChild(canvas);

        var scene = new THREE.Scene();
        var pmrem = new THREE.PMREMGenerator(renderer);
        scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
        pmrem.dispose();
        scene.add(new THREE.HemisphereLight('#f4f8ff', '#3b4a63', 0.55));
        var sun = new THREE.DirectionalLight('#fff1dc', 2.2);
        sun.position.set(-7, 13, -6); // from the front-left, so shadows fall away from the viewer
        sun.castShadow = true;
        sun.shadow.mapSize.set(1024, 1024);
        sun.shadow.bias = -0.0004;
        sun.shadow.radius = 4;
        var sc = sun.shadow.camera; sc.left = -9; sc.right = 9; sc.top = 9; sc.bottom = -9; sc.near = 1; sc.far = 40;
        scene.add(sun);
        var fill = new THREE.DirectionalLight('#9cc7ff', 0.45);
        fill.position.set(9, 6, 7);
        scene.add(fill);

        var house = new THREE.Group();
        scene.add(house);
        var steps = []; // build-up animation: {obj, at, dur, kind}

        // ------------------------------------------------------------- materials + helpers
        function std(color, rough, extra) { return new THREE.MeshStandardMaterial(Object.assign({ color: color, roughness: rough == null ? 0.85 : rough }, extra || {})); }
        var mWall = std('#f7f3ec', 0.92), mCap = std('#2f3b4d', 0.6), mGlass = std('#9fd3ff', 0.05, { transparent: true, opacity: 0.38, metalness: 0.2 });
        var mSlab = std('#e4ddd1', 0.95), mGrass = std('#5fa047', 1), mPath = std('#e2ddd3', 0.85), mDoor = std('#a77446', 0.55);
        var mSoil = std('#b98a5e', 1), mSoilDark = std('#8c6544', 1), mDeck = std('#d9cbb4', 0.8);

        // Floor finishes drawn on small canvases: wood planks, tiles and carpet.
        var aniso = Math.min(8, renderer.capabilities.getMaxAnisotropy());
        function canvasTex(draw) {
            var c = document.createElement('canvas'); c.width = c.height = 256;
            draw(c.getContext('2d'), 256);
            var t = new THREE.CanvasTexture(c);
            t.colorSpace = THREE.SRGBColorSpace; t.wrapS = t.wrapT = THREE.RepeatWrapping; t.anisotropy = aniso;
            return t;
        }
        function shade(hex, f) { // lighten (f > 0) or darken (f < 0) a #rrggbb colour
            var n = parseInt(hex.slice(1), 16), r = n >> 16, g = (n >> 8) & 255, b = n & 255;
            var k = function (v) { return Math.round(f > 0 ? v + (255 - v) * f : v * (1 + f)); };
            return 'rgb(' + k(r) + ',' + k(g) + ',' + k(b) + ')';
        }
        var rand = (function () { var x = 7; return function () { x = (x * 16807) % 2147483647; return (x - 1) / 2147483646; }; })();
        var FINISH = {
            wood: function (base) { return canvasTex(function (x, n) {
                var rows = 8, rh = n / rows;
                for (var r = 0; r < rows; r++) {
                    var off = (r % 2) * n / 3 + rand() * 40, len = n / 1.6;
                    for (var s = -1; s < 3; s++) {
                        x.fillStyle = shade(base, (rand() - 0.5) * 0.16);
                        x.fillRect(off + s * len, r * rh, len, rh);
                        x.fillStyle = 'rgba(60,35,15,.35)'; x.fillRect(off + s * len, r * rh, 1.5, rh);
                    }
                    x.fillStyle = 'rgba(60,35,15,.3)'; x.fillRect(0, r * rh, n, 1.5);
                    for (var k = 0; k < 6; k++) { x.fillStyle = 'rgba(80,50,25,.07)'; x.fillRect(0, r * rh + rand() * rh, n, 1); }
                }
            }); },
            tile: function (base, grout, count) { return canvasTex(function (x, n) {
                x.fillStyle = grout; x.fillRect(0, 0, n, n);
                var t = n / count;
                for (var i = 0; i < count; i++) for (var j = 0; j < count; j++) {
                    x.fillStyle = shade(base, (rand() - 0.5) * 0.05);
                    x.fillRect(i * t + 1.5, j * t + 1.5, t - 3, t - 3);
                }
            }); },
            carpet: function (base) { return canvasTex(function (x, n) {
                x.fillStyle = base; x.fillRect(0, 0, n, n);
                for (var i = 0; i < 2600; i++) { x.fillStyle = rand() > 0.5 ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)'; x.fillRect(rand() * n, rand() * n, 2, 2); }
            }); },
        };
        function floorMat(r) {
            var tex = r[4] === 'wood' ? FINISH.wood(r[5]) : r[4] === 'tile' ? FINISH.tile(r[5], r[6], r[7]) : FINISH.carpet(r[5]);
            var tile = r[4] === 'wood' ? 2.2 : r[4] === 'tile' ? 1.6 : 1.5; // metres covered by one copy of the texture
            tex.repeat.set((r[2] - r[0]) / tile, (r[3] - r[1]) / tile);
            return std('#ffffff', r[4] === 'tile' ? 0.35 : r[4] === 'wood' ? 0.6 : 1, { map: tex });
        }

        function box(w, h, d, mat, x, y, z, parent) {
            var g = new THREE.BoxGeometry(w, h, d);
            g.translate(0, h / 2, 0); // origin at the base, so scaling y "grows" it upward
            var m = new THREE.Mesh(g, mat);
            m.position.set(x, y, z);
            m.castShadow = true; m.receiveShadow = true;
            (parent || house).add(m);
            return m;
        }
        function step(obj, at, dur, kind) { obj.visible = reduce; steps.push({ obj: obj, at: at, dur: dur, kind: kind, y: obj.position.y }); }

        // ------------------------------------------------------------- the plan (metres, centred)
        var H = 1.15, T = 0.15, SILL = 0.55;
        var rooms = [ // x1, z1, x2, z2, finish, colour(s), name
            [-5, -4, 1, 0, 'wood', '#c99561', null, 0, 'Living'],
            [1, -4, 5, 0, 'tile', '#ece8e1', '#cbc3b6', 4, 'Kitchen'],
            [-5, 0, -0.6, 4, 'carpet', '#b4c3d4', null, 0, 'Bedroom'],
            [-0.6, 0, 1.6, 4, 'tile', '#e3eef3', '#b9cad3', 6, 'Bath'],
            [1.6, 0, 5, 4, 'wood', '#d9b98f', null, 0, 'Bedroom 2'],
        ];
        // Walls: [x1, z1, x2, z2, openings [[centre along wall from x1/z1, width, 'door'|'window']]]
        var walls = [
            [-5, -4, 5, -4, [[2, 1.6, 'window'], [4.7, 1.0, 'door'], [8.2, 1.2, 'window']]],
            [-5, 4, 5, 4, [[2.2, 1.6, 'window'], [5.5, 0.6, 'window'], [8.3, 1.4, 'window']]],
            [-5, -4, -5, 4, [[2, 1.4, 'window'], [6.2, 1.2, 'window']]],
            [5, -4, 5, 4, [[2, 1.0, 'window'], [6, 1.2, 'window']]],
            [-5, 0, 5, 0, [[1.5, 3.4, 'open'], [3.6, 0.9, 'door'], [5.5, 0.8, 'door'], [7.4, 0.9, 'door']]],
            [-0.6, 0, -0.6, 4, []],
            [1.6, 0, 1.6, 4, []],
            [1, -4, 1, 0, [[2.6, 2.6, 'open']]],
        ];

        // Ground: a round grass island with layered soil below (like an architect's diorama),
        // a soft shadow underneath so it floats, stepping stones to the door and a pool deck.
        var R = 8.8;
        var ground = new THREE.Group(); house.add(ground);
        function cyl(r1, r2, h, mat, y) {
            var m = new THREE.Mesh(new THREE.CylinderGeometry(r1, r2, h, 72), mat);
            m.position.y = y; m.receiveShadow = true; ground.add(m); return m;
        }
        cyl(R, R, 0.08, mGrass, -0.05);
        cyl(R - 0.02, R - 0.3, 0.85, mSoil, -0.515);
        cyl(R - 0.3, R - 0.9, 0.4, mSoilDark, -1.14);
        var shadowTex = canvasTex(function (x, n) {
            var g = x.createRadialGradient(n / 2, n / 2, 0, n / 2, n / 2, n / 2);
            g.addColorStop(0, 'rgba(0,10,30,.55)'); g.addColorStop(0.55, 'rgba(0,10,30,.25)'); g.addColorStop(1, 'rgba(0,10,30,0)');
            x.fillStyle = g; x.fillRect(0, 0, n, n);
        });
        var floatShadow = new THREE.Mesh(new THREE.PlaneGeometry(R * 2.3, R * 2.3), new THREE.MeshBasicMaterial({ map: shadowTex, transparent: true, depthWrite: false }));
        floatShadow.rotation.x = -Math.PI / 2; floatShadow.position.y = -2.1; ground.add(floatShadow);
        [-4.55, -5.35, -6.15, -6.95].forEach(function (z, i) { box(1.0, 0.04, 0.55, mPath, -0.3 + (i % 2 ? 0.08 : -0.08), -0.02, z, ground); });
        box(2.3, 0.03, 4.1, mDeck, 6.95, -0.02, 0.4, ground);
        step(ground, 0, 0.7, 'pop');

        var slab = box(10.3, 0.12, 8.3, mSlab, 0, -0.1, 0);
        step(slab, 0.15, 0.5, 'grow');
        rooms.forEach(function (r, i) {
            var f = box(r[2] - r[0] - 0.02, 0.03, r[3] - r[1] - 0.02, floorMat(r), (r[0] + r[2]) / 2, 0.02, (r[1] + r[3]) / 2);
            step(f, 0.35 + i * 0.09, 0.45, 'grow');
        });
        // Living-room rug
        step(box(2.6, 0.02, 1.9, std('#4f6f99', 1, { map: FINISH.carpet('#4f6f99') }), -2.1, 0.05, -1.75), 0.8, 0.4, 'grow');

        var wi = 0;
        walls.forEach(function (w) {
            var horiz = w[1] === w[3], len = horiz ? w[2] - w[0] : w[3] - w[1];
            var cuts = w[4].slice().sort(function (a, b) { return a[0] - b[0]; });
            var pos = 0, pieces = [];
            cuts.forEach(function (c) {
                var a = c[0] - c[1] / 2, b = c[0] + c[1] / 2;
                if (a > pos) pieces.push([pos, a, 'wall']);
                pieces.push([a, b, c[2]]);
                pos = b;
            });
            if (pos < len) pieces.push([pos, len, 'wall']);
            pieces.forEach(function (p) {
                var mid = (p[0] + p[1]) / 2, l = p[1] - p[0] + (p[2] === 'wall' ? T : 0);
                var x = horiz ? w[0] + mid : w[0], z = horiz ? w[1] : w[1] + mid;
                var sx = horiz ? l : T, sz = horiz ? T : l;
                var at = 0.75 + (wi++) * 0.03;
                if (p[2] === 'wall') {
                    var m = box(sx, H, sz, mWall, x, 0, z);
                    step(m, at, 0.7, 'grow');
                    step(box(sx + 0.012, 0.05, sz + 0.012, mCap, x, H, z), at + 0.55, 0.3, 'fade');
                } else if (p[2] === 'window') {
                    step(box(sx, SILL, sz, mWall, x, 0, z), at, 0.6, 'grow');
                    var g = box(horiz ? sx : 0.04, H - SILL, horiz ? 0.04 : sz, mGlass, x, SILL, z);
                    g.castShadow = false;
                    step(g, at + 0.4, 0.5, 'grow');
                } else if (p[2] === 'door' && horiz) {
                    // Door leaf, hinged at the left jamb and standing open into the room behind it.
                    var leafW = p[1] - p[0] - 0.05, hinge = new THREE.Group();
                    hinge.position.set(w[0] + p[0] + 0.025, 0, w[1]);
                    hinge.rotation.y = -1.15;
                    box(leafW, H - 0.03, 0.04, mDoor, leafW / 2, 0, 0, hinge);
                    house.add(hinge);
                    step(hinge, at + 0.9, 0.5, 'grow');
                }
            });
        });

        // Furniture from the web app's library (centre x/z in metres, size in cm, turn in degrees).
        var items = [
            ['Sofa', 'furniture', -2.1, -0.55, 230, 95, 180],
            ['Coffee table', 'tables', -2.1, -1.8, 110, 60, 0],
            ['Side table', 'tables', -2.1, -3.65, 170, 40, 0],
            ['Side table', 'tables', -4.45, -0.5, 50, 50, 0],
            ['Potted plant', 'garden', -4.45, -3.45, 55, 55, 0],
            ['Refrigerator', 'appliance', 1.5, -3.55, 75, 70, 0],
            ['Base cabinet', 'custom', 2.2, -3.6, 60, 60, 0],
            ['Stove 4 burner', 'cooking', 2.8, -3.6, 60, 60, 0],
            ['Base cabinet', 'custom', 3.4, -3.6, 60, 60, 0],
            ['Double sink', 'sinks', 4.1, -3.6, 80, 60, 0],
            ['Base cabinet', 'custom', 4.65, -3.6, 50, 60, 0],
            ['Dining table 4 chairs', 'tables', 3.1, -1.45, 150, 150, 0],
            ['Double bed', 'furniture', -2.8, 2.9, 165, 205, 180],
            ['Side table', 'tables', -4.15, 3.6, 45, 40, 0],
            ['Side table', 'tables', -1.45, 3.6, 45, 40, 0],
            ['Potted plant', 'garden', -4.5, 0.55, 50, 50, 0],
            ['Bathtub', 'bathtub', 0.5, 3.5, 170, 75, 0],
            ['Toilet', 'toilet', 1.25, 1.35, 40, 65, -90],
            ['Vanity sink', 'sinks', -0.27, 1.1, 85, 50, 90],
            ['Single bed', 'furniture', 4.2, 2.85, 100, 205, 180],
            ['Desk', 'desk', 2.6, 3.55, 120, 60, 180],
            ['Potted plant', 'garden', 2.0, 0.55, 45, 45, 0],
            ['Tree', 'garden', -6.7, -3.6, 190, 190, 0],
            ['Pine tree', 'garden', 6.6, -3.2, 150, 150, 0],
            ['Tree', 'garden', 6.4, 3.9, 210, 210, 0],
            ['Palm tree', 'garden', -6.6, 3.4, 170, 170, 0],
            ['Hedge', 'garden', -2.6, -5.35, 280, 50, 0],
            ['Hedge', 'garden', 2.4, -5.35, 280, 50, 0],
            ['Pool', 'pool', 6.95, 0.4, 150, 320, 0],
            ['Bush', 'garden', -7.3, 0.2, 75, 75, 0],
            ['Bush', 'garden', 2.8, 6.9, 80, 80, 0],
            ['Bush', 'garden', -3.2, 6.8, 70, 70, 0],
        ];
        items.forEach(function (it, i) {
            var model = null;
            try { model = createFurniture({ id: 'hero' + i, label: it[0], cat: it[1], file: '', w: it[4], h: it[5] }); } catch (e) { model = null; }
            if (!model) return;
            model.traverse(function (o) { if (o.isMesh) { o.castShadow = true; o.receiveShadow = true; } });
            model.position.set(it[2], 0, it[3]);
            model.rotation.y = -it[6] * Math.PI / 180;
            house.add(model);
            var outdoor = (it[1] === 'garden' && !/potted/i.test(it[0])) || it[1] === 'pool';
            step(model, outdoor ? 1.0 + (i % 6) * 0.12 : 1.55 + i * 0.055, 0.75, outdoor ? 'pop' : 'drop');
        });

        // ------------------------------------------------------------- camera
        var camera = new THREE.PerspectiveCamera(30, 1, 0.5, 200);
        var target = new THREE.Vector3(0, 0, 0);
        var dist = 30;
        var base = { yaw: Math.PI + Math.PI / 4.6, pitch: 0.9 }; // look from the front (street) side
        var view = { yaw: base.yaw - (reduce ? 0 : 0.75), pitch: base.pitch - (reduce ? 0 : 0.22), zoom: reduce ? 1 : 1.3 };
        var pointer = { x: 0, y: 0, tx: 0, ty: 0 };
        var drag = { on: false, x: 0, offset: 0, vel: 0 };

        function resize() {
            var w = host.clientWidth, h = host.clientHeight;
            if (!w || !h) return;
            renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, w < 520 ? 1.5 : 2));
            renderer.setSize(w, h, false);
            camera.aspect = w / h;
            frame(w, h);
            labelsOn = w >= 420;
            labelLayer.classList.toggle('is-hidden', !labelsOn);
            if (!running) render(performance.now());
        }

        // Centre the island in the canvas (a little right on wide layouts, clear of the phone mock-up),
        // leave a strip at the bottom for the "Drag to rotate" hint, then move in as close as it fits.
        var BOUNDS = { x: 0.94, top: 0.94, bottom: -0.8 };
        function frame(w, h) {
            var want = { x: w >= 520 ? 0.07 : 0, y: (BOUNDS.top + BOUNDS.bottom) / 2 };
            var ox = 0, oy = 0;
            for (var pass = 0; pass < 3; pass++) {
                camera.setViewOffset(w, h, ox, oy, w, h);
                camera.updateProjectionMatrix();
                dist = fitDistance();
                var b = extent(dist); // where the island lands in NDC with this offset
                ox += ((b.minX + b.maxX) / 2 - want.x) * w / 2;
                oy += (want.y - (b.minY + b.maxY) / 2) * h / 2;
            }
            camera.setViewOffset(w, h, ox, oy, w, h);
            camera.updateProjectionMatrix();
            dist = fitDistance();
        }

        // Closest camera distance at which the island, walls and tree tops stay inside the canvas
        // from every angle the house can turn to (sway, pointer lean and drag-to-spin).
        var FIT_POINTS = (function () {
            var pts = [], i, a;
            for (i = 0; i < 32; i++) {
                a = i / 32 * Math.PI * 2;
                pts.push(new THREE.Vector3(Math.cos(a) * R, 0, Math.sin(a) * R));
                pts.push(new THREE.Vector3(Math.cos(a) * (R - 0.3), -0.94, Math.sin(a) * (R - 0.3)));
                pts.push(new THREE.Vector3(Math.cos(a) * (R - 0.9), -1.34, Math.sin(a) * (R - 0.9)));
                pts.push(new THREE.Vector3(Math.cos(a) * 7, -2.1, Math.sin(a) * 7)); // visible part of the floating shadow
            }
            [[-6.7, -3.6], [6.6, -3.2], [6.4, 3.9], [-6.6, 3.4]].forEach(function (t) { pts.push(new THREE.Vector3(t[0], 4.6, t[1])); });
            [[-5.1, -4.1], [5.1, -4.1], [5.1, 4.1], [-5.1, 4.1]].forEach(function (c) { pts.push(new THREE.Vector3(c[0], H, c[1])); });
            return pts;
        })();
        var fitCam = new THREE.PerspectiveCamera(), probe = new THREE.Vector3();
        function placeCamera(cam, yaw, pitch, r) {
            cam.position.set(
                target.x + r * Math.sin(pitch) * Math.sin(yaw),
                target.y + r * Math.cos(pitch),
                target.z + r * Math.sin(pitch) * Math.cos(yaw));
            cam.lookAt(target);
            cam.updateMatrixWorld();
        }
        function fitsAt(yaw, pitch, r) {
            placeCamera(fitCam, yaw, pitch, r);
            for (var i = 0; i < FIT_POINTS.length; i++) {
                probe.copy(FIT_POINTS[i]).project(fitCam);
                if (Math.abs(probe.x) > BOUNDS.x || probe.y > BOUNDS.top || probe.y < BOUNDS.bottom) return false;
            }
            return true;
        }
        function fitDistance() {
            fitCam.copy(camera);
            var need = 5;
            for (var s = 0; s < 24; s++) {
                var yaw = s / 24 * Math.PI * 2;
                [base.pitch - 0.1, base.pitch + 0.1].forEach(function (pitch) {
                    if (fitsAt(yaw, pitch, need)) return;
                    var lo = need, hi = 150;
                    for (var k = 0; k < 22; k++) { var mid = (lo + hi) / 2; if (fitsAt(yaw, pitch, mid)) hi = mid; else lo = mid; }
                    need = hi;
                });
            }
            return need;
        }
        function sampleViews(fn) {
            for (var s = 0; s < 24; s++) { fn(s / 24 * Math.PI * 2, base.pitch - 0.1); fn(s / 24 * Math.PI * 2, base.pitch + 0.1); }
        }
        function extent(r) {
            var b = { minX: 1e9, maxX: -1e9, minY: 1e9, maxY: -1e9 };
            sampleViews(function (yaw, pitch) {
                placeCamera(fitCam, yaw, pitch, r);
                FIT_POINTS.forEach(function (pt) {
                    probe.copy(pt).project(fitCam);
                    b.minX = Math.min(b.minX, probe.x); b.maxX = Math.max(b.maxX, probe.x);
                    b.minY = Math.min(b.minY, probe.y); b.maxY = Math.max(b.maxY, probe.y);
                });
            });
            return b;
        }

        // ------------------------------------------------------------- room labels
        // Small tags with each room's name and floor area that follow the 3D view.
        var labelLayer = document.createElement('div');
        labelLayer.className = 'hero-3d-labels';
        host.appendChild(labelLayer);
        var labelsOn = true, labelPos = new THREE.Vector3();
        var labels = rooms.map(function (r, i) {
            var el = document.createElement('span');
            el.className = 'room-tag';
            el.style.transitionDelay = (i * 0.12) + 's';
            var area = (r[2] - r[0] - T) * (r[3] - r[1] - T);
            el.innerHTML = '<b></b><span></span>';
            el.firstChild.textContent = r[8];
            el.lastChild.textContent = area.toFixed(1) + ' m²';
            labelLayer.appendChild(el);
            return { el: el, at: new THREE.Vector3((r[0] + r[2]) / 2, H + 0.35, (r[1] + r[3]) / 2), x: 0, y: 0, d: 0 };
        });
        function placeLabels() {
            if (!labelsOn) return;
            var w = host.clientWidth, h = host.clientHeight;
            labels.forEach(function (l) {
                labelPos.copy(l.at).project(camera);
                l.x = (labelPos.x + 1) / 2 * w; l.y = (1 - labelPos.y) / 2 * h; l.d = labelPos.z;
                l.el.style.transform = 'translate(' + l.x.toFixed(1) + 'px,' + l.y.toFixed(1) + 'px) translate(-50%, -100%)';
            });
            // Nearest rooms first; a tag that would overlap one already shown fades out until there is room.
            var shownTags = [];
            labels.slice().sort(function (a, b) { return a.d - b.d; }).forEach(function (l) {
                var bw = (l.w || (l.w = l.el.offsetWidth)) / 2 + 4, bh = l.h || (l.h = l.el.offsetHeight);
                var clash = shownTags.some(function (o) { return Math.abs(o.x - l.x) < bw + o.w / 2 + 4 && Math.abs(o.y - l.y) < bh + 4; });
                l.el.classList.toggle('is-crowded', clash);
                if (!clash) shownTags.push(l);
            });
        }

        // ------------------------------------------------------------- animation
        var ease = {
            out: function (t) { return 1 - Math.pow(1 - t, 3); },
            back: function (t) { var c = 1.6; return 1 + (c + 1) * Math.pow(t - 1, 3) + c * Math.pow(t - 1, 2); },
            inOut: function (t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; },
        };
        var t0 = null, introDone = reduce, INTRO = 0;
        steps.forEach(function (s) { INTRO = Math.max(INTRO, s.at + s.dur); });
        function applySteps(t) {
            var all = true;
            steps.forEach(function (s) {
                var p = Math.min(Math.max((t - s.at) / s.dur, 0), 1);
                if (p < 1) all = false;
                s.obj.visible = p > 0;
                if (s.kind === 'grow') s.obj.scale.y = Math.max(ease.out(p), 0.001);
                else if (s.kind === 'pop') s.obj.scale.setScalar(Math.max(ease.back(p), 0.001));
                else if (s.kind === 'drop') { s.obj.position.y = s.y + (1 - ease.out(p)) * 2.4; s.obj.scale.setScalar(0.75 + 0.25 * ease.back(p)); }
                else if (s.kind === 'fade') s.obj.scale.y = Math.max(p, 0.001);
            });
            return all;
        }

        var running = false, visible = true, raf = 0, last = 0, idleT = 0;
        function render(now) {
            if (!reduce) {
                if (t0 === null) t0 = now;
                var t = (now - t0) / 1000;
                if (!introDone) introDone = applySteps(t);
                var dt = Math.min((now - (last || now)) / 1000, 0.05);
                if (introDone) idleT += dt;
                var k = ease.inOut(Math.min(t / (INTRO + 0.4), 1));
                // Ease the camera in from a wider, lower angle, then sway gently.
                view.yaw = (base.yaw - 0.75) + 0.75 * k + Math.sin(idleT * 0.32) * 0.28 * Math.min(idleT / 2, 1);
                view.pitch = (base.pitch - 0.22) + 0.22 * k + Math.sin(idleT * 0.21) * 0.04;
                view.zoom = 1.3 - 0.3 * k;
                pointer.x += (pointer.tx - pointer.x) * 0.06;
                pointer.y += (pointer.ty - pointer.y) * 0.06;
                if (!drag.on) { drag.offset += drag.vel; drag.vel *= 0.94; }
            }
            last = now;
            var yaw = view.yaw + drag.offset + pointer.x * 0.22;
            var pitch = Math.min(Math.max(view.pitch - pointer.y * 0.09, 0.45), 1.25);
            placeCamera(camera, yaw, pitch, dist * view.zoom);
            renderer.render(scene, camera);
            placeLabels();
            if (!visual.classList.contains('is-3d')) {
                visual.classList.add('is-3d');
                if (!reduce) setTimeout(function () { visual.classList.add('is-3d-ready'); }, (INTRO + 0.2) * 1000);
                else visual.classList.add('is-3d-ready');
            }
        }
        function loop(now) {
            raf = 0;
            if (!running) return;
            render(now);
            raf = requestAnimationFrame(loop);
        }
        function setRunning(on) {
            on = on && !reduce && visible && !document.hidden;
            if (on === running) return;
            running = on;
            last = 0;
            if (on && !raf) raf = requestAnimationFrame(loop);
        }

        // ------------------------------------------------------------- input
        var hero = host.closest('.hero') || host;
        if (!reduce && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            hero.addEventListener('pointermove', function (e) {
                if (drag.on) return;
                var b = hero.getBoundingClientRect();
                pointer.tx = ((e.clientX - b.left) / b.width - 0.5) * 2;
                pointer.ty = ((e.clientY - b.top) / b.height - 0.5) * 2;
            });
            hero.addEventListener('pointerleave', function () { pointer.tx = 0; pointer.ty = 0; });
        }
        canvas.addEventListener('pointerdown', function (e) {
            drag.on = true; drag.x = e.clientX; drag.vel = 0;
            visual.classList.add('is-dragging');
            canvas.setPointerCapture(e.pointerId);
        });
        canvas.addEventListener('pointermove', function (e) {
            if (!drag.on) return;
            var d = (e.clientX - drag.x) * 0.009;
            drag.x = e.clientX; drag.offset -= d; drag.vel = -d;
            if (!running) render(performance.now());
        });
        function endDrag() { drag.on = false; visual.classList.remove('is-dragging'); }
        canvas.addEventListener('pointerup', endDrag);
        canvas.addEventListener('pointercancel', endDrag);

        if ('ResizeObserver' in window) new ResizeObserver(resize).observe(host);
        else window.addEventListener('resize', resize);
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (en) { visible = en[0].isIntersecting; setRunning(true); }).observe(host);
        }
        document.addEventListener('visibilitychange', function () { setRunning(true); });

        resize();
        if (reduce) render(performance.now());
        setRunning(true);
    }
})();
