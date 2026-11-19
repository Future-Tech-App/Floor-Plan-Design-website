<?php
/**
 * Phone mock-up of the app's home screen, drawn in HTML/CSS (no screenshot), so it stays
 * sharp at any size. Everything inside is sized in container units (cqw) of .phone-mockup.
 *
 * @var string $phoneClass extra classes for the outer element (e.g. "hero-phone")
 */
$phoneClass = $phoneClass ?? '';
$tools = [   // label, icon, accent colour, soft colour
    ['Draw', 'pencil', '#1e88e5', '#d6e9fc'],
    ['AI Generator', 'sparkles', '#9c3fb8', '#ecdaf4'],
    ['Construction Calculator', 'calculator', '#ef8f00', '#ffe6c2'],
    ['Bubble Level', 'level', '#2e9d4b', '#d6eed9'],
    ['Measure', 'ruler', '#e5483d', '#fbd8d5'],
    ['Area Calculator', 'edit', '#0f9fbf', '#cdeff6'],
    ['Saved Plans', 'copy', '#d99a00', '#fcecb8'],
    ['Templates', 'layers', '#4053b5', '#dadef4'],
    ['Export', 'download', '#128a74', '#d0ebe5'],
];
?>
<div class="phone-mockup <?= e($phoneClass) ?>" aria-hidden="true">
    <div class="pm-screen">
        <span class="pm-camera"></span>
        <div class="pm-bar">
            <?= icon('menu', 'pm-ic') ?>
            <span class="pm-title">Floor Plan Designer</span>
            <svg class="pm-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/></svg>
            <?= icon('settings', 'pm-ic') ?>
        </div>
        <div class="pm-body">
            <div class="pm-welcome">
                <img src="<?= e(url('/apple-touch-icon.png')) ?>" alt="" width="40" height="40">
                <span><strong>Welcome</strong>Design your dream space</span>
            </div>
            <div class="pm-grid">
                <?php foreach ($tools as $i => [$label, $ic, $accent, $soft]): ?>
                    <span class="pm-tile" style="--accent: <?= e($accent) ?>; --soft: <?= e($soft) ?>; --i: <?= $i ?>">
                        <span class="pm-tile-ic"><?= icon($ic) ?></span>
                        <span class="pm-tile-label"><?= e($label) ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
