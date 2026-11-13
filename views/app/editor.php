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
$tools = [
    ['select', 'Select', 'V', '<path d="M4 3l7 17 2-7 7-2Z"/>'],
    ['wall', 'Wall', 'W', '<path d="M3 21V9l9-6 9 6v12"/><path d="M3 15h18"/>'],
    ['room', 'Room', 'R', '<rect x="3" y="3" width="18" height="18"/><path d="M3 12h7v9"/>'],
    ['door', 'Door', 'D', '<path d="M4 21V5h9v16"/><path d="M13 21a9 9 0 0 0-9-9"/>'],
    ['window', 'Window', 'N', '<rect x="3" y="8" width="18" height="8"/><path d="M3 12h18"/>'],
    ['furniture', 'Library', 'F', '<path d="M6 19v2M18 19v2M5 11V6a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3v5"/><path d="M3 13a2 2 0 0 1 4 0v2h10v-2a2 2 0 0 1 4 0v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>'],
    ['label', 'Label', 'T', '<path d="M4 7V4h16v3M9 20h6M12 4v16"/>'],
    ['dim', 'Measure', 'M', '<path d="M3 12h18M3 8v8M21 8v8"/>'],
    ['pan', 'Pan', 'H', '<path d="M18 11V6a2 2 0 0 0-4 0v5M14 10V4a2 2 0 0 0-4 0v6M10 10.5V6a2 2 0 0 0-4 0v8a8 8 0 0 0 16 0v-3a2 2 0 0 0-4 0"/>'],
];
?>
<div class="ed" id="editor"
     data-symbols-base="<?= e(url('/assets/app/symbols/')) ?>"
     data-library="<?= e(json_encode($library, JSON_UNESCAPED_SLASHES)) ?>">
    <header class="ed-top">
        <a class="ed-back" href="<?= e(url('/app')) ?>" title="Dashboard" aria-label="Back to dashboard"><?= icon('chevron-left') ?></a>
        <div class="ed-title">
            <input id="plan-name" value="Untitled plan" aria-label="Plan name" maxlength="80">
            <span id="save-state">Not saved</span>
        </div>
        <div class="ed-actions">
            <button type="button" class="ed-btn" data-cmd="undo" title="Undo (Ctrl+Z)" aria-label="Undo"><svg viewBox="0 0 24 24" class="icon"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/></svg></button>
            <button type="button" class="ed-btn" data-cmd="redo" title="Redo (Ctrl+Y)" aria-label="Redo"><svg viewBox="0 0 24 24" class="icon"><path d="m15 14 5-5-5-5"/><path d="M20 9H9a5 5 0 0 0 0 10h3"/></svg></button>
            <button type="button" class="ed-btn ed-btn-fit" data-cmd="fit" title="Fit to screen" aria-label="Fit to screen"><svg viewBox="0 0 24 24" class="icon"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/></svg></button>
            <button type="button" class="ed-btn ed-btn-text" data-cmd="export" aria-haspopup="true"><?= icon('download') ?><span>Export</span></button>
            <button type="button" class="ed-btn ed-btn-primary" data-cmd="save" title="Save (Ctrl+S)"><svg viewBox="0 0 24 24" class="icon"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg><span>Save</span></button>
        </div>
        <div class="ed-menu" id="export-menu" hidden>
            <button type="button" data-cmd="export-png">PNG image</button>
            <button type="button" data-cmd="export-pdf">PDF (A4)</button>
            <button type="button" data-cmd="export-json">Plan file (.json)</button>
        </div>
    </header>

    <nav class="ed-tools" aria-label="Tools">
        <?php foreach ($tools as [$id, $label, $key, $svg]): ?>
            <button type="button" class="ed-tool<?= $id === 'select' ? ' is-active' : '' ?>" data-tool="<?= e($id) ?>" title="<?= e($label) ?> (<?= e($key) ?>)">
                <svg viewBox="0 0 24 24" class="icon"><?= $svg ?></svg><span><?= e($label) ?></span>
            </button>
        <?php endforeach; ?>
        <span class="ed-tools-sep"></span>
        <button type="button" class="ed-tool ed-tool-danger" data-cmd="delete" title="Delete (Del)"><?= icon('trash') ?><span>Delete</span></button>
    </nav>

    <main class="ed-stage" id="stage">
        <canvas id="canvas" tabindex="0" aria-label="Floor plan drawing area"></canvas>
        <div class="ed-hint" id="hint" hidden aria-live="polite"></div>
        <div class="ed-zoom">
            <button type="button" data-cmd="zoom-in" aria-label="Zoom in">+</button>
            <span id="zoom-label">100%</span>
            <button type="button" data-cmd="zoom-out" aria-label="Zoom out">−</button>
        </div>
        <div class="ed-status" id="status"></div>
    </main>

    <aside class="ed-panel" id="props" hidden aria-label="Properties">
        <div class="ed-panel-head"><strong id="props-title">Properties</strong><button type="button" class="ed-x" data-close="props" aria-label="Close"><?= icon('x') ?></button></div>
        <div class="ed-panel-body" id="props-body"></div>
    </aside>

    <aside class="ed-panel ed-library" id="library" hidden aria-label="Library">
        <div class="ed-panel-head"><strong>Library</strong><button type="button" class="ed-x" data-close="library" aria-label="Close"><?= icon('x') ?></button></div>
        <div class="ed-panel-body">
            <input type="search" id="lib-search" placeholder="Search furniture, doors, sinks…" class="ed-input">
            <div class="ed-cats" id="lib-cats"></div>
            <div class="ed-lib-grid" id="lib-grid"></div>
        </div>
    </aside>
</div>
<noscript><p style="padding:20px">The editor needs JavaScript. Please enable it to draw floor plans.</p></noscript>
