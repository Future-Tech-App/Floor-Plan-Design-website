<?php
/** @var string $content  @var array $site  @var array $flashes  @var string $title */
header('Cache-Control: no-store, private');
$loggedIn = Auth::check();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Admin') ?></title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/style.css')) ?>">
</head>
<body class="admin-body">
<header class="admin-top">
    <div class="container admin-top-inner">
        <a class="brand" href="<?= e(url('/admin/index.php')) ?>"><span><?= e($site['name']) ?> · Admin</span></a>
        <?php if ($loggedIn): ?>
        <nav class="admin-nav">
            <a href="<?= e(url('/admin/index.php')) ?>">Messages</a>
            <a href="<?= e(url('/admin/settings.php')) ?>">Version &amp; settings</a>
            <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener">View site</a>
            <form method="post" action="<?= e(url('/admin/logout.php')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button class="link-button" type="submit">Sign out (<?= e(Auth::user()) ?>)</button>
            </form>
        </nav>
        <?php endif; ?>
    </div>
</header>

<main class="container admin-main">
    <?php foreach ($flashes as $flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>

    <?= $content ?>
</main>
<script src="<?= e(url('/assets/js/main.js')) ?>" defer></script>
</body>
</html>
