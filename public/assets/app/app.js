/* Floor Plan Designer web app — shared browser code.
 * Plans live in this browser's localStorage and are backed up to the server
 * (/api/v1/plans.php) under a random, private id for this browser. Notes stay local. */
(function () {
    'use strict';

    // ---------------------------------------------------------------- storage
    var LIST_KEY = 'fpd_plans';
    var PLAN_PREFIX = 'fpd_plan_';

    function read(key, fallback) {
        try { var v = localStorage.getItem(key); return v ? JSON.parse(v) : fallback; } catch (e) { return fallback; }
    }
    function write(key, value) {
        try { localStorage.setItem(key, JSON.stringify(value)); return true; } catch (e) { alert('Could not save: your browser storage is full or blocked.'); return false; }
    }

    var FPStore = {
        list: function () {
            var l = read(LIST_KEY, []);
            return Array.isArray(l) ? l.sort(function (a, b) { return (b.modifiedAt || '').localeCompare(a.modifiedAt || ''); }) : [];
        },
        get: function (id) { return read(PLAN_PREFIX + id, null); },
        save: function (plan) {
            var now = new Date().toISOString();
            plan.id = plan.id || ('plan_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7));
            plan.createdAt = plan.createdAt || now;
            plan.modifiedAt = now;
            if (!write(PLAN_PREFIX + plan.id, plan)) return null;
            var l = FPStore.list().filter(function (p) { return p.id !== plan.id; });
            l.push({ id: plan.id, name: plan.name, createdAt: plan.createdAt, modifiedAt: plan.modifiedAt });
            write(LIST_KEY, l);
            FPSync.push(plan);
            return plan;
        },
        remove: function (id) {
            FPSync.remove(id);
            try { localStorage.removeItem(PLAN_PREFIX + id); } catch (e) { /* ignore */ }
            write(LIST_KEY, FPStore.list().filter(function (p) { return p.id !== id; }));
        },
        rename: function (id, name) {
            var p = FPStore.get(id); if (!p) return;
            p.name = name; FPStore.save(p);
        },
        duplicate: function (id) {
            var p = FPStore.get(id); if (!p) return null;
            var copy = JSON.parse(JSON.stringify(p));
            delete copy.id; delete copy.createdAt;
            copy.name = 'Copy of ' + p.name;
            return FPStore.save(copy);
        },
        download: function (id) {
            var p = FPStore.get(id); if (!p) return;
            FP.downloadBlob(new Blob([JSON.stringify(p, null, 2)], { type: 'application/json' }), (p.name || 'floor-plan').replace(/[^\w\- ]+/g, '_') + '.json');
        },
        importFile: function (file, done) {
            var r = new FileReader();
            r.onload = function () {
                try {
                    var p = JSON.parse(r.result);
                    if (!p || !Array.isArray(p.walls)) throw new Error('not a plan');
                    delete p.id; delete p.createdAt;
                    p.name = p.name || file.name.replace(/\.json$/i, '');
                    done(FPStore.save(p));
                } catch (e) { alert('This file is not a Floor Plan Designer web plan (.json).'); }
            };
            r.readAsText(file);
        }
    };

    // ---------------------------------------------------------------- helpers
    var FP = {
        url: function (path) { return (document.documentElement.getAttribute('data-base') || '') + path; },
        esc: function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); },
        relTime: function (iso) {
            var t = new Date(iso).getTime(); if (!t) return '';
            var m = Math.round((Date.now() - t) / 60000);
            if (m < 1) return 'Just now';
            if (m < 60) return m + ' min ago';
            if (m < 1440) return Math.round(m / 60) + ' h ago';
            if (m < 10080) return Math.round(m / 1440) + ' d ago';
            return new Date(t).toLocaleDateString();
        },
        downloadBlob: function (blob, name) {
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob); a.download = name;
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
        },
        settings: function () {
            return Object.assign({ units: 'metric', grid: true, snap: true, dimensions: true, wallThickness: 15 }, read('fpd_settings', {}));
        },
        saveSettings: function (s) { write('fpd_settings', s); }
    };
    // ---------------------------------------------------------------- server backup
    // Each browser gets a random 128-bit id, kept only in this browser; the server
    // stores a hash of it, so only this browser can update or delete its plans.
    var FPSync = (function () {
        var timers = {}, pending = {}, SYNC_KEY = 'fpd_synced';
        function clientId() {
            var id = read('fpd_client', '');
            if (!/^[a-f0-9]{32}$/.test(id)) {
                var b = new Uint8Array(16); (window.crypto || window.msCrypto).getRandomValues(b);
                id = Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
                try { localStorage.setItem('fpd_client', JSON.stringify(id)); } catch (e) { /* ignore */ }
            }
            return id;
        }
        function synced() { return read(SYNC_KEY, {}); }
        function mark(id, stamp) {
            var s = synced();
            if (stamp) s[id] = stamp; else delete s[id];
            try { localStorage.setItem(SYNC_KEY, JSON.stringify(s)); } catch (e) { /* ignore */ }
        }
        function send(body, keepalive) {
            if (!window.fetch) return Promise.resolve(false);
            return fetch(FP.url(body.notes ? '/api/v1/notes.php' : '/api/v1/plans.php'), {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                body: JSON.stringify(body), keepalive: !!keepalive && JSON.stringify(body).length < 60000
            }).then(function (r) { return r.ok; }).catch(function () { return false; });
        }
        var NOTES_KEY = 'floor_plan_notes', NOTES_SYNC_KEY = 'fpd_notes_synced', notesTimer = null;
        function notesHash(list) {
            var str = JSON.stringify(list || []), h = 0;
            for (var i = 0; i < str.length; i++) h = (h * 31 + str.charCodeAt(i)) | 0;
            return str.length + ':' + h;
        }
        function uploadNotes(keepalive) {
            var notes = read(NOTES_KEY, []);
            if (!Array.isArray(notes)) return Promise.resolve(false);
            var stamp = notesHash(notes);
            return send({ client: clientId(), source: 'web', notes: notes }, keepalive).then(function (ok) {
                if (ok) { try { localStorage.setItem(NOTES_SYNC_KEY, JSON.stringify(stamp)); } catch (e) { /* ignore */ } }
                return ok;
            }, function () { return false; });
        }
        function upload(id, keepalive) {
            delete pending[id]; clearTimeout(timers[id]);
            var plan = FPStore.get(id); if (!plan) return Promise.resolve(false);
            var stamp = plan.modifiedAt || '';
            return send({ client: clientId(), source: 'web', plan: plan }, keepalive).then(function (ok) {
                if (ok) mark(id, stamp);
                document.dispatchEvent(new CustomEvent('fpd:synced'));
                return ok;
            });
        }
        return {
            push: function (plan) {
                if (!plan || !plan.id) return;
                pending[plan.id] = true; clearTimeout(timers[plan.id]);
                timers[plan.id] = setTimeout(function () { upload(plan.id); }, 1500);
            },
            remove: function (id) {
                delete pending[id]; clearTimeout(timers[id]); mark(id, null);
                send({ client: clientId(), delete: id }, true);
            },
            flush: function () {
                Object.keys(pending).forEach(function (id) { upload(id, true); });
                if (notesTimer) { clearTimeout(notesTimer); notesTimer = null; uploadNotes(true); }
            },
            // Notes: the whole list is sent and replaces this browser's notes on the server.
            pushNotes: function () {
                clearTimeout(notesTimer);
                notesTimer = setTimeout(function () { notesTimer = null; uploadNotes(false); }, 1000);
            },
            // How many local plans are backed up with their latest changes.
            status: function () {
                var s = synced(), list = FPStore.list();
                return { total: list.length, synced: list.filter(function (m) { return s[m.id] === m.modifiedAt; }).length };
            },
            // Upload every plan now, one after another. Resolves with the number that failed.
            syncAll: function () {
                var ids = FPStore.list().map(function (m) { return m.id; }), failed = 0;
                return ids.reduce(function (p, id) {
                    return p.then(function () { return upload(id); }).then(function (ok) { if (!ok) failed++; });
                }, Promise.resolve()).then(function () { return failed; });
            },
            // Upload plans saved before backups existed, or changed while offline.
            catchUp: function () {
                if (notesHash(read(NOTES_KEY, [])) !== read(NOTES_SYNC_KEY, '')) uploadNotes(false);
                var s = synced();
                FPStore.list().filter(function (m) { return s[m.id] !== m.modifiedAt; }).slice(0, 30)
                    .forEach(function (m, i) { setTimeout(function () { upload(m.id); }, 800 + i * 400); });
            }
        };
    })();
    window.addEventListener('pagehide', function () { FPSync.flush(); });
    setTimeout(function () { FPSync.catchUp(); }, 1500);

    window.FPStore = FPStore;
    window.FP = FP;
    window.FPSync = FPSync;

    // Backup status + "Sync now" on the Saved Plans page.
    var syncBox = document.getElementById('sync-status');
    if (syncBox) {
        var label = syncBox.querySelector('[data-sync-label]'), btn = syncBox.querySelector('[data-sync-now]');
        var show = function () {
            var st = FPSync.status();
            syncBox.classList.toggle('is-ok', st.total > 0 && st.synced === st.total);
            label.textContent = st.total === 0 ? 'No plans to back up yet.'
                : st.synced === st.total ? 'All ' + st.total + ' plan' + (st.total > 1 ? 's are' : ' is') + ' backed up to the server.'
                : st.synced + ' of ' + st.total + ' plans backed up.';
        };
        document.addEventListener('fpd:synced', show);
        btn.addEventListener('click', function () {
            btn.disabled = true; label.textContent = 'Syncing…';
            FPSync.syncAll().then(function (failed) {
                btn.disabled = false; show();
                if (failed) label.textContent += ' ' + failed + ' could not be sent — check your connection and try again.';
            });
        });
        show();
    }

    // ---------------------------------------------------------------- header
    var toggle = document.querySelector('.ah-toggle');
    var nav = document.getElementById('app-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // ---------------------------------------------------------------- hero slider
    var slides = document.querySelectorAll('.hero-slide');
    if (slides.length > 1) {
        var dots = document.querySelectorAll('.hero-dots button');
        var current = 0, timer;
        var show = function (i) {
            slides[current].classList.remove('is-active'); slides[current].setAttribute('aria-hidden', 'true');
            if (dots[current]) dots[current].classList.remove('is-active');
            current = i;
            slides[current].classList.add('is-active'); slides[current].setAttribute('aria-hidden', 'false');
            if (dots[current]) dots[current].classList.add('is-active');
        };
        var start = function () { timer = setInterval(function () { show((current + 1) % slides.length); }, 6000); };
        dots.forEach(function (d, i) { d.addEventListener('click', function () { clearInterval(timer); show(i); }); });
        if (!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) start();
    }

    // ---------------------------------------------------------------- tabs
    document.querySelectorAll('[data-tabs]').forEach(function (group) {
        var buttons = group.querySelectorAll('[data-tab]');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                buttons.forEach(function (b) { b.classList.toggle('is-active', b === btn); b.setAttribute('aria-selected', b === btn ? 'true' : 'false'); });
                document.querySelectorAll('[data-tab-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-tab-panel') !== btn.getAttribute('data-tab'); });
                try { history.replaceState(null, '', '#' + btn.getAttribute('data-tab')); } catch (e) { /* ignore */ }
            });
        });
        var hash = location.hash.slice(1);
        var initial = hash && group.querySelector('[data-tab="' + hash + '"]');
        if (initial) initial.click();
    });

    // ---------------------------------------------------------------- recent plans (dashboard + plans page)
    function planCard(p, i, withActions) {
        return '<article class="plan-card" data-id="' + FP.esc(p.id) + '">' +
            '<div class="pc-main" data-open>' +
            '<span class="pc-num">' + String(i + 1).padStart(2, '0') + '.</span>' +
            '<h3>' + FP.esc(p.name || 'Untitled plan') + '</h3>' +
            '<div class="pc-meta">Last modified: ' + FP.esc(FP.relTime(p.modifiedAt)) + '</div>' +
            '<div class="pc-open link-arrow">Open plan →</div></div>' +
            (withActions ? '<div class="pc-actions" style="display:flex;flex-direction:column;gap:6px">' +
                '<button class="icon-btn" type="button" data-act="view3d" title="3D view" aria-label="3D view" style="font:800 11px var(--sans)">3D</button>' +
                '<button class="icon-btn" type="button" data-act="rename" title="Rename" aria-label="Rename">✎</button>' +
                '<button class="icon-btn" type="button" data-act="duplicate" title="Duplicate" aria-label="Duplicate">⧉</button>' +
                '<button class="icon-btn" type="button" data-act="download" title="Download (.json)" aria-label="Download">⤓</button>' +
                '<button class="icon-btn danger" type="button" data-act="delete" title="Delete" aria-label="Delete">✕</button>' +
                '</div>' : '') +
            '</article>';
    }
    function renderPlans(el) {
        var max = parseInt(el.getAttribute('data-max') || '0', 10);
        var withActions = el.hasAttribute('data-actions');
        var plans = FPStore.list();
        var shown = max ? plans.slice(0, max) : plans;
        var empty = document.querySelector(el.getAttribute('data-empty'));
        var more = document.querySelector(el.getAttribute('data-more') || '#none');
        if (empty) empty.hidden = plans.length > 0;
        if (more) more.hidden = plans.length === 0;
        el.innerHTML = shown.map(function (p, i) { return planCard(p, i, withActions); }).join('');
    }
    document.querySelectorAll('[data-plans]').forEach(function (el) {
        renderPlans(el);
        el.addEventListener('click', function (ev) {
            var card = ev.target.closest('.plan-card'); if (!card) return;
            var id = card.getAttribute('data-id');
            var act = ev.target.closest('[data-act]');
            if (!act) { if (ev.target.closest('[data-open]')) location.href = FP.url('/app/editor?plan=' + encodeURIComponent(id)); return; }
            var a = act.getAttribute('data-act');
            if (a === 'view3d') { location.href = FP.url('/app/3d?plan=' + encodeURIComponent(id)); return; }
            if (a === 'rename') { var n = prompt('Plan name', (FPStore.get(id) || {}).name || ''); if (n && n.trim()) FPStore.rename(id, n.trim()); }
            if (a === 'duplicate') FPStore.duplicate(id);
            if (a === 'download') FPStore.download(id);
            if (a === 'delete' && confirm('Delete this plan? This cannot be undone.')) FPStore.remove(id);
            renderPlans(el);
        });
    });
    var importInput = document.getElementById('plan-import');
    if (importInput) {
        importInput.addEventListener('change', function () {
            if (!importInput.files[0]) return;
            FPStore.importFile(importInput.files[0], function () { document.querySelectorAll('[data-plans]').forEach(renderPlans); });
            importInput.value = '';
        });
    }

    // ---------------------------------------------------------------- template lightbox + filters
    var lb = document.getElementById('lightbox');
    if (lb) {
        var lbImg = lb.querySelector('img'), lbTitle = lb.querySelector('strong'), lbDl = lb.querySelector('[data-download]');
        document.querySelectorAll('[data-template]').forEach(function (a) {
            a.addEventListener('click', function (ev) {
                ev.preventDefault();
                lbImg.src = a.getAttribute('href'); lbImg.alt = a.getAttribute('data-name');
                lbTitle.textContent = a.getAttribute('data-name');
                lbDl.setAttribute('href', a.getAttribute('href'));
                lbDl.setAttribute('download', a.getAttribute('data-name').replace(/[^\w\- ]+/g, '') + '.jpg');
                lb.classList.add('open'); lb.querySelector('[data-close]').focus();
            });
        });
        var close = function () { lb.classList.remove('open'); };
        lb.addEventListener('click', function (ev) { if (ev.target === lb || ev.target.closest('[data-close]')) close(); });
        document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') close(); });
    }
    document.querySelectorAll('[data-filter-group]').forEach(function (group) {
        var buttons = group.querySelectorAll('[data-filter]');
        var items = document.querySelectorAll('[data-cat]');
        buttons.forEach(function (b) {
            b.addEventListener('click', function () {
                var f = b.getAttribute('data-filter');
                buttons.forEach(function (x) { x.classList.toggle('is-active', x === b); });
                items.forEach(function (it) { it.hidden = !(f === 'all' || it.getAttribute('data-cat') === f); });
            });
        });
        // Open a category from a link such as /app/templates#2bed.
        var start = location.hash && group.querySelector('[data-filter="' + location.hash.slice(1).replace(/[^\w-]/g, '') + '"]');
        if (start) start.click();
    });
})();
