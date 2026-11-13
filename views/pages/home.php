<?php /** @var array $site */
$features = [
    ['✏️', 'Floor plan editor', 'Draw walls with straight, freehand and room tools. Add doors with 6 swing types, windows, stairs and columns.'],
    ['🛋️', 'Furniture library', 'Beds, sofas, tables, kitchen appliances, bathroom fixtures, garden and pool items — drag them onto your plan.'],
    ['🧊', '3D preview', 'Rotate and zoom an isometric 3D view with textured floors, lighting and 3D furniture.'],
    ['📏', 'Measure ruler', 'Measure between any two points in feet, meters, centimetres, inches or millimetres.'],
    ['🧮', 'Construction calculator', 'Estimate bricks, concrete, paint and tiles for your project.'],
    ['📄', '300 DPI export', 'Export high-quality PDFs and images ready to print or share.'],
];
?>
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <p class="eyebrow">Version <?= e($site['version']) ?> · Web, Android &amp; iOS</p>
            <h1>Design your home's floor plan <span class="accent">right from your phone</span></h1>
            <p class="lead">Draw rooms, place doors, windows and furniture, then see it all in 3D. Measure, calculate materials and export professional plans — no laptop required.</p>
            <div class="cta-row">
                <a class="btn" href="<?= e(url('/app/')) ?>">Open in your browser</a>
                <?php if ($site['android_url']): ?>
                    <a class="btn btn-ghost" href="<?= e($site['android_url']) ?>" target="_blank" rel="noopener">Get it on Google Play</a>
                <?php endif; ?>
                <?php if ($site['ios_url']): ?>
                    <a class="btn btn-dark" href="<?= e($site['ios_url']) ?>" target="_blank" rel="noopener">Download on the App Store</a>
                <?php else: ?>
                    <a class="btn btn-ghost" href="<?= e(url('/features')) ?>">Explore features</a>
                <?php endif; ?>
            </div>
            <ul class="hero-points">
                <li>Web, Android &amp; iOS</li>
                <li>Works offline</li>
                <li>40+ languages</li>
                <li>Multi-floor &amp; layers</li>
            </ul>
        </div>
        <div class="hero-media">
            <img class="phone" src="<?= e(url('/assets/img/app-home.png')) ?>" alt="Floor Plan Designer home screen" width="300" loading="eager">
            <img class="phone phone-back" src="<?= e(url('/assets/img/editor.jpg')) ?>" alt="Floor plan editor with walls and furniture" width="300" loading="lazy">
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="stats">
            <div><strong>9+</strong><span>Tools</span></div>
            <div><strong>40+</strong><span>Languages</span></div>
            <div><strong>300</strong><span>DPI export</span></div>
            <div><strong>3D</strong><span>Live preview</span></div>
            <div><strong>5+</strong><span>Plan types</span></div>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <h2>Everything you need to plan a space</h2>
            <p class="muted">From the first wall to a printable PDF.</p>
        </div>
        <div class="card-grid">
            <?php foreach ($features as [$icon, $name, $text]): ?>
                <article class="card">
                    <div class="card-icon" aria-hidden="true"><?= $icon ?></div>
                    <h3><?= e($name) ?></h3>
                    <p><?= e($text) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="center"><a class="btn btn-ghost" href="<?= e(url('/features')) ?>">See all features</a></p>
    </div>
</section>

<section class="section">
    <div class="container split">
        <div>
            <h2>How it works</h2>
            <ol class="steps">
                <li><strong>Start a project</strong> — begin from scratch or from a 1, 2 or 3 bedroom apartment template.</li>
                <li><strong>Draw and furnish</strong> — sketch walls, snap doors and windows into them, drop in furniture.</li>
                <li><strong>Check it in 3D</strong> — rotate the model to see how the rooms feel.</li>
                <li><strong>Export and share</strong> — save a 300 DPI PDF or image and send it to anyone.</li>
            </ol>
        </div>
        <img class="rounded shadow" src="<?= e(url('/assets/img/editor.jpg')) ?>" alt="Editor screenshot" loading="lazy">
    </div>
</section>

<section class="section cta-band">
    <div class="container center">
        <h2>Ready to design your space?</h2>
        <p>Start right now in your browser — free and ad-free — or download the mobile app.</p>
        <div class="cta-row" style="justify-content:center">
            <a class="btn btn-light" href="<?= e(url('/app/')) ?>">Open the web app</a>
            <a class="btn btn-ghost" style="color:#fff;border-color:#fff" href="<?= e(url('/download')) ?>">Download</a>
        </div>
    </div>
</section>
