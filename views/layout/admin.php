<?php
/** Admin panel layout. @var string $content  @var array $site  @var array $flashes  @var string $title  @var string $nav  @var string $pageTitle */
$nav = $nav ?? '';
$unread = Repository::unreadCount();
$menu = [
    'Overview' => [
        ['dashboard', '/admin', 'Dashboard', 'home'],
        ['messages', '/admin/messages', 'Messages', 'mail'],
        ['plans', '/admin/plans', 'Saved Plans', 'pencil'],
        ['notes', '/admin/notes', 'User Notes', 'note'],
        ['analytics', '/admin/analytics', 'Analytics', 'globe'],
        ['ai', '/admin/ai', 'AI Usage', 'sparkles'],
    ],
    'Content' => [
        ['templates', '/admin/templates', 'Templates', 'layers'],
        ['versions', '/admin/versions', 'App Versions', 'device'],
        ['settings', '/admin/settings', 'Site Settings', 'settings'],
    ],
    'Security' => [
        ['users', '/admin/users', 'Admins', 'shield'],
        ['activity', '/admin/activity', 'Activity Log', 'clock'],
        ['account', '/admin/account', 'My Account', 'edit'],
    ],
];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Admin') ?></title>
    <link rel="icon" href="<?= e(url('/favicon.ico')) ?>" sizes="any">
    <link rel="stylesheet" href="<?= e(asset('/assets/admin/admin.css')) ?>">
</head>
<body class="ad">
<aside class="ad-side" id="ad-side">
    <a class="ad-brand" href="<?= e(url('/admin')) ?>">
        <img src="<?= e(url('/assets/img/logo.jpeg')) ?>" alt="" width="36" height="36">
        <span><strong><?= e($site['name']) ?></strong><small>Admin panel</small></span>
    </a>
    <nav class="ad-menu" aria-label="Admin">
        <?php foreach ($menu as $group => $items): ?>
            <span class="ad-menu-label"><?= e($group) ?></span>
            <?php foreach ($items as [$key, $href, $label, $ic]): ?>
                <a href="<?= e(url($href)) ?>" class="<?= $nav === $key ? 'is-active' : '' ?>"<?= $nav === $key ? ' aria-current="page"' : '' ?>>
                    <?= icon($ic) ?><span><?= e($label) ?></span>
                    <?php if ($key === 'messages' && $unread > 0): ?><b class="ad-badge"><?= $unread > 99 ? '99+' : $unread ?></b><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="ad-side-foot">
        <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= icon('globe') ?>View website</a>
        <a href="<?= e(url('/app')) ?>" target="_blank" rel="noopener"><?= icon('monitor') ?>Open web app</a>
    </div>
</aside>
<div class="ad-scrim" data-close-side hidden></div>

<div class="ad-main">
    <header class="ad-top">
        <button type="button" class="ad-burger" data-open-side aria-label="Open menu" aria-controls="ad-side"><?= icon('menu') ?></button>
        <h1><?= e($pageTitle ?? 'Admin') ?></h1>
        <div class="ad-user">
            <span class="ad-avatar" aria-hidden="true"><?= e(strtoupper(substr((string) Auth::user(), 0, 1))) ?></span>
            <span class="ad-user-name"><?= e((string) Auth::user()) ?></span>
            <form method="post" action="<?= e(url('/admin/logout')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="ad-btn ad-btn-ghost ad-btn-sm">Sign out</button>
            </form>
        </div>
    </header>
    <main class="ad-content" id="main">
        <?php foreach ($flashes as $flash): ?>
            <div class="ad-flash ad-flash-<?= e($flash['type']) ?>" role="status"><?= icon($flash['type'] === 'success' ? 'check' : 'help') ?><span><?= e($flash['message']) ?></span></div>
        <?php endforeach; ?>
        <?= $content ?>
    </main>
    <footer class="ad-foot">© <?= e($site['year']) ?> <?= e($site['name']) ?> · Admin · v<?= e(preg_replace('/\+.*/', '', $site['version'])) ?></footer>
</div>
<script src="<?= e(asset('/assets/admin/admin.js')) ?>" defer></script>
</body>
</html>
