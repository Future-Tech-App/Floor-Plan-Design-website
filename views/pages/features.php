<?php
$bannerTitle  = 'Features';
$bannerKicker = 'Everything in one app';
$bannerLead   = 'A complete floor-planning toolkit — design, visualize, measure, calculate and share.';
require ROOT_DIR . '/views/partials/page_banner.php';

$groups = [
    [
        'kicker' => 'Design',
        'title'  => 'Draw accurate plans in minutes',
        'image'  => 'ill/design.png',
        'items'  => [
            ['pencil', 'Wall tools', 'Straight walls, freehand drawing and a room tool that draws four walls at once. Wall thickness starts at 15 cm and is fully adjustable.'],
            ['home', 'Doors, windows, stairs & columns', 'Six door swing types and a window catalogue that cut openings in walls automatically.'],
            ['layers', 'Templates, layers & undo', 'Start from 1, 2 or 3 bedroom templates, organize with layers and experiment freely with full undo/redo.'],
        ],
    ],
    [
        'kicker' => 'Furnish & visualize',
        'title'  => 'See your space before it’s built',
        'image'  => 'ill/3d.png',
        'items'  => [
            ['chair', 'Furniture library', 'Furniture, appliances, kitchens, bathrooms, electrical, HVAC, garden, pool and vehicles.'],
            ['cube', 'Interactive 3D', 'Isometric 3D with depth-sorted rendering, wall openings, textured floors and directional lighting.'],
            ['layers', 'Multi-floor buildings', 'Design multi-storey buildings with independent floors and layer management.'],
        ],
    ],
    [
        'kicker' => 'Measure & calculate',
        'title'  => 'Plan your budget with confidence',
        'image'  => 'ill/furniture.png',
        'items'  => [
            ['ruler', 'Measure ruler & area calculator', 'Distances in ft, m, cm, in and mm; areas and volumes of common shapes.'],
            ['calculator', 'Construction calculator', 'Material estimates for bricks, blocks, concrete, plaster, paint, steel and tiles — with local pricing.'],
            ['compass', 'Bubble level & compass', 'A digital spirit level and compass using your phone’s sensors.'],
        ],
    ],
    [
        'kicker' => 'AI & sharing',
        'title'  => 'Smarter starts, professional results',
        'image'  => 'ill/share.png',
        'items'  => [
            ['sparkles', 'AI floor plan generator & analyzer', 'Describe a building to generate a layout, or upload a photo of a plan to detect its rooms.'],
            ['download', '300 DPI PDF & image export', 'Title block, scale bar, north arrow and room schedule on A4, A3, Letter or Legal.'],
            ['globe', '40+ languages', 'English, Urdu, Hindi, Arabic, Chinese, Spanish, French, German and many more.'],
        ],
    ],
];
?>

<?php foreach ($groups as $i => $g): ?>
<section class="section<?= $i % 2 ? ' section-tint' : '' ?>">
    <div class="container feature-row<?= $i % 2 ? ' reverse' : '' ?>">
        <div class="feature-media reveal">
            <img src="<?= e(url('/assets/img/' . $g['image'])) ?>" alt="" loading="lazy">
        </div>
        <div class="reveal">
            <p class="kicker"><?= e($g['kicker']) ?></p>
            <h2><?= e($g['title']) ?></h2>
            <div class="feature-list">
                <?php foreach ($g['items'] as [$ic, $name, $text]): ?>
                    <div class="feature-list-item">
                        <span class="card-icon"><?= icon($ic) ?></span>
                        <div>
                            <h3><?= e($name) ?></h3>
                            <p><?= e($text) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endforeach; ?>

<section class="section">
    <div class="container narrow">
        <div class="section-head reveal">
            <p class="kicker">Compare</p>
            <h2>Web, Free and Pro</h2>
        </div>
        <div class="table-wrap reveal">
            <table class="compare">
                <thead><tr><th>Feature</th><th>Web app</th><th>Mobile free</th><th>Mobile Pro</th></tr></thead>
                <tbody>
                    <tr><td>Editor, furniture, 3D preview</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
                    <tr><td>AI floor plan generator &amp; analyzer</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
                    <tr><td>Measure ruler &amp; calculators</td><td class="yes">✓</td><td class="yes">✓</td><td class="yes">✓</td></tr>
                    <tr><td>Bubble level &amp; compass</td><td>Phones/tablets¹</td><td class="yes">✓</td><td class="yes">✓</td></tr>
                    <tr><td>PDF &amp; image export</td><td>Downloads</td><td class="yes">✓</td><td class="yes">✓</td></tr>
                    <tr><td>Ad-free</td><td class="yes">✓</td><td>—</td><td class="yes">✓</td></tr>
                </tbody>
            </table>
        </div>
        <p class="muted small">¹ Browsers only provide motion sensors on phones and tablets, and most browsers don't provide compass data — use the mobile app for the compass. Pro is available inside the mobile apps as a 1-day pass, monthly, 6-month, yearly or lifetime purchase.</p>
    </div>
</section>
