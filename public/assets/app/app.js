/* Floor Plan Designer web app — shared browser code.
 * Saving needs an account (Sign in with Google, FPAuth below). Plans and notes are kept in this
 * browser's localStorage and saved to the user's account on the server (/api/v1/plans.php,
 * /api/v1/notes.php), so they appear in every browser the user signs in to, and in
 * Admin → Saved Plans / User Notes with the user's email. Signing out removes them from the
 * browser; they stay in the account. */
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
            if (!FPAuth.user()) { FPAuth.open('Sign in to save your plans to your account.'); return null; }
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
        // Store a plan downloaded from the account (keeps its own dates; no upload).
        putLocal: function (plan) {
            if (!plan || !plan.id || !write(PLAN_PREFIX + plan.id, plan)) return;
            var l = FPStore.list().filter(function (p) { return p.id !== plan.id; });
            l.push({ id: plan.id, name: plan.name, createdAt: plan.createdAt, modifiedAt: plan.modifiedAt });
            write(LIST_KEY, l);
        },
        // Remove every plan and note from this browser (after signing out; the account keeps them).
        clearLocal: function () {
            FPStore.list().forEach(function (p) { try { localStorage.removeItem(PLAN_PREFIX + p.id); } catch (e) { /* ignore */ } });
            ['fpd_plans', 'fpd_synced', 'floor_plan_notes', 'fpd_notes_synced', 'fpd_draft'].forEach(function (k) { try { localStorage.removeItem(k); sessionStorage.removeItem(k); } catch (e) { /* ignore */ } });
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
    // Plans and notes go to the signed-in user's account. Each browser also sends a random
    // 128-bit id (kept only in this browser), which the server uses for rate limits.
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
            if (!window.fetch || !FPAuth.user()) return Promise.resolve(false);
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
                send({ client: clientId(), source: 'web', delete: id }, true);
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
            // After signing in (and on each visit): download the account's plans and notes that this
            // browser doesn't have (or has older copies of), then upload anything newer here.
            pull: function () {
                if (!FPAuth.user() || !window.fetch) return Promise.resolve(false);
                var get = function (q) { return fetch(FP.url(q), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; }); };
                var local = {}; FPStore.list().forEach(function (m) { local[m.id] = m.modifiedAt || ''; });
                var plans = get('/api/v1/plans.php?list=1').then(function (d) {
                    var want = ((d && d.plans) || []).filter(function (m) { return !(m.id in local) || (m.modifiedAt || '') > local[m.id]; });
                    return want.reduce(function (p, m) {
                        return p.then(function () { return get('/api/v1/plans.php?id=' + encodeURIComponent(m.id)); }).then(function (r) {
                            if (r && r.plan) { FPStore.putLocal(r.plan); mark(r.plan.id, r.plan.modifiedAt); }
                        });
                    }, Promise.resolve());
                });
                var notes = get('/api/v1/notes.php').then(function (d) {
                    var mine = read(NOTES_KEY, []), seen = {};
                    if (!Array.isArray(mine)) mine = [];
                    mine.forEach(function (n) { seen[(n.title || '') + '\u0000' + (n.content || '')] = 1; });
                    var added = 0;
                    ((d && d.notes) || []).forEach(function (n, i) {
                        var k = (n.title || '') + '\u0000' + (n.content || '');
                        if (seen[k]) return;
                        var t = n.updatedAt ? String(n.updatedAt).replace(' ', 'T') + 'Z' : new Date().toISOString();
                        mine.push({ id: 'note_' + Date.now() + '_' + i, title: n.title || 'Untitled', content: n.content || '', createdAt: t, updatedAt: t });
                        seen[k] = 1; added++;
                    });
                    if (added) { try { localStorage.setItem(NOTES_KEY, JSON.stringify(mine)); } catch (e) { /* ignore */ } }
                });
                return Promise.all([plans, notes]).then(function () {
                    document.dispatchEvent(new CustomEvent('fpd:plans-changed'));
                    FPSync.catchUp();
                    return true;
                });
            },
            // Upload plans saved before backups existed, or changed while offline.
            catchUp: function () {
                if (!FPAuth.user()) return;
                if (notesHash(read(NOTES_KEY, [])) !== read(NOTES_SYNC_KEY, '')) uploadNotes(false);
                var s = synced();
                FPStore.list().filter(function (m) { return s[m.id] !== m.modifiedAt; }).slice(0, 30)
                    .forEach(function (m, i) { setTimeout(function () { upload(m.id); }, 800 + i * 400); });
            }
        };
    })();
    window.addEventListener('pagehide', function () { FPSync.flush(); });

    // ---------------------------------------------------------------- accounts (Sign in with Google)
    var FPAuth = (function () {
        var root = document.documentElement, user = null, waiting = [], modal = null, gis = null;
        try { user = JSON.parse(root.getAttribute('data-user') || 'null'); } catch (e) { user = null; }
        var clientId = root.getAttribute('data-google-client') || '';

        function initials(u) { return ((u.name || u.email || '?').trim()[0] || '?').toUpperCase(); }
        function avatar(u) {
            return u.picture ? '<img src="' + FP.esc(u.picture) + '" alt="" referrerpolicy="no-referrer">' : '<span>' + FP.esc(initials(u)) + '</span>';
        }
        function renderSlots() {
            document.querySelectorAll('[data-account]').forEach(function (el) {
                if (!user) {
                    el.innerHTML = '<button type="button" class="acct-signin" data-auth-open>Sign in</button>';
                    return;
                }
                el.innerHTML = '<button type="button" class="acct-btn" aria-haspopup="true" aria-expanded="false" title="' + FP.esc(user.email) + '">' + avatar(user) +
                    '<b>' + FP.esc((user.name || user.email).split(' ')[0]) + '</b></button>' +
                    '<div class="acct-menu" hidden><div class="acct-who">' + avatar(user) + '<span><strong>' + FP.esc(user.name || 'Your account') + '</strong>' + FP.esc(user.email) + '</span></div>' +
                    '<p class="acct-note">Your plans and notes are saved to this account.</p>' +
                    '<button type="button" class="acct-out" data-auth-signout>Sign out</button></div>';
            });
        }
        document.addEventListener('click', function (ev) {
            if (ev.target.closest('[data-auth-open]')) { open(''); return; }
            if (ev.target.closest('[data-auth-signout]')) { signOut(); return; }
            var btn = ev.target.closest('.acct-btn');
            document.querySelectorAll('.acct-menu').forEach(function (m) {
                var mine = btn && m.previousElementSibling === btn;
                m.hidden = mine ? !m.hidden : true;
                if (m.previousElementSibling) m.previousElementSibling.setAttribute('aria-expanded', m.hidden ? 'false' : 'true');
            });
        });

        function loadGis() {
            return gis || (gis = new Promise(function (resolve, reject) {
                var sc = document.createElement('script');
                sc.src = 'https://accounts.google.com/gsi/client'; sc.async = true;
                sc.onload = resolve; sc.onerror = function () { gis = null; reject(); };
                document.head.appendChild(sc);
            }));
        }
        function setError(msg) { if (modal) { var e = modal.querySelector('[data-auth-error]'); e.textContent = msg || ''; e.hidden = !msg; } }
        function close() {
            if (!modal) return;
            modal.remove(); modal = null; waiting = [];
            document.removeEventListener('keydown', onKey);
        }
        function onKey(ev) { if (ev.key === 'Escape') close(); }
        function open(reason) {
            if (user) return;
            if (modal) { if (reason) modal.querySelector('[data-auth-reason]').textContent = reason; return; }
            modal = document.createElement('div');
            modal.className = 'auth-modal';
            modal.innerHTML = '<div class="auth-card" role="dialog" aria-modal="true" aria-labelledby="auth-title">' +
                '<button type="button" class="auth-x" aria-label="Close">&times;</button>' +
                '<div class="auth-logo"><img src="' + FP.url('/apple-touch-icon.png') + '" alt=""></div>' +
                '<h2 id="auth-title">Sign in to save</h2>' +
                '<p data-auth-reason>' + FP.esc(reason || 'Save your floor plans and notes to your free account and open them on any computer.') + '</p>' +
                '<div class="auth-google" data-auth-google><span class="auth-loading">Loading Google sign-in…</span></div>' +
                '<p class="auth-error" data-auth-error role="alert" hidden></p>' +
                '<p class="auth-small">We only use your name, email and profile photo from Google. <a href="' + FP.url('/privacy-policy') + '" target="_blank" rel="noopener">Privacy policy</a></p>' +
                '</div>';
            document.body.appendChild(modal);
            modal.addEventListener('click', function (ev) { if (ev.target === modal || ev.target.closest('.auth-x')) close(); });
            document.addEventListener('keydown', onKey);
            var slot = modal.querySelector('[data-auth-google]');
            if (!clientId) {
                slot.innerHTML = '';
                setError('Google sign-in isn\'t set up on this site yet. An admin can add the Google client ID in Admin → Site Settings.');
                return;
            }
            loadGis().then(function () {
                if (!modal) return;
                google.accounts.id.initialize({ client_id: clientId, callback: onCredential, ux_mode: 'popup', auto_select: false, cancel_on_tap_outside: true });
                slot.innerHTML = '';
                google.accounts.id.renderButton(slot, { theme: 'filled_blue', size: 'large', shape: 'pill', text: 'continue_with', width: 280 });
            }).catch(function () { slot.innerHTML = ''; setError('Could not load Google sign-in. Check your internet connection and try again.'); });
        }
        function onCredential(resp) {
            setError('');
            fetch(FP.url('/api/v1/auth.php'), {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                body: JSON.stringify({ action: 'google', credential: resp && resp.credential })
            }).then(function (r) { return r.json(); }).then(function (d) {
                if (!d || !d.ok || !d.user) throw new Error((d && d.error) || 'Sign-in failed.');
                signedIn(d.user);
            }).catch(function (e) { setError(e.message || 'Sign-in failed. Please try again.'); });
        }
        function signedIn(u) {
            user = u;
            root.setAttribute('data-user', JSON.stringify(u));
            var run = waiting; waiting = [];
            close(); renderSlots();
            document.dispatchEvent(new CustomEvent('fpd:auth', { detail: { user: u } }));
            FPSync.pull();
            run.forEach(function (cb) { try { cb(); } catch (e) { /* ignore */ } });
        }
        function signOut() {
            var st = FPSync.status();
            if (st.synced < st.total && !confirm('Some changes have not reached your account yet. Sign out anyway? Unsaved changes on this computer will be lost.')) return;
            FPSync.flush();
            fetch(FP.url('/api/v1/auth.php'), {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ action: 'signout' })
            }).catch(function () { /* ignore */ }).then(function () {
                if (window.google && google.accounts && google.accounts.id) google.accounts.id.disableAutoSelect();
                FPStore.clearLocal();
                location.href = FP.url('/app');
            });
        }
        return {
            user: function () { return user; },
            open: open,
            // Run cb now if signed in, otherwise after the user signs in.
            require: function (cb, reason) { if (user) { cb(); return true; } waiting.push(cb); open(reason); return false; },
            render: renderSlots
        };
    })();
    FPAuth.render();
    document.addEventListener('fpd:plans-changed', function () { document.querySelectorAll('[data-plans]').forEach(function (el) { if (typeof renderPlans === 'function') renderPlans(el); }); });
    // Bring in plans and notes saved in other browsers (at most every 2 minutes).
    setTimeout(function () {
        if (!FPAuth.user()) return;
        var last = 0; try { last = +sessionStorage.getItem('fpd_pulled') || 0; } catch (e) { /* ignore */ }
        if (Date.now() - last < 120000) { FPSync.catchUp(); return; }
        try { sessionStorage.setItem('fpd_pulled', String(Date.now())); } catch (e) { /* ignore */ }
        FPSync.pull();
    }, 800);

    window.FPStore = FPStore;
    window.FP = FP;
    window.FPSync = FPSync;
    window.FPAuth = FPAuth;

    // Backup status + "Sync now" on the Saved Plans page.
    var syncBox = document.getElementById('sync-status');
    if (syncBox) {
        var label = syncBox.querySelector('[data-sync-label]'), btn = syncBox.querySelector('[data-sync-now]');
        var show = function () {
            var st = FPSync.status();
            syncBox.classList.toggle('is-ok', st.total > 0 && st.synced === st.total);
            label.textContent = st.total === 0 ? 'No plans to back up yet.'
                : st.synced === st.total ? 'All ' + st.total + ' plan' + (st.total > 1 ? 's are' : ' is') + ' saved to your account.'
                : st.synced + ' of ' + st.total + ' plans saved to your account.';
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
