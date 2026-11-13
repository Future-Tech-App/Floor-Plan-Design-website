/* Floor Plan Designer web app — shared browser code.
 * Plans and notes live in this browser's localStorage (no accounts; nothing is
 * sent to the server). */
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
            return plan;
        },
        remove: function (id) {
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
    window.FPStore = FPStore;
    window.FP = FP;

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
    });
})();
