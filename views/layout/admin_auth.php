<?php /** Sign-in / setup layout. @var string $content  @var array $site  @var array $flashes  @var string $title */ ?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Admin') ?></title>
    <link rel="icon" href="<?= e(url('/favicon.ico')) ?>" sizes="any">
    <link rel="stylesheet" href="<?= e(url('/assets/admin/admin.css')) ?>">
</head>
<body class="ad-auth">
<main class="ad-auth-card">
    <a class="ad-auth-brand" href="<?= e(url('/')) ?>">
        <img src="<?= e(url('/assets/img/logo.jpeg')) ?>" alt="" width="52" height="52">
        <span><strong><?= e($site['name']) ?></strong><small>Admin panel</small></span>
    </a>
    <?php foreach ($flashes as $flash): ?>
        <div class="ad-flash ad-flash-<?= e($flash['type']) ?>" role="status"><span><?= e($flash['message']) ?></span></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<p class="ad-auth-foot"><a href="<?= e(url('/')) ?>">← Back to the website</a></p>
</body>
</html>
