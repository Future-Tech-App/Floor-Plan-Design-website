<?php $headLead = 'Saved in this browser and used by the editor.'; require ROOT_DIR . '/views/app/_head.php'; ?>
<div class="page-body">
    <div class="a-container" style="max-width:760px">
        <form class="panel" id="settings-form">
            <h2>Editor</h2>
            <div class="field" style="margin-bottom:18px">
                <span class="flabel">Unit system</span>
                <div class="seg">
                    <label><input type="radio" name="units" value="metric"><span>Metric (m, cm)</span></label>
                    <label><input type="radio" name="units" value="imperial"><span>Imperial (ft, in)</span></label>
                </div>
            </div>
            <div class="form-grid">
                <label class="field"><span class="flabel">Default wall thickness (cm)</span><input type="number" name="wallThickness" min="5" max="60" step="1"></label>
            </div>
            <div style="display:grid;gap:12px;margin-top:18px">
                <label><input type="checkbox" name="grid"> Show grid</label>
                <label><input type="checkbox" name="snap"> Snap to grid</label>
                <label><input type="checkbox" name="dimensions"> Show dimensions</label>
            </div>
            <div class="form-actions"><button class="btn" type="submit">Save settings</button><span id="settings-saved" class="kicker" hidden style="align-self:center">Saved</span></div>
        </form>
        <div class="panel">
            <h2>Data</h2>
            <p>Your plans and notes are stored only in this browser. Clearing your browser data deletes them — download plans from <a href="<?= e(url('/app/plans')) ?>">Saved Plans</a> to keep a copy.</p>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var f = document.getElementById('settings-form'), s = FP.settings();
    f.units.value = s.units; f.wallThickness.value = s.wallThickness;
    f.grid.checked = !!s.grid; f.snap.checked = !!s.snap; f.dimensions.checked = !!s.dimensions;
    f.addEventListener('submit', function (ev) {
        ev.preventDefault();
        FP.saveSettings({ units: f.units.value, wallThickness: Math.min(60, Math.max(5, parseFloat(f.wallThickness.value) || 15)), grid: f.grid.checked, snap: f.snap.checked, dimensions: f.dimensions.checked });
        var m = document.getElementById('settings-saved'); m.hidden = false; setTimeout(function () { m.hidden = true; }, 2000);
    });
});
</script>
