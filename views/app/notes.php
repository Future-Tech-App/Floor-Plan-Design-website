<?php $headLead = 'Project notes and annotations, saved in this browser and backed up to our server.'; require ROOT_DIR . '/views/app/_head.php'; ?>
<div class="page-body">
    <div class="a-container notes-layout">
        <aside>
            <div class="form-actions" style="margin:0 0 14px"><button class="btn" type="button" id="note-new"><?= icon('plus') ?> New note</button></div>
            <label class="field" style="display:block;margin-bottom:14px"><span class="visually-hidden">Search notes</span><input type="search" id="note-search" placeholder="Search notes…"></label>
            <div id="note-list"></div>
            <p id="note-empty" class="muted" hidden style="font:italic 1.1rem var(--serif)">No notes yet.</p>
        </aside>
        <form class="panel" id="note-form" autocomplete="off">
            <label class="field" style="display:block;margin-bottom:14px"><span class="flabel">Title</span><input name="title" maxlength="120" placeholder="Untitled"></label>
            <label class="field" style="display:block"><span class="flabel">Note</span><textarea name="content" rows="14" placeholder="Write your note…"></textarea></label>
            <div class="form-actions">
                <button class="btn" type="submit">Save note</button>
                <button class="btn btn-danger" type="button" id="note-delete" hidden>Delete</button>
                <span class="kicker" id="note-saved" hidden style="align-self:center">Saved</span>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var KEY = 'floor_plan_notes';
    var load = function () { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } };
    var store = function (n) {
        try { localStorage.setItem(KEY, JSON.stringify(n)); } catch (e) { alert('Could not save the note.'); return; }
        if (window.FPSync) FPSync.pushNotes();   // back up to the server
    };
    var notes = load(), editing = null;
    var list = document.getElementById('note-list'), form = document.getElementById('note-form');
    var del = document.getElementById('note-delete'), search = document.getElementById('note-search');
    function render() {
        var q = search.value.trim().toLowerCase();
        var shown = notes.filter(function (n) { return !q || (n.title + ' ' + n.content).toLowerCase().indexOf(q) !== -1; })
            .sort(function (a, b) { return (b.updatedAt || '').localeCompare(a.updatedAt || ''); });
        list.innerHTML = shown.map(function (n) {
            return '<button type="button" class="note-item' + (n.id === editing ? ' is-active' : '') + '" data-id="' + FP.esc(n.id) + '"><strong>' + FP.esc(n.title || 'Untitled') + '</strong><span>' + FP.esc(FP.relTime(n.updatedAt)) + '</span></button>';
        }).join('');
        document.getElementById('note-empty').hidden = notes.length > 0;
    }
    function open(n) {
        editing = n ? n.id : null;
        form.title.value = n ? n.title : ''; form.content.value = n ? n.content : '';
        del.hidden = !n; render(); form.title.focus();
    }
    list.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-id]'); if (!b) return;
        open(notes.find(function (n) { return n.id === b.getAttribute('data-id'); }));
    });
    document.getElementById('note-new').addEventListener('click', function () { open(null); });
    search.addEventListener('input', render);
    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var title = form.title.value.trim(), content = form.content.value.trim();
        if (!title && !content) { alert('Add a title or some content first.'); return; }
        var now = new Date().toISOString();
        var n = notes.find(function (x) { return x.id === editing; });
        if (n) { n.title = title || 'Untitled'; n.content = content; n.updatedAt = now; }
        else { n = { id: 'note_' + Date.now(), title: title || 'Untitled', content: content, createdAt: now, updatedAt: now }; notes.push(n); editing = n.id; }
        store(notes); del.hidden = false; render();
        var s = document.getElementById('note-saved'); s.hidden = false; setTimeout(function () { s.hidden = true; }, 1800);
    });
    del.addEventListener('click', function () {
        if (!editing || !confirm('Delete this note? This cannot be undone.')) return;
        notes = notes.filter(function (n) { return n.id !== editing; }); store(notes); open(null);
    });
    render();
});
</script>
