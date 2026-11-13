<?php /** @var array $site */ require ROOT_DIR . '/views/app/_head.php';
$highlights = [
    ['pencil', 'Draw & design', 'Walls, rooms, doors, windows and furniture.'],
    ['calculator', 'Calculators', 'Bricks, concrete, paint, steel, areas and unit conversions.'],
    ['download', 'PNG & PDF export', 'Print-ready plans with measurements to share with anyone.'],
    ['shield', 'Private', 'No account. Plans stay in your browser.'],
]; ?>
<div class="page-body">
    <div class="a-container" style="text-align:center">
        <img src="<?= e(url('/assets/img/logo.jpeg')) ?>" alt="" width="110" height="110" style="border-radius:50%;border:3px solid var(--blue);margin:0 auto 18px;box-shadow:0 10px 24px rgba(0,29,54,.12)">
        <h2 style="font-size:2.4rem;margin-bottom:6px"><?= e($site['name']) ?></h2>
        <span class="kicker">Version <?= e(preg_replace('/\+.*/', '', $site['version'])) ?> · Web</span>
        <span class="rule"></span>
        <p style="max-width:720px;margin:0 auto">Floor Plan Designer is a home-design app for drawing accurate floor plans, furnishing every room and exporting professional plans — in your browser, on Android and on iOS. This web version is built with PHP and JavaScript.</p>

        <h2 class="sec-title" style="margin-top:60px">Design Made Simple</h2>
        <span class="rule"></span>
        <div class="grid grid-4" style="text-align:center">
            <?php foreach ($highlights as [$ic, $t, $d]): ?>
                <div class="tool-card"><span class="ring"><?= icon($ic) ?></span><h3 style="font-size:1.35rem"><?= e($t) ?></h3><p><?= e($d) ?></p></div>
            <?php endforeach; ?>
        </div>

        <h2 class="sec-title" style="margin-top:60px">Your Privacy</h2>
        <span class="rule"></span>
        <p style="max-width:700px;margin:0 auto 24px">Your plans and notes are saved in this browser on this device — we never receive them. AI requests go through our server to the AI provider and are not stored.</p>
        <div class="form-actions" style="justify-content:center">
            <a class="btn" href="<?= e(url('/privacy-policy')) ?>" target="_blank" rel="noopener">Privacy Policy</a>
            <a class="btn btn-dark" href="<?= e(url('/terms-of-use')) ?>" target="_blank" rel="noopener">Terms of Service</a>
        </div>

        <h2 class="sec-title" style="margin-top:60px">Contact Us</h2>
        <span class="rule"></span>
        <p>Questions, ideas or found a bug? We read every message.</p>
        <p><a href="mailto:<?= e($site['support_email']) ?>" style="font:400 1.6rem var(--serif)"><?= e($site['support_email']) ?></a></p>
        <a class="btn btn-dark" href="<?= e(url('/')) ?>">Visit Website</a>
    </div>
</div>
