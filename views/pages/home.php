<?php /** @var array $site */
$highlights = [
    ['pencil', 'Draw & design', 'Walls, rooms, doors and windows in a few taps.'],
    ['cube', 'Live 3D preview', 'Walk around your plan before anything is built.'],
    ['calculator', 'Material estimates', 'Bricks, concrete, paint and tiles, priced locally.'],
];
$services = [   // icon, title, text, photo (public/assets/img, free Unsplash photos)
    ['pencil', 'Floor plan editor', 'Straight, freehand and room tools, six door swings, windows, stairs and columns — with snapping and undo.', 'tools/draw.jpg'],
    ['chair', 'Furniture library', 'Beds, sofas, kitchens, bathrooms, electrical, HVAC, garden and pool items to furnish every room.', 'interior/sofa-green.jpg'],
    ['cube', '3D visualization', 'Isometric 3D with textured floors, lighting, wall openings and walk mode.', 'tools/3d.jpg'],
    ['sparkles', 'AI floor plans', 'Describe a house or office and get a complete layout — or let AI read a photo of an existing plan.', 'tools/ai.jpg'],
    ['calculator', 'Construction calculator', 'Estimate bricks, blocks, concrete, plaster, paint, steel, tiles and labor costs.', 'tools/construction.jpg'],
    ['download', '300 DPI export', 'Print-ready PDFs with title block, scale bar, north arrow and room schedule, or high-res images.', 'tools/export.jpg'],
];
$gallery = [
    ['1bed', '1bed-1.jpg', 'One-bedroom apartment', '1 Bedroom'],
    ['2bed', '2bed-1.jpg', 'Two-bedroom apartment', '2 Bedrooms'],
    ['3bed', '3bed-2.jpg', 'Three-bedroom apartment', '3 Bedrooms'],
    ['1bed', '1bed-2.jpg', 'Compact one-bedroom', '1 Bedroom'],
    ['2bed', '2bed-2.jpg', 'Family two-bedroom', '2 Bedrooms'],
    ['3bed', '3bed-1.jpg', 'Spacious three-bedroom', '3 Bedrooms'],
];
$steps = [
    ['Start a project', 'Begin from scratch or pick a 1, 2 or 3 bedroom template.', 'tools/plans.jpg'],
    ['Draw & furnish', 'Sketch walls, snap in doors and windows, drop in furniture.', 'interior/dining-green.jpg'],
    ['Check it in 3D', 'Rotate and walk through the model to feel the space.', 'interior/kitchen-wood.jpg'],
    ['Export & share', 'Save a 300 DPI PDF or image and send it to anyone.', 'tools/analyzer.jpg'],
];
$faqs = [
    ['Is Floor Plan Designer free?', 'Yes. The web app is free with no ads. The mobile apps are free too; Pro removes their ads.'],
    ['Do I need to install anything?', 'No. Open the web app in Chrome, Edge, Safari or Firefox. Android and iOS apps are available if you prefer.'],
    ['Can I print my plans?', 'Yes. Export a 300 DPI PDF in A4, A3, Letter or Legal with measurements, scale bar and room schedule.'],
];
?>
<section class="hero">
    <div class="hero-bg" aria-hidden="true"></div>
    <div class="container hero-grid">
        <div class="hero-copy reveal">
            <p class="kicker kicker-light">Home design &amp; floor plan app</p>
            <h1>Plan, design &amp; visualize <span class="accent-text">your dream home</span></h1>
            <p class="hero-lead">Draw accurate floor plans, furnish every room, walk through it in 3D and export professional plans — in your browser or on your phone.</p>
            <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(url('/app/')) ?>">Start designing free <?= icon('arrow') ?></a>
                <a class="btn btn-outline-light btn-lg" href="<?= e(url('/features')) ?>">Explore features</a>
            </div>
            <ul class="hero-trust">
                <li><?= icon('check') ?>No sign-up</li>
                <li><?= icon('check') ?>40+ languages</li>
                <li><?= icon('check') ?>Works offline on mobile</li>
            </ul>
        </div>
        <div class="hero-visual reveal" aria-hidden="true">
            <div class="hero-render"><img src="<?= e(url('/assets/img/gallery/3bed-2.jpg')) ?>" alt="" width="1000" height="560"></div>
            <img class="hero-phone" src="<?= e(url('/assets/img/app-home.png')) ?>" alt="" width="260">
            <div class="hero-chip"><?= icon('cube') ?><span><strong>3D</strong> live preview</span></div>
        </div>
    </div>
</section>

<section class="highlights">
    <div class="container highlights-grid">
        <?php foreach ($highlights as $i => [$ic, $name, $text]): ?>
            <article class="highlight-card reveal<?= $i === 1 ? ' is-featured' : '' ?>">
                <span class="highlight-icon"><?= icon($ic) ?></span>
                <div>
                    <h3><?= e($name) ?></h3>
                    <p><?= e($text) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="section about">
    <div class="container about-grid">
        <div class="about-media reveal">
            <img class="about-main" src="<?= e(url('/assets/img/gallery/2bed-1.jpg')) ?>" alt="Furnished two-bedroom floor plan" loading="lazy" width="1000" height="1000">
            <img class="about-inset" src="<?= e(url('/assets/img/editor.jpg')) ?>" alt="Floor plan editor on a phone" loading="lazy">
            <div class="about-badge"><strong>40+</strong><span>languages supported</span></div>
        </div>
        <div class="about-copy reveal">
            <p class="kicker">About the app</p>
            <h2>Professional floor plans, <span class="accent-text">without the learning curve</span></h2>
            <p class="lead">Floor Plan Designer puts architect-grade tools in a simple, touch-friendly app. Whether you're planning a renovation, furnishing a new apartment or sketching a building for a client, you can go from idea to printable plan in minutes.</p>
            <ul class="check-list">
                <li><?= icon('check') ?>Accurate measurements in feet, meters, cm, inches or mm</li>
                <li><?= icon('check') ?>Multi-floor buildings with independent layers</li>
                <li><?= icon('check') ?>Plans backed up automatically — no account needed</li>
            </ul>
            <div class="about-actions">
                <a class="btn btn-primary" href="<?= e(url('/features')) ?>">See all features</a>
                <a class="link-arrow" href="<?= e(url('/support')) ?>">How it works <?= icon('arrow') ?></a>
            </div>
        </div>
    </div>
