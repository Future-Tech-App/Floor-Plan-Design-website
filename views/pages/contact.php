<?php /** @var array $site  @var array $errors  @var array $old */
$subjects = ['Support', 'Bug report', 'Feature request', 'Business enquiry', 'Other'];
$err = static fn (string $f): string => isset($errors[$f])
    ? '<p class="field-error" id="err-' . e($f) . '">' . e($errors[$f]) . '</p>' : '';
$aria = static fn (string $f): string => isset($errors[$f])
    ? ' aria-invalid="true" aria-describedby="err-' . e($f) . '"' : '';
?>
<section class="page-hero">
    <div class="container">
        <h1>Contact us</h1>
        <p class="lead">Questions, ideas or a bug? We read every message.</p>
    </div>
</section>

<section class="section">
    <div class="container split contact-split">
        <form class="form card" method="post" action="<?= e(url('/contact')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="hp" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="field">
                <label for="name">Your name</label>
                <input id="name" name="name" type="text" maxlength="100" required value="<?= e($old['name']) ?>"<?= $aria('name') ?>>
                <?= $err('name') ?>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" maxlength="150" required placeholder="you@example.com" value="<?= e($old['email']) ?>"<?= $aria('email') ?>>
                <?= $err('email') ?>
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
                <textarea id="message" name="message" rows="7" minlength="10" maxlength="5000" required placeholder="Describe your question or issue. For bugs, include your phone model and app version."<?= $aria('message') ?>><?= e($old['message']) ?></textarea>
                <?= $err('message') ?>
            </div>
            <button class="btn" type="submit">Send message</button>
        </form>

        <aside>
            <div class="card">
                <h3>Email</h3>
                <p><a href="mailto:<?= e($site['support_email']) ?>"><?= e($site['support_email']) ?></a></p>
                <p class="muted small">We usually reply within 1–2 business days.</p>
            </div>
            <div class="card">
                <h3>Quick answers</h3>
                <p>Many questions are already answered in our <a href="<?= e(url('/support')) ?>">Support Center</a>.</p>
            </div>
        </aside>
    </div>
</section>
