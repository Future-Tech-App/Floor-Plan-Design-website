<?php /** @var array $site  @var array $errors  @var array $old */
$subjects = ['Support', 'Bug report', 'Feature request', 'Business enquiry', 'Other'];
$err = static fn (string $f): string => isset($errors[$f])
    ? '<p class="field-error" id="err-' . e($f) . '">' . e($errors[$f]) . '</p>' : '';
$aria = static fn (string $f): string => isset($errors[$f])
    ? ' aria-invalid="true" aria-describedby="err-' . e($f) . '"' : '';

$bannerTitle  = 'Contact us';
$bannerKicker = 'Get in touch';
$bannerLead   = 'Questions, ideas or a bug? We read every message.';
require ROOT_DIR . '/views/partials/page_banner.php';
?>
<section class="section">
    <div class="container split contact-split">
        <form class="form card reveal" method="post" action="<?= e(url('/contact')) ?>" novalidate>
            <p class="kicker">Send a message</p>
            <h2 style="font-size:1.8rem">How can we help?</h2>
            <?= csrf_field() ?>
            <div class="hp" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="name">Your name</label>
                    <input id="name" name="name" type="text" maxlength="100" required autocomplete="name" value="<?= e($old['name']) ?>"<?= $aria('name') ?>>
                    <?= $err('name') ?>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" maxlength="150" required autocomplete="email" placeholder="you@example.com" value="<?= e($old['email']) ?>"<?= $aria('email') ?>>
                    <?= $err('email') ?>
                </div>
            </div>
            <div class="field">
                <label for="subject">Subject</label>
                <select id="subject" name="subject" required<?= $aria('subject') ?>>
                    <option value="">Choose a topic…</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= e($s) ?>"<?= $old['subject'] === $s ? ' selected' : '' ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $err('subject') ?>
            </div>
            <div class="field">
                <label for="message">Message</label>
                <textarea id="message" name="message" rows="7" minlength="10" maxlength="5000" required placeholder="Describe your question or issue. For bugs, include your device and app version."<?= $aria('message') ?>><?= e($old['message']) ?></textarea>
                <?= $err('message') ?>
            </div>
            <button class="btn btn-lg" type="submit">Send message <?= icon('arrow') ?></button>
        </form>

        <aside class="contact-info reveal">
            <div class="card">
                <span class="card-icon"><?= icon('mail') ?></span>
                <div>
                    <h3>Email us</h3>
                    <p><a href="mailto:<?= e($site['support_email']) ?>"><?= e($site['support_email']) ?></a></p>
                </div>
            </div>
            <div class="card">
                <span class="card-icon"><?= icon('clock') ?></span>
                <div>
                    <h3>Response time</h3>
                    <p>We usually reply within 1–2 business days.</p>
                </div>
            </div>
            <div class="card">
                <span class="card-icon"><?= icon('help') ?></span>
                <div>
                    <h3>Quick answers</h3>
                    <p>Many questions are already answered in our <a href="<?= e(url('/support')) ?>">Support Center</a>.</p>
                </div>
            </div>
            <div class="card">
                <span class="card-icon"><?= icon('monitor') ?></span>
                <div>
                    <h3>Try it now</h3>
                    <p>The <a href="<?= e(url('/app/')) ?>">web app</a> is free and needs no sign-up.</p>
                </div>
            </div>
        </aside>
    </div>
</section>