</section>

<section class="section section-tint" id="services">
    <div class="container">
        <div class="section-head reveal">
            <p class="kicker">What you can do</p>
            <h2>Everything you need to plan a space</h2>
            <p class="muted">From the first wall to a print-ready PDF — one app, every step.</p>
        </div>
        <div class="service-grid">
            <?php foreach ($services as [$ic, $name, $text, $photo]): ?>
                <article class="service-card has-photo reveal">
                    <div class="card-photo"><img src="<?= e(url('/assets/img/' . $photo)) ?>" alt="" loading="lazy" width="760" height="520"></div>
                    <span class="service-icon"><?= icon($ic) ?></span>
                    <h3><?= e($name) ?></h3>
                    <p><?= e($text) ?></p>
                    <a class="link-arrow" href="<?= e(url('/features')) ?>">Learn more <?= icon('arrow') ?></a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="stats-band">
    <div class="container stats-grid">
        <div class="stat"><strong data-count="9" data-suffix="+">9+</strong><span>Design &amp; measuring tools</span></div>
        <div class="stat"><strong data-count="40" data-suffix="+">40+</strong><span>Languages</span></div>
        <div class="stat"><strong data-count="300">300</strong><span>DPI print export</span></div>
        <div class="stat"><strong data-count="3">3</strong><span>Platforms: Web, Android, iOS</span></div>
    </div>
</section>

<section class="section" id="gallery">
    <div class="container">
        <div class="section-head section-head-row reveal">
            <div>
                <p class="kicker">Plan gallery</p>
                <h2>Start from a ready-made layout</h2>
            </div>
            <div class="filters" role="tablist" aria-label="Filter plans">
                <button type="button" class="filter is-active" data-filter="all" role="tab" aria-selected="true">All</button>
                <button type="button" class="filter" data-filter="1bed" role="tab" aria-selected="false">1 Bedroom</button>
                <button type="button" class="filter" data-filter="2bed" role="tab" aria-selected="false">2 Bedrooms</button>
                <button type="button" class="filter" data-filter="3bed" role="tab" aria-selected="false">3 Bedrooms</button>
            </div>
        </div>
        <div class="gallery-grid">
            <?php foreach ($gallery as [$cat, $img, $name, $label]): ?>
                <a class="gallery-item reveal" data-cat="<?= e($cat) ?>" href="<?= e(url('/app/')) ?>">
                    <img src="<?= e(url('/assets/img/gallery/' . $img)) ?>" alt="<?= e($name) ?> floor plan" loading="lazy" width="1000" height="1000">
                    <span class="gallery-overlay">
                        <span class="gallery-tag"><?= e($label) ?></span>
                        <strong><?= e($name) ?></strong>
                        <span class="gallery-cta">Open in the app <?= icon('arrow') ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-tint">
    <div class="container">
        <div class="section-head reveal">
            <p class="kicker">How it works</p>
            <h2>From idea to finished plan in four steps</h2>
        </div>
        <ol class="process">
            <?php foreach ($steps as $i => [$name, $text, $photo]): ?>
                <li class="process-step has-photo reveal">
                    <div class="card-photo"><img src="<?= e(url('/assets/img/' . $photo)) ?>" alt="" loading="lazy" width="760" height="520"></div>
                    <span class="process-num"><?= sprintf('%02d', $i + 1) ?></span>
                    <h3><?= e($name) ?></h3>
                    <p><?= e($text) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<section class="section platforms">
    <div class="container platforms-grid">
        <div class="reveal">
            <p class="kicker">Available everywhere</p>
            <h2>Design on any screen</h2>
            <p class="lead">Use the full app in your browser on a computer or tablet, or install it on your phone to design on site — even offline.</p>
            <div class="platform-cards">
                <a class="platform-card" href="<?= e(url('/app/')) ?>">
                    <?= icon('monitor') ?>
                    <span><strong>Web app</strong><small>Free · no ads · no install</small></span>
                </a>
                <?php if ($site['android_url']): ?>
                <a class="platform-card" href="<?= e($site['android_url']) ?>" target="_blank" rel="noopener">
                    <?= icon('device') ?>
                    <span><strong>Android</strong><small>Get it on Google Play</small></span>
                </a>
                <?php endif; ?>
                <a class="platform-card" href="<?= e($site['ios_url'] ?: url('/download')) ?>"<?= $site['ios_url'] ? ' target="_blank" rel="noopener"' : '' ?>>
                    <?= icon('device') ?>
                    <span><strong>iPhone &amp; iPad</strong><small>iOS 15 or later</small></span>
                </a>
            </div>
        </div>
        <div class="faq-teaser reveal">
            <h3>Quick answers</h3>
            <?php foreach ($faqs as [$q, $a]): ?>
                <details class="faq-item">
                    <summary><?= e($q) ?></summary>
                    <p><?= e($a) ?></p>
                </details>
            <?php endforeach; ?>
            <a class="link-arrow" href="<?= e(url('/support')) ?>">All questions &amp; answers <?= icon('arrow') ?></a>
        </div>
    </div>
</section>
