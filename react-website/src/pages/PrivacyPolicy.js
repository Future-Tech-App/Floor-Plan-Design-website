import React from 'react';

export default function PrivacyPolicy() {
  return (
    <>
      <section className="page-hero">
        <div className="container">
          <h1>Privacy Policy</h1>
          <p>Last updated: March 26, 2026</p>
        </div>
      </section>

      <section className="legal-content">
        <div className="container">
          <p>
            Floor Plan Designer ("we", "our", or "us") is committed to protecting your privacy.
            This Privacy Policy explains how we collect, use, disclose, and safeguard your information
            when you use our mobile application ("App"). Please read this policy carefully.
          </p>

          <h2>1. Information We Collect</h2>

          <h3>1.1 Information You Provide</h3>
          <p>We may collect information that you voluntarily provide when using the App:</p>
          <ul>
            <li>Floor plan projects and designs you create within the App</li>
            <li>Settings and preferences you configure</li>
            <li>Feedback, support requests, or correspondence you send to us</li>
          </ul>

          <h3>1.2 Automatically Collected Information</h3>
          <p>When you use the App, certain information may be collected automatically:</p>
          <ul>
            <li>Device information (model, operating system, unique device identifiers)</li>
            <li>Usage data (features used, time spent, crash reports)</li>
            <li>Language and locale preferences</li>
          </ul>

          <h3>1.3 Third-Party Services</h3>
          <p>Our App may use third-party services that collect information, including:</p>
          <ul>
            <li><strong>Google AdMob</strong> — for displaying advertisements. AdMob may collect device identifiers and usage data. See <a href="https://policies.google.com/privacy" target="_blank" rel="noreferrer">Google's Privacy Policy</a>.</li>
            <li><strong>Firebase Analytics</strong> — for understanding app usage and improving our services.</li>
          </ul>

          <h2>2. How We Use Your Information</h2>
          <p>We use the collected information to:</p>
          <ul>
            <li>Provide and maintain the App's functionality</li>
            <li>Save and restore your floor plan projects locally on your device</li>
            <li>Improve and optimize the App's performance and user experience</li>
            <li>Display relevant advertisements through AdMob</li>
            <li>Diagnose technical issues and prevent fraud</li>
            <li>Respond to your support requests and communications</li>
          </ul>

          <h2>3. Data Storage and Security</h2>
          <p>
            Your floor plan projects are stored locally on your device. We do not upload or store
            your designs on external servers unless you explicitly choose to export or share them.
          </p>
          <p>
            API keys and sensitive configuration data are stored securely using encrypted storage
            mechanisms provided by the device operating system.
          </p>
          <p>
            We implement reasonable security measures to protect your information, but no method of
            electronic storage is 100% secure. We cannot guarantee absolute security.
          </p>

          <h2>4. Data Sharing</h2>
          <p>We do not sell, trade, or rent your personal information. We may share information only in the following cases:</p>
          <ul>
            <li><strong>Service Providers</strong> — Third-party services (AdMob, Firebase) that help us operate the App, bound by their own privacy policies.</li>
            <li><strong>Legal Requirements</strong> — If required by law, court order, or governmental regulation.</li>
            <li><strong>Safety</strong> — To protect the rights, property, or safety of our users or the public.</li>
          </ul>

          <h2>5. Advertising</h2>
          <p>
            The App displays advertisements through Google AdMob. AdMob may use cookies and
            device identifiers to serve personalized or non-personalized ads based on your
            preferences and settings. You can opt out of personalized advertising through your
            device's advertising settings.
          </p>

          <h2>6. Children's Privacy</h2>
          <p>
            The App is not directed to children under the age of 13. We do not knowingly collect
            personal information from children under 13. If we learn that we have collected
            information from a child under 13, we will take steps to delete it promptly.
          </p>

          <h2>7. Your Rights</h2>
          <p>Depending on your jurisdiction, you may have the right to:</p>
          <ul>
            <li>Access the personal information we hold about you</li>
            <li>Request correction of inaccurate information</li>
            <li>Request deletion of your information</li>
            <li>Opt out of personalized advertising</li>
            <li>Withdraw consent where applicable</li>
          </ul>

          <h2>8. Changes to This Policy</h2>
          <p>
            We may update this Privacy Policy from time to time. Any changes will be posted within
            the App and on this page, with the "Last updated" date revised accordingly. Your
            continued use of the App after any changes constitutes acceptance of the new policy.
          </p>

          <h2>9. Contact Us</h2>
          <p>
            If you have questions or concerns about this Privacy Policy, please contact us at:
          </p>
          <ul>
            <li>Email: <a href="mailto:support@floorplandesigner.app">support@floorplandesigner.app</a></li>
            <li>Website: <a href="/contact">Contact Us</a></li>
          </ul>
        </div>
      </section>
    </>
  );
}
