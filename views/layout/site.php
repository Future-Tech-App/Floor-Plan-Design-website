<?php
/** @var string $content  @var array $site  @var array $flashes  @var string $title  @var string $description  @var string $canonical  @var string $page */
$pageTitle = $title ?? $site['name'];
$pageDesc  = $description ?? $site['tagline'];
$canon     = rtrim($site['site_url'], '/') . ($canonical ?? '/');
$page      = $page ?? '';
$showCta   = $showCta ?? !in_array($page, ['contact', ''], true);
$nav = [
    'home'     => ['/', 'Home'],
    'features' => ['/features', 'Features'],
    'download' => ['/download', 'Download'],
    'support'  => ['/support', 'Support'],
    'contact'  => ['/contact', 'Contact'],
];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDesc) ?>">
    <link rel="canonical" href="<?= e($canon) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDesc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($canon) ?>">
    <meta property="og:site_name" content="<?= e($site['name']) ?>">
    <meta property="og:image" content="<?= e($site['site_url'] . '/android-chrome-512x512.png') ?>">
    <meta name="twitter:card" content="summary">
    <meta name="theme-color" content="#0f1c2e">
    <link rel="icon" href="<?= e(url('/favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(url('/favicon-32x32.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(url('/apple-touch-icon.png')) ?>">
    <link rel="manifest" href="<?= e(url('/manifest.json')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('/assets/css/style.css')) ?>">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body class="page-<?= e($page ?: 'other') ?>">
<a class="skip-link" href="#main">Skip to content</a>

<div class="topbar">
    <div class="container topbar-inner">
        <div class="topbar-info">
            <a href="mailto:<?= e($site['support_email']) ?>"><?= icon('mail') ?><span><?= e($site['support_email']) ?></span></a>
            <span class="topbar-sep" aria-hidden="true"></span>
            <span class="topbar-hide-sm"><?= icon('device') ?>Web · Android · iOS</span>
        </div>
        <div class="topbar-links">
            <a href="<?= e(url('/support')) ?>">Help center</a>
            <a href="<?= e(url('/app/')) ?>" class="topbar-strong">Open web app <?= icon('arrow') ?></a>
        </div>
    </div>
</div>

<header class="site-header" id="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="<?= e(url('/')) ?>">
            <img src="<?= e(url('/assets/img/logo.jpeg')) ?>" alt="" width="44" height="44">
            <span class="brand-text">
                <strong><?= e($site['name']) ?></strong>
                <small>Floor plan &amp; home design</small>
            </span>
        </a>
        <nav id="site-nav" class="site-nav" aria-label="Main">
            <?php foreach ($nav as $key => [$href, $label]): ?>
                <a href="<?= e(url($href)) ?>" class="<?= $page === $key ? 'active' : '' ?>"<?= $page === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
            <a class="btn btn-primary nav-cta" href="<?= e(url('/app/')) ?>">Start designing</a>
        </nav>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Open menu">
            <?= icon('menu') ?>
        </button>
    </div>
</header>

<main id="main">
    <?php if (!empty($flashes)): ?>
        <div class="container flash-wrap">
            <?php foreach ($flashes as $flash): ?>
                <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $content ?>

    <?php if ($showCta): ?>
    <section class="cta-band">
        <div class="container cta-band-inner">
            <div>
                <p class="kicker kicker-light">Free · No ads · Nothing to install</p>
                <h2>Ready to design your dream space?</h2>
                <p>Open Floor Plan Designer in your browser right now, or take it with you on Android and iOS.</p>
            </div>
            <div class="cta-band-actions">
                <a class="btn btn-light btn-lg" href="<?= e(url('/app/')) ?>">Open the web app <?= icon('arrow') ?></a>
                <a class="btn btn-outline-light btn-lg" href="<?= e(url('/download')) ?>">Get the mobile app</a>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>

