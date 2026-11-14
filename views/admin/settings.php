<?php /** @var array $values @var array $errors @var array $defaults */
$err = static fn (string $k) => isset($errors[$k]) ? '<em class="ad-err">' . e($errors[$k]) . '</em>' : '';
?>
<form method="post" action="<?= e(url('/admin/settings')) ?>">
    <?= csrf_field() ?>
    <section class="ad-card ad-mb">
        <div class="ad-card-head"><h2>Announcement banner</h2><span class="ad-muted">A slim bar at the top of the website and web app</span></div>
        <div class="ad-card-body ad-form">
            <label class="ad-check"><input type="checkbox" name="announce_enabled" value="1" <?= $values['announce_enabled'] === '1' ? 'checked' : '' ?>><span>Show the banner<small>Visitors can close it; it reappears when you change the text.</small></span></label>
            <div class="ad-form-grid">
                <label class="ad-field"><span>Text</span><input name="announce_text" maxlength="200" value="<?= e($values['announce_text']) ?>" placeholder="New: walk through your plan in 3D!"><?= $err('announce_text') ?></label>
                <label class="ad-field"><span>Link (optional)</span><input name="announce_link" value="<?= e($values['announce_link']) ?>" placeholder="/app/ai or https://…"><?= $err('announce_link') ?></label>
            </div>
        </div>
    </section>
    <section class="ad-card">
        <div class="ad-card-head"><h2>Contact &amp; store links</h2><span class="ad-muted">Leave a field empty to use the default</span></div>
        <div class="ad-card-body ad-form-grid">
            <label class="ad-field"><span>Support email</span><input type="email" name="site_support_email" value="<?= e($values['site_support_email']) ?>" placeholder="<?= e((string) $defaults['site_support_email']) ?>"><small>Shown in footers, About and Contact.</small><?= $err('site_support_email') ?></label>
            <label class="ad-field"><span>Tagline</span><input name="site_tagline" maxlength="300" value="<?= e($values['site_tagline']) ?>" placeholder="<?= e((string) $defaults['site_tagline']) ?>"><?= $err('site_tagline') ?></label>
            <label class="ad-field"><span>Google Play link</span><input type="url" name="site_android_url" value="<?= e($values['site_android_url']) ?>" placeholder="<?= e((string) $defaults['site_android_url']) ?>"><?= $err('site_android_url') ?></label>
            <label class="ad-field"><span>App Store link</span><input type="url" name="site_ios_url" value="<?= e($values['site_ios_url']) ?>" placeholder="<?= e((string) $defaults['site_ios_url'] ?: 'https://apps.apple.com/…') ?>"><small>Fills the “Coming soon” App Store buttons.</small><?= $err('site_ios_url') ?></label>
        </div>
        <div class="ad-form-foot"><button class="ad-btn ad-btn-primary" type="submit">Save settings</button></div>
    </section>
</form>
