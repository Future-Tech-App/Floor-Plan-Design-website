<?php /** @var array $site */
$bannerTitle  = 'Download';
$bannerKicker = 'Latest version ' . $site['version'];
$bannerLead   = 'Use Floor Plan Designer in your browser, or install it on Android and iOS.';
require ROOT_DIR . '/views/partials/page_banner.php';
?>
<section class="section">
    <div class="container card-grid three">
        <article class="card store-card is-featured reveal">
            <span class="card-icon"><?= icon('monitor') ?></span>
            <h2>Web browser</h2>
            <p>Every tool, right now, in Chrome, Edge, Safari or Firefox — on a computer, tablet or phone. Free, no ads, nothing to install. Plans are saved in your browser.</p>
            <a class="btn btn-light" href="<?= e(url('/app/')) ?>">Open the web app <?= icon('arrow') ?></a>
        </article>
        <article class="card store-card reveal">
            <span class="card-icon"><?= icon('device') ?></span>
            <h2>Android</h2>
            <p>For Android phones and tablets. Works offline, and updates install automatically through Google Play.</p>
            <?php if ($site['android_url']): ?>
                <a class="btn" href="<?= e($site['android_url']) ?>" target="_blank" rel="noopener">Get it on Google Play <?= icon('arrow') ?></a>
            <?php else: ?>
                <p class="muted">Coming soon.</p>
            <?php endif; ?>
        </article>
        <article class="card store-card reveal">
            <span class="card-icon"><?= icon('device') ?></span>
            <h2>iPhone &amp; iPad</h2>
            <p>Requires iOS 15.0 or later. Works on iPhone and iPad, online or offline.</p>
            <?php if ($site['ios_url']): ?>
                <a class="btn btn-dark" href="<?= e($site['ios_url']) ?>" target="_blank" rel="noopener">Download on the App Store <?= icon('arrow') ?></a>
            <?php else: ?>
                <p class="muted">App Store link coming soon. <a href="<?= e(url('/contact?subject=Other')) ?>">Ask us</a> to be notified.</p>
            <?php endif; ?>
        </article>
    </div>
</section>

<section class="section section-tint">
    <div class="container split">
        <div class="reveal">
            <p class="kicker">Stay up to date</p>
            <h2>Keeping the app current</h2>
            <p class="lead">We occasionally require a minimum app version so everyone gets important fixes. If the app asks you to update, tap <em>Update now</em> or open your store and install the latest version. Your saved plans stay on your device.</p>
            <a class="link-arrow" href="<?= e(url('/support')) ?>">Having trouble? Visit support <?= icon('arrow') ?></a>
        </div>
        <div class="feature-media reveal"><img src="<?= e(url('/assets/img/app-home.png')) ?>" alt="Floor Plan Designer home screen" loading="lazy" style="max-height:380px;border-radius:24px;box-shadow:var(--shadow)"></div>
    </div>
</section>
