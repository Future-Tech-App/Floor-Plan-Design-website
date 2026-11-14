<?php /** @var array $site */ ?>
<?php
$bannerTitle  = 'Privacy Policy';
$bannerKicker = 'Legal';
$bannerLead   = 'Last updated: November 14, 2026';
require ROOT_DIR . '/views/partials/page_banner.php';
?>

<section class="section">
    <div class="container narrow prose">
        <p>This policy explains what information <?= e($site['name']) ?> (“the app”) and this website handle, why, and the choices you have.</p>

        <h2>1. Summary</h2>
        <ul>
            <li>The app has <strong>no accounts or login</strong>. We do not ask for your name, phone number, address or precise location.</li>
            <li>Your floor plans are saved on your device and <strong>backed up to our server</strong>, linked only to a random ID — not to your name or email. Settings and notes stay on your device.</li>
            <li>Some features use third-party services (ads, analytics, crash reporting, optional AI tools) that receive limited data as described below.</li>
            <li>All network traffic is encrypted with HTTPS/TLS.</li>
        </ul>

        <h2>2. Data stored on your device</h2>
        <p>Projects, floors, layers, furniture placements, notes, preferences and any API keys you enter are saved locally (API keys in encrypted secure storage). We cannot see this data. Uninstalling the app or clearing its data deletes it.</p>

        <h3>Plan backups</h3>
        <p>When you save a floor plan in the app or the web app, a copy of the plan (its name, walls, rooms, doors, windows and furniture) is sent to our server over HTTPS. The copy is linked to a random ID that your device creates; we store only a scrambled (hashed) version of that ID, and nothing that identifies you, such as your name, email or advertising ID. We keep backups so your plans are safe and to understand how the app is used, and our team can view them. When you delete a plan, its backup is deleted too.</p>

        <h2>3. Data handled by third parties</h2>
        <h3>Advertising (Google AdMob)</h3>
        <p>For users without Pro, Google AdMob shows ads. It may collect your device's advertising ID, device information, approximate (IP-based) location and ad interactions. In the EEA and UK you are asked for consent; on iOS you are asked for tracking permission. You can reset or limit your advertising ID in your device settings. Buying Pro removes ads.</p>

        <h3>Analytics and crash reports (Firebase)</h3>
        <p>Firebase Analytics records app usage events (for example, a plan was created, saved or exported) along with an app instance ID, device information and approximate location. Firebase Crashlytics collects crash logs and diagnostics so we can fix bugs. Firebase Remote Config downloads app settings and does not collect personal data.</p>

        <h3>Optional AI features</h3>
        <p>If you use the AI floor plan generator, the text description you type is sent to an AI provider to produce a layout. If you use the floor plan analyzer, the photo you choose or take is sent to the provider to detect rooms. Only use these features with content you are comfortable sharing. They are never used unless you start them.</p>

        <h3>Purchases, reviews and updates</h3>
        <p>Purchases are processed by Google Play or the Apple App Store. We receive confirmation of the purchase but never your payment details. In-app review and update prompts are provided by the store.</p>

        <h2>4. This website and the web app</h2>
        <p>When you send a message through our contact form we store your name, email, subject, message, IP address and browser user-agent so we can reply and prevent spam. We use a session cookie for security (CSRF protection) only — no advertising cookies are set by this site. The site loads its fonts from Google Fonts, so your browser sends your IP address to Google when you visit.</p>
        <p>The web app at <a href="<?= e(url('/app/')) ?>">/app</a> stores your plans and settings in your browser's local storage, and backs up your plans to our server as described in section 2. The web app shows no ads and has no in-app purchases.</p>
        <p>We count page views on this website and the web app to see which pages are used. Only a daily total per page is stored — no cookies, IP address or visitor ID.</p>
        <p>When you use the AI generator or analyzer in the web app, your text or photo is sent to our server, which forwards it to the AI provider. Our server does not store the text or photo; it records your IP address and the time of the request for up to 30 days to prevent abuse (rate limiting).</p>

        <h2>5. How long we keep data</h2>
        <p>Contact messages are kept as long as needed to resolve your request and then deleted. Plan backups are kept until you delete the plan or ask us to delete them. Analytics and crash data are retained according to Google's Firebase retention settings.</p>

        <h2>6. Your rights and data deletion</h2>
        <p>You can delete all on-device data by uninstalling the app, and delete a plan's backup by deleting the plan. To request deletion of all your plan backups, access to or deletion of contact messages, or deletion of analytics and crash data associated with your device, email <a href="mailto:<?= e($site['support_email']) ?>"><?= e($site['support_email']) ?></a>.</p>

        <h2>7. Children</h2>
        <p>The app is not directed to children under 13 and we do not knowingly collect personal information from them.</p>

        <h2>8. Changes</h2>
        <p>We may update this policy. The date at the top shows the latest revision.</p>

        <h2>9. Contact</h2>
        <p>Questions? Email <a href="mailto:<?= e($site['support_email']) ?>"><?= e($site['support_email']) ?></a> or use our <a href="<?= e(url('/contact')) ?>">contact form</a>.</p>
    </div>
</section>
