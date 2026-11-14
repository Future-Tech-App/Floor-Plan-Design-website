/* Admin panel: sidebar on small screens, select-all checkboxes, confirm prompts. */
(function () {
    'use strict';
    var side = document.getElementById('ad-side'), scrim = document.querySelector('[data-close-side]');
    function toggle(open) { if (!side) return; side.classList.toggle('is-open', open); if (scrim) scrim.hidden = !open; }
    document.querySelectorAll('[data-open-side]').forEach(function (b) { b.addEventListener('click', function () { toggle(true); }); });
    if (scrim) scrim.addEventListener('click', function () { toggle(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') toggle(false); });

    // "Select all" checkbox for bulk actions.
    document.querySelectorAll('[data-check-all]').forEach(function (all) {
        var form = all.closest('form');
        all.addEventListener('change', function () { form.querySelectorAll('input[name="ids[]"]').forEach(function (c) { c.checked = all.checked; }); update(form); });
        form.addEventListener('change', function () { update(form); });
        update(form);
    });
    function update(form) {
        var n = form.querySelectorAll('input[name="ids[]"]:checked').length, el = form.querySelector('[data-selected]');
        if (el) el.textContent = n ? n + ' selected' : '';
        form.querySelectorAll('[data-bulk]').forEach(function (b) { b.disabled = !n; });
    }

    // Confirm destructive actions.
    document.addEventListener('submit', function (e) {
        var btn = e.submitter, msg = (btn && btn.getAttribute('data-confirm')) || e.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) e.preventDefault();
    });

    // Show the chosen file name in upload fields.
    document.querySelectorAll('.ad-file input[type=file]').forEach(function (inp) {
        inp.addEventListener('change', function () { var s = inp.parentNode.querySelector('[data-file-name]'); if (s) s.textContent = inp.files[0] ? inp.files[0].name : 'No file chosen'; });
    });
})();
