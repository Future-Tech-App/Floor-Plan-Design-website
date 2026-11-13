<?php
/** @var string $content  @var array $site  @var array $flashes  @var string $title  @var string $description  @var string $canonical  @var string $page */
$pageTitle = $title ?? $site['name'];
$pageDesc  = $description ?? $site['tagline'];
$canon     = rtrim($site['site_url'], '/') . ($canonical ?? '/');
$page      = $page ?? '';
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
    <meta name="theme-color" content="#2196F3">
    <meta property="og:site_name" content="<?= e($site['name']) ?>">
    <meta property="og:image" content="<?= e($site['site_url'] . '/android-chrome-512x512.png') ?>">
    <meta name="twitter:card" content="summary">
    <link rel="icon" href="<?= e(url('/favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(url('/favicon-32x32.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(url('/apple-touch-icon.png')) ?>">
    <link rel="manifest" href="<?= e(url('/manifest.json')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/style.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="<?= e(url('/')) ?>">
            <img src="<?= e(url('/assets/img/logo.jpeg')) ?>" alt="" width="34" height="34">
            <span><?= e($site['name']) ?></span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
        <nav id="site-nav" class="site-nav" aria-label="Main">
            <?php foreach ($nav as $key => [$href, $label]): ?>
                <a href="<?= e(url($href)) ?>" class="<?= $page === $key ? 'active' : '' ?>"<?= $page === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
            <a class="btn btn-small" href="<?= e(url('/download')) ?>">Get the app</a>
        </nav>
    </div>
</header>

<main id="main">
    <?php if (!empty($flashes)): ?>
        <div class="container">
            <?php foreach ($flashes as $flash): ?>
                <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <strong><?= e($site['name']) ?></strong>
            <p class="muted"><?= e($site['tagline']) ?></p>
        </div>
        <div>
            <p class="footer-title">Product</p>
            <a href="<?= e(url('/features')) ?>">Features</a>
            <a href="<?= e(url('/download')) ?>">Download</a>
        </div>
        <div>
            <p class="footer-title">Help</p>
            <a href="<?= e(url('/support')) ?>">Support &amp; FAQ</a>
            <a href="<?= e(url('/contact')) ?>">Contact</a>
        </div>
        <div>
            <p class="footer-title">Legal</p>
            <a href="<?= e(url('/privacy-policy')) ?>">Privacy Policy</a>
            <a href="<?= e(url('/terms-of-use')) ?>">Terms of Use</a>
        </div>
    </div>
    <div class="container footer-bottom muted">
        <span>&copy; <?= e($site['year']) ?> <?= e($site['company']) ?>. All rights reserved.</span>
        <span>Version <?= e($site['version']) ?></span>
    </div>
</footer>

<script src="<?= e(url('/assets/js/main.js')) ?>" defer></script>
</body>
</html>
