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

<?php
$footCols = [
    'Design' => [
        ['/app/editor', 'New Floor Plan'], ['/app/ai', 'AI Floor Plans'], ['/app/ai/analyzer', 'AI Plan Analyzer'],
        ['/app/templates', 'Plan Templates'], ['/app/plans', 'Saved Plans & 3D'],
    ],
    'Tools' => [
        ['/app/calculators', 'Construction Calculator'], ['/app/calculators#metal', 'Metal Calculator'], ['/app/calculators/area', 'Area Calculator'],
        ['/app/calculators#converter', 'Unit Converters'], ['/app/notes', 'Notes'],
    ],
    'Company' => [
        ['/app/about', 'About'], ['/support', 'Help & FAQ', true], ['/contact', 'Contact Us', true],
        ['/privacy-policy', 'Privacy Policy', true], ['/terms-of-use', 'Terms of Service', true],
    ],
];
$hideCta = in_array($nav, ['ai'], true);
?>
<?php if (!$hideCta): ?>
<section class="af-cta" aria-label="Start designing">
    <div class="a-container af-cta-inner">
        <div>
            <span class="kicker kicker-light">Free · No sign-up</span>
            <h2>Ready To Design Your Dream Home?</h2>
            <p>Draw, furnish and walk through your plan in 3D — right in your browser.</p>
        </div>
        <div class="af-cta-actions">
            <a class="btn" href="<?= e(url('/app/editor')) ?>"><?= icon('plus') ?> Start a New Plan</a>
            <a class="btn btn-ghost-light" href="<?= e(url('/app/ai')) ?>"><?= icon('sparkles') ?> Generate with AI</a>
        </div>
    </div>
</section>
<?php endif; ?>

<footer class="af">
    <div class="a-container af-grid">
        <div class="af-brand">
            <a class="af-logo" href="<?= e(url('/app')) ?>">
                <img src="<?= e(url('/assets/img/logo.jpeg')) ?>" alt="" width="44" height="44" loading="lazy">
                <span><?= e($site['name']) ?></span>
            </a>
            <p><?= e($site['tagline']) ?></p>
            <div class="af-stores">
                <?php if (!empty($site['android_url'])): ?>
                    <a class="af-store" href="<?= e($site['android_url']) ?>" target="_blank" rel="noopener"><?= icon('device') ?><span><small>Get it on</small>Google Play</span></a>
                <?php endif; ?>
                <?php if (!empty($site['ios_url'])): ?>
                    <a class="af-store" href="<?= e($site['ios_url']) ?>" target="_blank" rel="noopener"><?= icon('device') ?><span><small>Download on the</small>App Store</span></a>
                <?php else: ?>
                    <span class="af-store is-soon" title="Coming soon"><?= icon('device') ?><span><small>Coming soon</small>App Store</span></span>
                <?php endif; ?>
            </div>
            <a class="af-mail" href="mailto:<?= e($site['support_email']) ?>"><?= icon('mail') ?><?= e($site['support_email']) ?></a>
        </div>
        <?php foreach ($footCols as $heading => $items): ?>
            <nav class="af-col" aria-label="<?= e($heading) ?>">
                <h3><?= e($heading) ?></h3>
                <ul>
                    <?php foreach ($items as $item): [$href, $label] = $item; $ext = !empty($item[2]); ?>
                        <li><a href="<?= e(url($href)) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?>><?= e($label) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        <?php endforeach; ?>
    </div>
    <div class="a-container af-bottom">
        <span>© <?= date('Y') ?> <?= e($site['name']) ?>. All rights reserved.</span>
        <span class="af-note"><?= icon('shield') ?> Your plans stay in this browser — no account needed.</span>
        <span class="af-meta"><a href="<?= e(url('/app/about#credits')) ?>">Photos: Unsplash</a><i aria-hidden="true">·</i>Web v<?= e(preg_replace('/\+.*/', '', $site['version'])) ?></span>
        <a class="af-up" href="#app-header" aria-label="Back to top" title="Back to top"><?= icon('chevron-up') ?></a>
    </div>
</footer>

<script src="<?= e(url('/assets/app/app.js')) ?>" defer></script>
</body>
</html>