<footer class="site-footer">
    <div class="container footer-highlights" aria-label="Highlights">
        <div><span class="fh-icon"><?= icon('check') ?></span><span><strong>Free to use</strong><small>No ads, no sign-up</small></span></div>
        <div><span class="fh-icon"><?= icon('monitor') ?></span><span><strong>Web, Android &amp; iOS</strong><small>Design anywhere</small></span></div>
        <div><span class="fh-icon"><?= icon('cube') ?></span><span><strong>3D walkthrough</strong><small>See every room</small></span></div>
        <div><span class="fh-icon"><?= icon('download') ?></span><span><strong>PDF &amp; PNG export</strong><small>Print-ready plans</small></span></div>
    </div>
    <div class="container footer-grid">
        <div class="footer-about">
            <a class="brand brand-footer" href="<?= e(url('/')) ?>">
                <img src="<?= e(url('/assets/img/logo.jpeg')) ?>" alt="" width="44" height="44" loading="lazy">
                <span class="brand-text"><strong><?= e($site['name']) ?></strong><small>Floor plan &amp; home design</small></span>
            </a>
            <p><?= e($site['tagline']) ?></p>
            <div class="footer-stores">
                <?php if ($site['android_url']): ?>
                    <a class="footer-store" href="<?= e($site['android_url']) ?>" target="_blank" rel="noopener"><?= icon('device') ?><span><small>Get it on</small>Google Play</span></a>
                <?php endif; ?>
                <?php if ($site['ios_url']): ?>
                    <a class="footer-store" href="<?= e($site['ios_url']) ?>" target="_blank" rel="noopener"><?= icon('device') ?><span><small>Download on the</small>App Store</span></a>
                <?php else: ?>
                    <span class="footer-store is-soon"><?= icon('device') ?><span><small>Coming soon</small>App Store</span></span>
                <?php endif; ?>
            </div>
        </div>
        <nav aria-label="Product">
            <h3 class="footer-title">Product</h3>
            <ul class="footer-links">
                <li><a href="<?= e(url('/app')) ?>">Web app</a></li>
                <li><a href="<?= e(url('/features')) ?>">Features</a></li>
                <li><a href="<?= e(url('/#gallery')) ?>">Plan gallery</a></li>
                <li><a href="<?= e(url('/download')) ?>">Download</a></li>
            </ul>
        </nav>
        <nav aria-label="Tools">
            <h3 class="footer-title">Tools</h3>
            <ul class="footer-links">
                <li><a href="<?= e(url('/app/editor')) ?>">Floor plan editor</a></li>
                <li><a href="<?= e(url('/app/ai')) ?>">AI floor plans</a></li>
                <li><a href="<?= e(url('/app/ai/analyzer')) ?>">AI plan analyzer</a></li>
                <li><a href="<?= e(url('/app/calculators')) ?>">Construction calculator</a></li>
            </ul>
        </nav>
        <nav aria-label="Help">
            <h3 class="footer-title">Help</h3>
            <ul class="footer-links">
                <li><a href="<?= e(url('/support')) ?>">Support &amp; FAQ</a></li>
                <li><a href="<?= e(url('/contact')) ?>">Contact us</a></li>
                <li><a href="<?= e(url('/privacy-policy')) ?>">Privacy Policy</a></li>
                <li><a href="<?= e(url('/terms-of-use')) ?>">Terms of Use</a></li>
            </ul>
        </nav>
        <div class="footer-contact-col">
            <h3 class="footer-title">Get in touch</h3>
            <ul class="footer-contact">
                <li><?= icon('mail') ?><a href="mailto:<?= e($site['support_email']) ?>"><?= e($site['support_email']) ?></a></li>
                <li><?= icon('clock') ?><span>Replies within 1–2 business days</span></li>
            </ul>
            <a class="btn btn-light footer-msg" href="<?= e(url('/contact')) ?>">Send us a message <?= icon('arrow') ?></a>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container footer-bottom-inner">
            <span>&copy; <?= e($site['year']) ?> <?= e($site['company']) ?>. All rights reserved.</span>
            <nav class="footer-legal" aria-label="Legal">
                <a href="<?= e(url('/privacy-policy')) ?>">Privacy</a>
                <a href="<?= e(url('/terms-of-use')) ?>">Terms</a>
                <a href="<?= e(url('/sitemap.xml')) ?>">Sitemap</a>
                <a href="<?= e(url('/app/about#credits')) ?>">Photo credits</a>
            </nav>
            <span>Version <?= e(preg_replace('/\+.*/', '', $site['version'])) ?></span>
        </div>
    </div>
</footer>

<a href="#main" class="to-top" aria-label="Back to top"><?= icon('chevron-up') ?></a>
<script src="<?= e(url('/assets/js/main.js')) ?>" defer></script>
</body>
</html>
