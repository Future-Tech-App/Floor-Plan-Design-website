<?php /** @var array $site */ ?>
<section class="page-hero">
    <div class="container">
        <h1>Download <?= e($site['name']) ?></h1>
        <p class="lead">Latest version: <strong><?= e($site['version']) ?></strong></p>
    </div>
</section>

<section class="section">
    <div class="container card-grid two">
        <article class="card store-card">
            <h2>Web browser</h2>
            <p>Use every tool right now in Chrome, Edge, Safari or Firefox — on a computer, tablet or phone. Free, no ads, nothing to install. Plans are saved in your browser.</p>
            <a class="btn" href="<?= e(url('/app/')) ?>">Open the web app</a>
        </article>
        <article class="card store-card">
            <h2>Android</h2>
            <p>For Android phones and tablets. Updates install automatically through Google Play.</p>
            <?php if ($site['android_url']): ?>
                <a class="btn" href="<?= e($site['android_url']) ?>" target="_blank" rel="noopener">Get it on Google Play</a>
            <?php else: ?>
                <p class="muted">Coming soon.</p>
            <?php endif; ?>
        </article>
        <article class="card store-card">
            <h2>iPhone &amp; iPad</h2>
            <p>Requires iOS 15.0 or later. Works on iPhone and iPad.</p>
            <?php if ($site['ios_url']): ?>
                <a class="btn btn-dark" href="<?= e($site['ios_url']) ?>" target="_blank" rel="noopener">Download on the App Store</a>
            <?php else: ?>
                <p class="muted">App Store link coming soon. <a href="<?= e(url('/contact?subject=Other')) ?>">Ask us</a> to be notified.</p>
            <?php endif; ?>
        </article>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <h2>Keeping the app up to date</h2>
        <p>We occasionally require a minimum app version so everyone gets important fixes. If the app asks you to update, tap <em>Update now</em> or open your store and install the latest version. Your saved plans stay on your device.</p>
        <p><a href="<?= e(url('/support')) ?>">Having trouble? Visit support →</a></p>
    </div>
</section>
