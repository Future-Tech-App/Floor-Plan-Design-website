<?php
/** Floor plan editor (JavaScript canvas). PHP provides the page and the symbol library. */
$manifestFile = ROOT_DIR . '/public/assets/app/symbols/manifest.json';
$symbols = is_file($manifestFile) ? json_decode((string) file_get_contents($manifestFile), true) : [];
$catNames = [
    'furniture' => 'Furniture', 'tables' => 'Tables', 'desk' => 'Desks', 'appliance' => 'Appliances', 'cooking' => 'Cooking',
    'dishwashers' => 'Dishwashers', 'sinks' => 'Sinks', 'bathtub' => 'Bathtubs', 'toilet' => 'Toilets', 'doors' => 'Doors',
    'windows' => 'Windows', 'gates' => 'Gates', 'linegates' => 'Line Gates', 'number-gates' => 'Number Gates', 'stairs' => 'Stairs',
    'architecture' => 'Architecture', 'basic' => 'Basic', 'electrical' => 'Electrical', 'havc' => 'HVAC', 'communication' => 'Communication',
    'garden' => 'Garden', 'pool' => 'Pool', 'posts' => 'Posts', 'rooms' => 'Rooms', 'custom' => 'Room Labels',
];
$library = [];
foreach ($catNames as $slug => $name) {
    if (!empty($symbols[$slug])) {
        $library[] = ['id' => $slug, 'name' => $name, 'items' => $symbols[$slug]];
    }
}
$svg = [
    'select'    => '<path d="M4 3l7 17 2-7 7-2Z"/>',
    'pan'       => '<path d="M18 11V6a2 2 0 0 0-4 0v5M14 10V4a2 2 0 0 0-4 0v6M10 10.5V6a2 2 0 0 0-4 0v8a8 8 0 0 0 16 0v-3a2 2 0 0 0-4 0"/>',
    'wall'      => '<path d="M3 20V8h6v6h6V4h6v16Z"/>',
    'room'      => '<rect x="3" y="3" width="18" height="18" rx="1"/><path d="M3 12h7v9"/>',
    'door'      => '<path d="M4 21V5h9v16"/><path d="M13 21a9 9 0 0 0-9-9"/>',
    'window'    => '<rect x="3" y="8" width="18" height="8"/><path d="M3 12h18M12 8v8"/>',
    'furniture' => '<path d="M6 19v2M18 19v2M5 11V6a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v5"/><path d="M3 13a2 2 0 0 1 4 0v2h10v-2a2 2 0 0 1 4 0v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
    'label'     => '<path d="M4 7V4h16v3M9 20h6M12 4v16"/>',
    'dim'       => '<path d="M3 12h18M3 8v8M21 8v8M7 10v4M11 10v4M15 10v4"/>',
];
$groups = [
    'Edit'  => [['select', 'Select', 'V', 'Select, move and stretch'], ['pan', 'Pan', 'H', 'Move around the plan']],
    'Build' => [['wall', 'Wall', 'W', 'Draw walls corner to corner'], ['room', 'Room', 'R', 'Drag a room with four walls'], ['door', 'Door', 'D', 'Click a wall to add a door'], ['window', 'Window', 'N', 'Click a wall to add a window']],
    'Add'   => [['furniture', 'Library', 'F', 'Furniture, fixtures & symbols'], ['label', 'Text', 'T', 'Add a text label'], ['dim', 'Measure', 'M', 'Measure a distance']],
];
$ic = static fn (string $d): string => '<svg viewBox="0 0 24 24" class="icon" aria-hidden="true">' . $d . '</svg>';
?>
<div class="ed" id="editor"
     data-symbols-base="<?= e(url('/assets/app/symbols/')) ?>"
     data-library="<?= e(json_encode($library, JSON_UNESCAPED_SLASHES)) ?>">
    <header class="ed-top">
        <a class="ed-back" href="<?= e(url('/app')) ?>" title="Back to dashboard" aria-label="Back to dashboard"><?= icon('chevron-left') ?></a>
        <a class="ed-brand" href="<?= e(url('/app')) ?>" aria-label="Floor Plan Designer dashboard"><img src="<?= e(url('/logo192.png')) ?>" alt="" width="32" height="32"></a>
        <div class="ed-title">
            <input id="plan-name" value="Untitled plan" aria-label="Plan name" maxlength="80" spellcheck="false">
            <span class="ed-save" data-state="new"><i aria-hidden="true"></i><span id="save-state">Not saved yet</span></span>
        </div>
        <div class="ed-history" role="group" aria-label="History">
            <button type="button" class="ed-icon-btn" data-cmd="undo" title="Undo (Ctrl+Z)" aria-label="Undo"><?= $ic('<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>') ?></button>
            <button type="button" class="ed-icon-btn" data-cmd="redo" title="Redo (Ctrl+Y)" aria-label="Redo"><?= $ic('<path d="m15 14 5-5-5-5"/><path d="M20 9H9a5 5 0 0 0 0 10h3"/>') ?></button>
        </div>
        <div class="ed-actions">
            <button type="button" class="ed-btn ed-btn-info" data-cmd="info" title="Plan overview" aria-label="Plan overview"><?= $ic('<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M15 3v18"/>') ?></button>
            <button type="button" class="ed-btn" data-cmd="view3d" title="3D view"><?= $ic('<path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.7Z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/>') ?><span>3D View</span></button>
            <button type="button" class="ed-btn" data-cmd="export" aria-haspopup="true" title="Export"><?= icon('download') ?><span>Export</span></button>
            <button type="button" class="ed-btn ed-btn-primary" data-cmd="save" title="Save (Ctrl+S)"><?= $ic('<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/>') ?><span>Save</span></button>
        </div>
        <div class="ed-menu" id="export-menu" hidden>
            <button type="button" data-cmd="export-png"><strong>PNG image</strong><small>High resolution, with title and scale</small></button>
            <button type="button" data-cmd="export-pdf"><strong>PDF (A4)</strong><small>Ready to print or share</small></button>
            <button type="button" data-cmd="export-json"><strong>Plan file (.json)</strong><small>Back up or move to another browser</small></button>
        </div>
    </header>

    <nav class="ed-tools" aria-label="Tools">
        <?php foreach ($groups as $gname => $tools): ?>
            <div class="ed-tool-group">
                <span class="ed-tool-label"><?= e($gname) ?></span>
                <?php foreach ($tools as [$id, $label, $key, $tip]): ?>
                    <button type="button" class="ed-tool<?= $id === 'select' ? ' is-active' : '' ?>" data-tool="<?= e($id) ?>" data-tip="<?= e($tip) ?>" data-key="<?= e($key) ?>" aria-label="<?= e($label) ?> (<?= e($key) ?>)">
                        <?= $ic($svg[$id]) ?><span><?= e($label) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <div class="ed-tool-group ed-tool-end">
            <button type="button" class="ed-tool ed-tool-phone" data-cmd="undo" aria-label="Undo"><?= $ic('<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>') ?><span>Undo</span></button>
            <button type="button" class="ed-tool ed-tool-phone" data-cmd="redo" aria-label="Redo"><?= $ic('<path d="m15 14 5-5-5-5"/><path d="M20 9H9a5 5 0 0 0 0 10h3"/>') ?><span>Redo</span></button>
            <button type="button" class="ed-tool ed-tool-danger" data-cmd="delete" data-tip="Delete the selection" data-key="Del" aria-label="Delete (Del)"><?= icon('trash') ?><span>Delete</span></button>
        </div>
    </nav>

    <main class="ed-stage" id="stage">
        <canvas id="canvas" tabindex="0" aria-label="Floor plan drawing area"></canvas>

        <div class="ed-start" id="start" hidden>
            <div class="ed-start-card" role="dialog" aria-labelledby="start-title">
                <span class="ed-kicker">New floor plan</span>
                <h2 id="start-title">How would you like to start?</h2>
                <div class="ed-start-grid">
                    <button type="button" class="ed-start-opt" data-start="room"><span class="ed-start-ic"><?= $ic($svg['room']) ?></span><strong>Draw a room</strong><small>Drag a rectangle — the four walls are added for you.</small></button>
                    <button type="button" class="ed-start-opt" data-start="wall"><span class="ed-start-ic"><?= $ic($svg['wall']) ?></span><strong>Draw walls</strong><small>Click from corner to corner for any shape.</small></button>
                    <a class="ed-start-opt" href="<?= e(url('/app/ai')) ?>"><span class="ed-start-ic"><?= icon('sparkles') ?></span><strong>Generate with AI</strong><small>Describe your home in words and get a layout.</small></a>
                    <a class="ed-start-opt" href="<?= e(url('/app/ai/analyzer')) ?>"><span class="ed-start-ic"><?= icon('image') ?></span><strong>From a photo</strong><small>Turn a picture of an existing plan into an editable one.</small></a>
                </div>
                <form class="ed-quick" id="quick-room">
                    <span class="ed-quick-label">Quick start</span>
                    <label>Width <input type="number" name="w" min="0.5" max="200" step="0.1" value="4" inputmode="decimal"></label>
                    <span aria-hidden="true">×</span>
                    <label>Length <input type="number" name="l" min="0.5" max="200" step="0.1" value="5" inputmode="decimal"></label>
                    <span class="ed-quick-unit" id="quick-unit">m</span>
                    <button type="submit" class="ed-btn ed-btn-primary">Create room</button>
                </form>
                <div class="ed-start-foot">
                    <label class="ed-link">Import a plan file (.json)<input type="file" id="start-import" accept="application/json,.json" class="visually-hidden"></label>
                    <button type="button" class="ed-link" data-start="blank">Start with a blank canvas</button>
                </div>
            </div>
        </div>

        <div class="ed-hint" id="hint" hidden aria-live="polite"></div>
        <div class="ed-zoom" role="group" aria-label="Zoom">
            <button type="button" data-cmd="zoom-out" aria-label="Zoom out" title="Zoom out (−)">−</button>
            <span id="zoom-label">100%</span>
            <button type="button" data-cmd="zoom-in" aria-label="Zoom in" title="Zoom in (+)">+</button>
            <button type="button" data-cmd="fit" class="ed-zoom-fit" aria-label="Fit to screen" title="Fit to screen"><?= $ic('<path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/>') ?></button>
        </div>

        <aside class="ed-panel ed-library" id="library" hidden aria-label="Library">
            <div class="ed-panel-head"><strong>Library</strong><button type="button" class="ed-x" data-close="library" aria-label="Close"><?= icon('x') ?></button></div>
            <div class="ed-panel-body">
                <div class="ed-search"><?= icon('search') ?><input type="search" id="lib-search" placeholder="Search beds, sofas, sinks…" aria-label="Search the library"></div>
                <div class="ed-cats" id="lib-cats"></div>
                <div class="ed-lib-grid" id="lib-grid"></div>
            </div>
        </aside>
    </main>

    <aside class="ed-side" id="side" aria-label="Plan details">
        <section class="ed-card ed-props" id="props" hidden aria-label="Properties">
            <div class="ed-panel-head"><strong id="props-title">Properties</strong><button type="button" class="ed-x" data-close="props" aria-label="Close"><?= icon('x') ?></button></div>
            <div class="ed-panel-body" id="props-body"></div>
        </section>
        <div id="plan-info">
            <section class="ed-card">
                <h3>Plan overview</h3>
                <dl class="ed-stats" id="info-stats"></dl>
            </section>
            <section class="ed-card">
                <h3>Rooms</h3>
                <ul class="ed-rooms" id="info-rooms"></ul>
            </section>
            <section class="ed-card">
                <h3>Display</h3>
                <div class="ed-switches">
                    <label class="ed-switch"><input type="checkbox" data-setting="grid"><span></span>Grid</label>
                    <label class="ed-switch"><input type="checkbox" data-setting="snap"><span></span>Snap to grid</label>
                    <label class="ed-switch"><input type="checkbox" data-setting="dimensions"><span></span>Wall lengths</label>
                </div>
                <div class="ed-row-2" style="margin-top:14px">
                    <div class="ed-row"><label for="set-units">Units</label><select id="set-units"><option value="metric">Metric (m)</option><option value="imperial">Imperial (ft)</option></select></div>
                    <div class="ed-row"><label for="set-wall">New walls (cm)</label><input type="number" id="set-wall" min="2" max="100" step="1"></div>
                </div>
            </section>
            <section class="ed-card ed-keys">
                <details>
                    <summary>Keyboard shortcuts</summary>
                    <dl>
                        <div><dt>V / H</dt><dd>Select / Pan</dd></div>
                        <div><dt>W / R</dt><dd>Wall / Room</dd></div>
                        <div><dt>D / N</dt><dd>Door / Window</dd></div>
                        <div><dt>F / T / M</dt><dd>Library / Text / Measure</dd></div>
                        <div><dt>Shift</dt><dd>Straight lines</dd></div>
                        <div><dt>Enter</dt><dd>Finish walls</dd></div>
                        <div><dt>Del</dt><dd>Delete</dd></div>
                        <div><dt>[ ]</dt><dd>Rotate item</dd></div>
                        <div><dt>Ctrl Z / Y</dt><dd>Undo / Redo</dd></div>
                        <div><dt>Ctrl S</dt><dd>Save</dd></div>
                        <div><dt>Space</dt><dd>Hold to pan</dd></div>
                    </dl>
                </details>
            </section>
        </div>
    </aside>

    <footer class="ed-statusbar">
        <span class="ed-status-tool" id="status-tool">Select</span>
        <span id="status">Move the pointer over the plan</span>
        <span class="ed-status-sep"></span>
        <span id="status-units"></span>
    </footer>
</div>
<noscript><p style="padding:20px">The editor needs JavaScript. Please enable it to draw floor plans.</p></noscript>
