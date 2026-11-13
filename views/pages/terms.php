<?php /** @var array $site */ ?>
<?php
$bannerTitle  = 'Terms of Use';
$bannerKicker = 'Legal';
$bannerLead   = 'Last updated: November 13, 2026';
require ROOT_DIR . '/views/partials/page_banner.php';
?>

<section class="section">
    <div class="container narrow prose">
        <p>By downloading or using <?= e($site['name']) ?> (“the app”) or this website you agree to these terms.</p>

        <h2>1. Licence</h2>
        <p>We grant you a personal, non-exclusive, non-transferable licence to use the app on devices you own or control, subject to these terms and the rules of the store you downloaded it from.</p>

        <h2>2. Your content</h2>
        <p>Floor plans and notes you create belong to you. They are stored on your device, and you are responsible for keeping backups (for example by exporting PDFs).</p>

        <h2>3. Not professional advice</h2>
        <p>Plans, measurements, 3D previews, material estimates and AI-generated layouts are for planning and visualisation only. They are not architectural, engineering or construction advice. Always confirm dimensions, structural requirements and building codes with a qualified professional before building.</p>

        <h2>4. Purchases</h2>
        <p>Pro purchases (1-day pass, monthly, 6-month, yearly or lifetime) are processed by Google Play or the App Store and are subject to their terms. Time-based passes end at the end of their period. Refunds are handled by the store.</p>

        <h2>5. Acceptable use</h2>
        <p>Do not reverse engineer, resell or misuse the app, interfere with its services, or use the AI features to submit unlawful content.</p>

        <h2>6. Updates</h2>
        <p>We may release updates and may require a minimum version to keep using the app, for example to fix security or stability issues.</p>

        <h2>7. Disclaimer and liability</h2>
        <p>The app is provided “as is” without warranties of any kind. To the extent permitted by law, we are not liable for indirect or consequential damages, loss of data, or costs arising from use of the app or reliance on its output.</p>

        <h2>8. Changes</h2>
        <p>We may update these terms. Continued use after an update means you accept the revised terms.</p>

        <h2>9. Contact</h2>
        <p>Email <a href="mailto:<?= e($site['support_email']) ?>"><?= e($site['support_email']) ?></a>.</p>
    </div>
</section>
