<?php
$groups = [
    'Design' => [
        ['Wall tools', 'Straight walls, freehand drawing and a room tool that draws four walls at once. Default wall thickness is 15 cm and fully adjustable.'],
        ['Doors & windows', 'Six door swing types plus a catalogue of windows that cut openings in the wall automatically.'],
        ['Stairs & columns', 'Add stairs, posts and columns for realistic structural layouts.'],
        ['Furniture library', 'Furniture, appliances, kitchen, bathroom, electrical, HVAC, garden, pool and vehicle icons.'],
        ['Templates', 'Start from one, two or three bedroom apartment templates and customise them.'],
        ['Undo / redo', 'Full edit history so you can experiment freely.'],
    ],
    'Visualize' => [
        ['3D preview', 'Isometric 3D with depth-sorted rendering, wall openings and adaptive furniture heights.'],
        ['Textures', 'Terrazzo, concrete, wood, marble and grass floors with directional lighting.'],
        ['Gestures', 'Rotate with inertia and pinch-to-zoom from 0.4× to 4×.'],
        ['Multi-floor & layers', 'Design multi-storey buildings with independent levels and layer management.'],
    ],
    'Measure & calculate' => [
        ['Measure ruler', 'Distances in ft, m, cm, in and mm.'],
        ['Area calculator', 'Areas and volumes for common shapes.'],
        ['Construction calculator', 'Material estimates for bricks, concrete, paint and tiles with local pricing.'],
        ['Bubble level', 'A digital spirit level using your phone\'s accelerometer.'],
    ],
    'Share' => [
        ['PDF & image export', '300 DPI output ready for printing or sending to a contractor.'],
        ['Notes', 'Attach notes and annotations to a project.'],
        ['40+ languages', 'Use the app in English, Urdu, Hindi, Arabic, Chinese, Spanish, French, German and many more.'],
        ['Offline first', 'Plans are stored on your device; the editor works without internet.'],
    ],
];
?>
<section class="page-hero">
    <div class="container">
        <h1>Features</h1>
        <p class="lead">A complete floor-planning toolkit in your pocket.</p>
    </div>
</section>

<?php foreach ($groups as $group => $items): ?>
<section class="section<?= $group === 'Visualize' || $group === 'Share' ? ' section-alt' : '' ?>">
    <div class="container">
        <h2><?= e($group) ?></h2>
        <div class="card-grid">
            <?php foreach ($items as [$name, $text]): ?>
                <article class="card">
                    <h3><?= e($name) ?></h3>
                    <p><?= e($text) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endforeach; ?>

<section class="section">
    <div class="container">
        <h2>Web, Free and Pro</h2>
        <div class="table-wrap">
            <table class="compare">
                <thead><tr><th>Feature</th><th>Web app</th><th>Mobile free</th><th>Mobile Pro</th></tr></thead>
                <tbody>
                    <tr><td>Editor, furniture, 3D preview</td><td>✓</td><td>✓</td><td>✓</td></tr>
                    <tr><td>AI floor plan generator &amp; analyzer</td><td>✓</td><td>✓</td><td>✓</td></tr>
                    <tr><td>Measure ruler &amp; calculators</td><td>✓</td><td>✓</td><td>✓</td></tr>
                    <tr><td>Bubble level &amp; compass</td><td>Phones/tablets¹</td><td>✓</td><td>✓</td></tr>
                    <tr><td>PDF &amp; image export</td><td>Downloads</td><td>✓</td><td>✓</td></tr>
                    <tr><td>Ad-free</td><td>✓</td><td>—</td><td>✓</td></tr>
                </tbody>
            </table>
        </div>
        <p class="muted small">¹ Browsers only provide motion sensors on phones and tablets, and most browsers don't provide compass data — use the mobile app for the compass. Pro is available inside the mobile apps as a 1-day pass, monthly, 6-month, yearly or lifetime purchase.</p>
    </div>
</section>
