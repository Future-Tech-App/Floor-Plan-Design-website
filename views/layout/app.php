<?php
/** @var string $content  @var array $site  @var array $flashes  @var string $title  @var string $nav  @var string $pageTitle */
$nav = $nav ?? '';
$links = [
    'dashboard'   => ['/app', 'Dashboard'],
    'templates'   => ['/app/templates', 'Templates'],
    'plans'       => ['/app/plans', 'Saved Plans'],
    'calculators' => ['/app/calculators', 'Calculators'],
    'about'       => ['/app/about', 'About'],
];
?><!doctype html>
<html lang="en" data-base="<?= e(rtrim((string) config('base_path', ''), '/')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Floor Plan Designer') ?></title>
    <meta name="description" content="<?= e($description ?? $site['tagline']) ?>">
    <meta name="theme-color" content="#001d36">
    <link rel="icon" href="<?= e(url('/favicon.ico')) ?>" sizes="any">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(url('/apple-touch-icon.png')) ?>">
    <link rel="manifest" href="<?= e(url('/manifest.json')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;600;700;800&family=Tinos:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('/assets/app/app.css')) ?>">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body class="app nav-<?= e($nav) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="ah" id="app-header">
    <a class="ah-logo" href="<?= e(url('/app')) ?>">
        <img src="<?= e(url('/assets/img/logo.jpeg')) ?>" alt="" width="44" height="44">
        <span><?= e($site['name']) ?></span>
    </a>
    <nav class="ah-nav" id="app-nav" aria-label="App">
        <?php foreach ($links as $key => [$href, $label]): ?>
            <a href="<?= e(url($href)) ?>" class="<?= $nav === $key ? 'active' : '' ?>"<?= $nav === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(url('/app/settings')) ?>" class="<?= $nav === 'settings' ? 'active' : '' ?>">Settings</a>
    </nav>
    <button class="ah-toggle" type="button" aria-expanded="false" aria-controls="app-nav" aria-label="Open menu"><?= icon('menu') ?></button>
    <a class="ah-cta" href="<?= e(url('/app/editor')) ?>">
        <small>Start Designing Today!</small>
        <strong>New plan <?= icon('arrow') ?></strong>
    </a>
</header>

<main id="main">
    <?php foreach ($flashes as $flash): ?>
        <div class="a-container"><div class="a-flash a-flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="af">
    <div class="a-container af-top">
        <a class="af-logo" href="<?= e(url('/app')) ?>">
            <img src="<?= e(url('/assets/img/logo.jpeg')) ?>" alt="" width="40" height="40" loading="lazy">
            <span><?= e($site['name']) ?></span>
        </a>
        <nav class="af-links" aria-label="Footer">
            <a href="<?= e(url('/app/about')) ?>">About</a>
            <a href="<?= e(url('/privacy-policy')) ?>" target="_blank" rel="noopener">Privacy Policy</a>
            <a href="<?= e(url('/terms-of-use')) ?>" target="_blank" rel="noopener">Terms of Service</a>
            <a href="<?= e(url('/support')) ?>" target="_blank" rel="noopener">Help</a>
            <a href="<?= e(url('/app/settings')) ?>">Settings</a>
        </nav>
    </div>
    <div class="a-container af-bottom">
        <span><?= icon('shield') ?> Your plans are saved in this browser on this device. Clearing browser data deletes them.</span>
        <span>Web · v<?= e(preg_replace('/\+.*/', '', $site['version'])) ?></span>
    </div>
</footer>

<script src="<?= e(url('/assets/app/app.js')) ?>" defer></script>
</body>
</html>
