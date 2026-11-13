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
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                if (el.hasAttribute('data-count')) countUp(el);
                else el.classList.add('is-in');
                io.unobserve(el);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        revealEls.forEach(function (el) { io.observe(el); });
        counters.forEach(function (el) { io.observe(el); });
    } else {
        revealEls.forEach(function (el) { el.classList.add('is-in'); });
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
