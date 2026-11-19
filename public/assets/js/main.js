// Floor Plan Designer — progressive enhancements. Every page works without JS.
(function () {
    'use strict';

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---- Mobile navigation ----
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('site-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && nav.classList.contains('open')) {
                nav.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus();
            }
        });
    }

    // ---- Header shadow + back-to-top on scroll ----
    var header = document.getElementById('site-header');
    var toTop = document.querySelector('.to-top');
    function onScroll() {
        var y = window.scrollY || window.pageYOffset;
        if (header) header.classList.toggle('is-scrolled', y > 10);
        if (toTop) toTop.classList.toggle('is-visible', y > 600);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // ---- Reveal on scroll + animated counters ----
    function countUp(el) {
        var target = parseInt(el.getAttribute('data-count'), 10);
        var suffix = el.getAttribute('data-suffix') || '';
        if (isNaN(target) || reduceMotion) return;
        var start = null;
        var duration = 1400;
        function step(ts) {
            if (start === null) start = ts;
            var p = Math.min((ts - start) / duration, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased) + suffix;
            if (p < 1) window.requestAnimationFrame(step);
        }
        el.textContent = '0' + suffix;
        window.requestAnimationFrame(step);
    }

    var revealEls = document.querySelectorAll('.reveal');
    var counters = document.querySelectorAll('[data-count]');
    if ('IntersectionObserver' in window && !reduceMotion) {
        var io = new IntersectionObserver(function (entries) {
            var n = 0;
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                if (el.hasAttribute('data-count')) countUp(el);
                else {
                    // Blocks that appear together swing up one after another.
                    var delay = Math.min(n++, 5) * 90;
                    if (delay) {
                        el.style.transitionDelay = delay + 'ms';
                        setTimeout(function () { el.style.transitionDelay = ''; }, delay + 1100);
                    }
                    el.classList.add('is-in');
                }
                io.unobserve(el);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        revealEls.forEach(function (el) { io.observe(el); });
        counters.forEach(function (el) { io.observe(el); });
    } else {
        revealEls.forEach(function (el) { el.classList.add('is-in'); });
    }

    // ---- 3D tilt: cards lean toward the pointer, with a light sheen (mouse/trackpad only) ----
    var finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    if (finePointer && !reduceMotion) {
        var TILT = '.service-card, .process-step, .price-card, .card-grid .card, .gallery-item, .platform-card, .about-media, .feature-media';
        var MAX = 9; // degrees
        document.querySelectorAll(TILT).forEach(function (card) {
            card.classList.add('tilt');
            var shine = document.createElement('span');
            shine.className = 'tilt-shine';
            shine.setAttribute('aria-hidden', 'true');
            card.appendChild(shine);
            var frame = 0, px = 0.5, py = 0.5, settle = 0;
            function apply() {
                frame = 0;
                var big = card.offsetWidth > 420 ? 0.55 : 1; // large blocks tilt less
                card.style.transform = 'perspective(900px) rotateX(' + ((0.5 - py) * MAX * 2 * big).toFixed(2) + 'deg) rotateY(' +
                    ((px - 0.5) * MAX * 2 * big).toFixed(2) + 'deg) translate3d(0, -6px, 0)';
                card.style.setProperty('--mx', (px * 100).toFixed(1) + '%');
                card.style.setProperty('--my', (py * 100).toFixed(1) + '%');
            }
            card.addEventListener('pointermove', function (ev) {
                if (ev.pointerType !== 'mouse' && ev.pointerType !== 'pen') return;
                // Wait until a scroll-reveal has finished, so the two motions don't fight.
                if (card.classList.contains('reveal') && !card.classList.contains('is-in')) return;
                var b = card.getBoundingClientRect();
                px = (ev.clientX - b.left) / b.width;
                py = (ev.clientY - b.top) / b.height;
                clearTimeout(settle);
                card.classList.remove('is-settling');
                card.classList.add('is-tilting');
                if (!frame) frame = window.requestAnimationFrame(apply);
            });
            card.addEventListener('pointerleave', function () {
                if (frame) { window.cancelAnimationFrame(frame); frame = 0; }
                card.classList.remove('is-tilting');
                card.classList.add('is-settling');
                card.style.transform = '';
                settle = setTimeout(function () { card.classList.remove('is-settling'); }, 650);
            });
        });

        // Hero layers (phone, "3D live preview" chip) drift with the pointer at different depths.
        var heroVisual = document.querySelector('.hero-visual');
        var heroSection = heroVisual && heroVisual.closest('.hero');
        if (heroSection) {
            var hFrame = 0, hx = 0, hy = 0;
            var setHero = function () {
                hFrame = 0;
                heroVisual.style.setProperty('--px', hx.toFixed(3));
                heroVisual.style.setProperty('--py', hy.toFixed(3));
            };
            heroSection.addEventListener('pointermove', function (ev) {
                var b = heroSection.getBoundingClientRect();
                hx = ((ev.clientX - b.left) / b.width - 0.5) * 2;
                hy = ((ev.clientY - b.top) / b.height - 0.5) * 2;
                if (!hFrame) hFrame = window.requestAnimationFrame(setHero);
            });
            heroSection.addEventListener('pointerleave', function () { hx = 0; hy = 0; setHero(); });
        }
    }

    // ---- Live backgrounds: pause off screen, scroll parallax, pointer depth ----
    var liveBgs = Array.prototype.slice.call(document.querySelectorAll('.live-bg'));
    if (liveBgs.length && !reduceMotion) {
        var shown = [];
        if ('IntersectionObserver' in window) {
            var bgIo = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    en.target.classList.toggle('is-paused', !en.isIntersecting);
                    var i = shown.indexOf(en.target);
                    if (en.isIntersecting && i < 0) shown.push(en.target);
                    if (!en.isIntersecting && i >= 0) shown.splice(i, 1);
                });
                queueParallax();
            });
            liveBgs.forEach(function (bg) { bgIo.observe(bg); });
        } else {
            shown = liveBgs.slice();
        }
        // The photo moves slower than the page, so it seems further away.
        var pFrame = 0;
        var parallax = function () {
            pFrame = 0;
            var vh = window.innerHeight;
            shown.forEach(function (bg) {
                var b = bg.getBoundingClientRect();
                // -1 when the section enters at the bottom, +1 when it leaves at the top. The photo
                // layer overhangs by 16% of the section's height, so it moves at most 9% of it.
                var ratio = Math.max(-1, Math.min(1, (b.top + b.height / 2 - vh / 2) / (vh / 2 + b.height / 2)));
                bg.style.setProperty('--sy', (ratio * -0.09 * b.height).toFixed(1) + 'px');
            });
        };
        var queueParallax = function () { if (!pFrame) pFrame = window.requestAnimationFrame(parallax); };
        window.addEventListener('scroll', queueParallax, { passive: true });
        window.addEventListener('resize', queueParallax);
        queueParallax();

        if (finePointer) {
            liveBgs.forEach(function (bg) {
                var section = bg.parentElement, lf = 0, lx = 0, ly = 0;
                var set = function () { lf = 0; bg.style.setProperty('--px', lx.toFixed(3)); bg.style.setProperty('--py', ly.toFixed(3)); };
                section.addEventListener('pointermove', function (ev) {
                    var b = section.getBoundingClientRect();
                    lx = ((ev.clientX - b.left) / b.width - 0.5) * 2;
                    ly = ((ev.clientY - b.top) / b.height - 0.5) * 2;
                    if (!lf) lf = window.requestAnimationFrame(set);
                });
                section.addEventListener('pointerleave', function () { lx = 0; ly = 0; set(); });
            });
        }
    }

    // ---- Gallery filters ----
    var filters = document.querySelectorAll('.filter[data-filter]');
    var items = document.querySelectorAll('.gallery-item[data-cat]');
    filters.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var cat = btn.getAttribute('data-filter');
            filters.forEach(function (b) {
                var active = b === btn;
                b.classList.toggle('is-active', active);
                b.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            items.forEach(function (item) {
                var show = cat === 'all' || item.getAttribute('data-cat') === cat;
                item.classList.toggle('is-hidden', !show);
                if (show) item.classList.add('is-in');
            });
        });
    });

    // ---- FAQ live search (support page) ----
    var search = document.getElementById('faq-search');
    if (search) {
        var faqItems = Array.prototype.slice.call(document.querySelectorAll('#faq .faq-item'));
        var groups = Array.prototype.slice.call(document.querySelectorAll('#faq .faq-group'));
        var empty = document.getElementById('faq-empty');
        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            var shown = 0;
            faqItems.forEach(function (item) {
                var match = !q || item.textContent.toLowerCase().indexOf(q) !== -1;
                item.hidden = !match;
                item.open = !!(q && match);
                if (match) shown++;
            });
            groups.forEach(function (g) {
                g.hidden = !g.querySelector('.faq-item:not([hidden])');
            });
            if (empty) empty.hidden = shown !== 0;
        });
    }

    // ---- Confirm destructive admin actions ----
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-confirm]');
        if (btn && !window.confirm(btn.getAttribute('data-confirm'))) {
            ev.preventDefault();
        }
    });
})();
