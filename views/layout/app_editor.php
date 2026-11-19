<?php /** @var string $content  @var array $site  @var string $title */ ?><!doctype html>
<html lang="en" data-base="<?= e(rtrim((string) config('base_path', ''), '/')) ?>"<?= app_auth_attrs() ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($title ?? 'Editor — Floor Plan Designer') ?></title>
    <meta name="theme-color" content="#1976d2">
    <meta name="robots" content="noindex">
    <link rel="icon" href="<?= e(url('/favicon.ico')) ?>" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Inter+Tight:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('/assets/app/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/app/editor.css')) ?>">
</head>
<body class="editor-body">
<?= $content ?>
<script src="<?= e(asset('/assets/app/app.js')) ?>" defer></script>
<script src="<?= e(asset('/assets/app/editor.js')) ?>" defer></script>
</body>
</html>
