<?php /** 3D view of a saved plan (three.js). The plan is read from browser storage by viewer3d.js. */
$swatches = ['#ede8e0', '#d6c39a', '#b8ad9c', '#a4a7aa', '#d2b36a', '#b57455', '#93a383', '#7f99ac', '#e7d9c9', '#6e7e6b', '#4f5b66', '#c98c7a'];
$floors = ['room' => 'Room colours', 'wood' => 'Wood', 'tiles' => 'Tiles', 'marble' => 'Marble', 'concrete' => 'Concrete'];
?>
<div class="ed v3d" id="viewer" data-symbols-base="<?= e(url('/assets/app/symbols/')) ?>">
    <header class="ed-top">
        <a class="ed-back" id="back-link" href="<?= e(url('/app/plans')) ?>" title="Back to the editor" aria-label="Back to the editor"><?= icon('chevron-left') ?></a>
        <div class="ed-title">
            <strong id="plan-title">3D View</strong>
            <span id="plan-sub">Drag to rotate · scroll or pinch to zoom</span>
        </div>
        <div class="ed-actions">
            <div class="v3d-seg" role="group" aria-label="View mode">
                <button type="button" class="is-active" data-mode="orbit">Orbit</button>
                <button type="button" data-mode="walk">Walk</button>
            </div>
            <button type="button" class="ed-btn" data-cmd="reset" title="Reset view" aria-label="Reset view"><svg viewBox="0 0 24 24" class="icon"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg></button>
            <button type="button" class="ed-btn ed-btn-text" data-cmd="snapshot"><svg viewBox="0 0 24 24" class="icon"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2Z"/><circle cx="12" cy="13" r="4"/></svg><span>Snapshot</span></button>
            <button type="button" class="ed-btn ed-btn-primary" data-cmd="settings"><?= icon('settings') ?><span>Settings</span></button>
        </div>
    </header>

    <main class="ed-stage v3d-stage" id="stage">
        <div class="v3d-empty" id="empty" hidden>
            <h2>No plan to show</h2>
            <p id="empty-text">Open a plan in the editor and press <strong>3D</strong>.</p>
            <a class="btn" href="<?= e(url('/app/plans')) ?>">Saved Plans</a>
        </div>
        <div class="ed-hint" id="hint" hidden aria-live="polite"></div>
        <div class="v3d-joy" id="joystick" hidden aria-hidden="true"><span></span></div>
    </main>

    <aside class="ed-panel" id="settings" hidden aria-label="3D settings">
        <div class="ed-panel-head"><strong>3D Settings</strong><button type="button" class="ed-x" data-close="settings" aria-label="Close"><?= icon('x') ?></button></div>
        <div class="ed-panel-body">
            <div class="ed-row">
                <label>Walls</label>
                <div class="v3d-seg v3d-seg-block" role="group" aria-label="Wall view">
                    <button type="button" data-wallview="cutaway" title="Outside walls facing you fade away">Cutaway</button>
                    <button type="button" data-wallview="full" title="All walls at full height">Full</button>
                    <button type="button" data-wallview="low" title="Low walls, like a dollhouse">Low</button>
                </div>
            </div>
            <div class="ed-row">
                <label for="wall-height">Wall height <span id="wall-height-label"></span></label>
                <input type="range" id="wall-height" min="0.5" max="1.6" step="0.05" value="1">
            </div>
            <div class="ed-row">
                <label>Wall colour</label>
                <div class="ed-swatches" id="wall-colors">
                    <?php foreach ($swatches as $c): ?><button type="button" data-color="<?= e($c) ?>" style="background:<?= e($c) ?>" aria-label="Wall colour <?= e($c) ?>"></button><?php endforeach; ?>
                </div>
            </div>
            <div class="ed-row">
                <label for="floor-finish">Floor</label>
                <select id="floor-finish">
                    <?php foreach ($floors as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="ed-row v3d-toggles">
                <label><input type="checkbox" data-opt="furniture"> Furniture</label>
                <label><input type="checkbox" data-opt="doors"> Doors</label>
                <label><input type="checkbox" data-opt="doorsOpen"> Doors open</label>
                <label><input type="checkbox" data-opt="windows"> Windows</label>
                <label><input type="checkbox" data-opt="labels"> Room names</label>
                <label><input type="checkbox" data-opt="shadows"> Shadows</label>
            </div>
            <div class="ed-actions-row"><button type="button" class="ed-small-btn" data-cmd="defaults">Restore defaults</button></div>
        </div>
    </aside>
</div>
<noscript><p style="padding:20px">The 3D view needs JavaScript.</p></noscript>
