// Floor Plan Designer — small progressive enhancements. The site works without JS.
(function () {
    'use strict';

    // Mobile navigation toggle
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('site-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // FAQ live search
    var search = document.getElementById('faq-search');
    if (search) {
        var items = Array.prototype.slice.call(document.querySelectorAll('.faq-item'));
        var groups = Array.prototype.slice.call(document.querySelectorAll('.faq-group'));
        var empty = document.getElementById('faq-empty');

        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            var shown = 0;
            items.forEach(function (item) {
                var match = !q || item.textContent.toLowerCase().indexOf(q) !== -1;
                item.hidden = !match;
                if (q && match) { item.open = true; }
                if (!q) { item.open = false; }
                if (match) { shown++; }
            });
            groups.forEach(function (g) {
                g.hidden = !g.querySelector('.faq-item:not([hidden])');
            });
            if (empty) { empty.hidden = shown !== 0; }
        });
    }

    // Confirm destructive admin actions
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-confirm]');
        if (btn && !window.confirm(btn.getAttribute('data-confirm'))) {
            ev.preventDefault();
        }
    });
})();
