<?php /** @var array $site */
$faqs = [
    'Getting started' => [
        ['Is Floor Plan Designer free?', 'Yes. All design tools are free to use. Non-Pro users see ads; buying Pro inside the app removes them.'],
        ['Do I need an account?', 'No. The app has no sign-up or login. Your plans are saved on your device.'],
        ['Does it work offline?', 'Yes. The editor, 3D preview, calculators and export all work without internet. Only the optional AI features and ads need a connection.'],
        ['Can I use it on a computer?', 'Yes. Open floorplandesigner.app/app in any modern browser for the same tools — free and without ads. Plans are saved in that browser, and exports are downloaded to your Downloads folder.'],
        ['Will my web plans appear on my phone?', 'Not automatically: web plans are stored in your browser and mobile plans on your device. Use Share on a saved plan to download its file.'],
        ['Can I start from a template?', 'Yes. Pick a one, two or three bedroom apartment template from the home screen and edit it like any other plan.'],
    ],
    'Editor' => [
        ['How do I change wall thickness?', 'Select a wall and adjust its thickness in the properties panel. New walls start at 15 cm.'],
        ['How do I add a door or window?', 'Choose a door or window from the library and drop it onto a wall — it snaps into place and cuts the opening for you.'],
        ['Which units are supported?', 'Feet, meters, centimetres, inches and millimetres. Switch between metric and imperial in Settings.'],
        ['Can I design more than one floor?', 'Yes. Add floors from the floors menu; each floor has its own layers.'],
    ],
    'Export & data' => [
        ['How do I export my plan?', 'Open the plan, tap Export and choose PDF or image. Exports are rendered at 300 DPI.'],
        ['Where are my plans stored?', 'On your device only. Uninstalling the app or clearing its data deletes them, so export anything you want to keep.'],
        ['How do I move plans to a new phone?', 'Export your plans as PDF or image and share them to the new device or cloud storage.'],
    ],
    'Purchases' => [
        ['What does Pro include?', 'Pro removes ads. It is available as a 1-day pass, monthly, 6-month, yearly or lifetime purchase.'],
        ['How do I restore a purchase?', 'Open Settings and tap Restore purchases while signed in to the same store account you bought with.'],
        ['How do I request a refund?', 'Refunds are handled by Google Play or Apple. Request one from your store\'s purchase history.'],
    ],
];
$total = array_sum(array_map('count', $faqs));
?>
<section class="page-hero">
    <div class="container">
        <h1>Support &amp; FAQ</h1>
        <p class="lead"><?= $total ?> answers to common questions.</p>
        <label class="search">
            <span class="visually-hidden">Search the FAQ</span>
            <input type="search" id="faq-search" placeholder="Search questions, e.g. export, wall, refund…" autocomplete="off">
        </label>
    </div>
</section>

<section class="section">
    <div class="container narrow" id="faq">
        <?php foreach ($faqs as $group => $items): ?>
            <div class="faq-group">
                <h2><?= e($group) ?></h2>
                <?php foreach ($items as [$q, $a]): ?>
                    <details class="faq-item">
                        <summary><?= e($q) ?></summary>
                        <p><?= e($a) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <p id="faq-empty" class="muted" hidden>No matching questions. <a href="<?= e(url('/contact?subject=Support')) ?>">Ask us directly</a>.</p>
    </div>
</section>

<section class="section section-alt">
    <div class="container center">
        <h2>Still need help?</h2>
        <p>Send us a message and include your phone model and app version (<?= e($site['version']) ?> is the latest).</p>
        <a class="btn" href="<?= e(url('/contact?subject=Support')) ?>">Contact support</a>
        <a class="btn btn-ghost" href="<?= e(url('/contact?subject=Bug%20report')) ?>">Report a bug</a>
    </div>
</section>
