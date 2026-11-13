<?php /** @var array $site */ ?>
<section class="page-hero">
    <div class="container">
        <h1>Privacy Policy</h1>
        <p class="lead">Last updated: November 13, 2026</p>
    </div>
</section>

<section class="section">
    <div class="container narrow prose">
        <p>This policy explains what information <?= e($site['name']) ?> (“the app”) and this website handle, why, and the choices you have.</p>

        <h2>1. Summary</h2>
        <ul>
            <li>The app has <strong>no accounts or login</strong>. We do not ask for your name, phone number, address or precise location.</li>
            <li>Your floor plans, settings and notes are stored <strong>only on your device</strong>.</li>
            <li>Some features use third-party services (ads, analytics, crash reporting, optional AI tools) that receive limited data as described below.</li>
            <li>All network traffic is encrypted with HTTPS/TLS.</li>
        </ul>

        <h2>2. Data stored on your device</h2>
        <p>Projects, floors, layers, furniture placements, notes, preferences and any API keys you enter are saved locally (API keys in encrypted secure storage). We cannot see this data. Uninstalling the app or clearing its data deletes it.</p>

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
        <p>When you send a message through our contact form we store your name, email, subject, message, IP address and browser user-agent so we can reply and prevent spam. We use a session cookie for security (CSRF protection) only — no advertising cookies are set by this site.</p>
        <p>The web app at <a href="<?= e(url('/app/')) ?>">/app</a> stores your plans and settings in your browser's local storage on your device; we do not receive them. Clearing your browser data deletes them. The web app shows no ads and has no in-app purchases. It uses Firebase Analytics for usage statistics, as the mobile app does.</p>
        <p>When you use the AI generator or analyzer in the web app, your text or photo is sent to our server, which forwards it to the AI provider. Our server does not store the text or photo; it records your IP address and the time of the request for up to two days to prevent abuse (rate limiting).</p>

        <h2>5. How long we keep data</h2>
        <p>Contact messages are kept as long as needed to resolve your request and then deleted. Analytics and crash data are retained according to Google's Firebase retention settings.</p>

        <h2>6. Your rights and data deletion</h2>
        <p>You can delete all on-device data by uninstalling the app. To request access to or deletion of contact messages, or deletion of analytics and crash data associated with your device, email <a href="mailto:<?= e($site['support_email']) ?>"><?= e($site['support_email']) ?></a>.</p>

        <h2>7. Children</h2>
        <p>The app is not directed to children under 13 and we do not knowingly collect personal information from them.</p>

        <h2>8. Changes</h2>
        <p>We may update this policy. The date at the top shows the latest revision.</p>

        <h2>9. Contact</h2>
        <p>Questions? Email <a href="mailto:<?= e($site['support_email']) ?>"><?= e($site['support_email']) ?></a> or use our <a href="<?= e(url('/contact')) ?>">contact form</a>.</p>
    </div>
</section>
