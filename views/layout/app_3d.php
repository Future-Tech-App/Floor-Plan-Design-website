<?php /** @var string $content  @var string $title */ ?><!doctype html>
<html lang="en" data-base="<?= e(rtrim((string) config('base_path', ''), '/')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($title ?? '3D View — Floor Plan Designer') ?></title>
    <meta name="theme-color" content="#1976d2">
    <meta name="robots" content="noindex">
    <link rel="icon" href="<?= e(url('/favicon.ico')) ?>" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;600;700;800&family=Tinos&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('/assets/app/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/app/editor.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/assets/app/viewer3d.css')) ?>">
    <script type="importmap"><?= json_encode(['imports' => ['three' => url('/assets/app/vendor/three/three.module.min.js'), 'three/addons/' => url('/assets/app/vendor/three/'), url('/assets/app/furniture3d.js') => asset('/assets/app/furniture3d.js')]], JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body class="editor-body">
<?= $content ?>
<script src="<?= e(asset('/assets/app/app.js')) ?>" defer></script>
<script type="module" src="<?= e(asset('/assets/app/viewer3d.js')) ?>"></script>
</body>
</html>
