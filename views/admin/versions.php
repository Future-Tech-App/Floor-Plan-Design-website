<?php /** @var array $values @var array $errors */
$err = static fn (string $k) => isset($errors[$k]) ? '<em class="ad-err">' . e($errors[$k]) . '</em>' : '';
?>
<div class="ad-note ad-mb"><?= icon('device') ?><span>These rules are served to the Android and iOS apps by <code>/api/v1/version.php</code>. Changes apply immediately.</span></div>
<form method="post" action="<?= e(url('/admin/versions')) ?>" class="ad-card">
    <?= csrf_field() ?>
    <div class="ad-card-head"><h2>App version &amp; updates</h2></div>
    <div class="ad-card-body ad-form">
        <div class="ad-form-grid">
            <label class="ad-field"><span>Latest version</span><input name="latest_version" value="<?= e($values['latest_version']) ?>" placeholder="3.0.2"><small>Shown on the website and offered as the newest build.</small><?= $err('latest_version') ?></label>
            <label class="ad-field"><span>Optional update below</span><input name="optional_update_version" value="<?= e($values['optional_update_version']) ?>" placeholder="0.0.0"><small>Older apps see a dismissible “update available” prompt.</small><?= $err('optional_update_version') ?></label>
            <label class="ad-field"><span>Force update below</span><input name="force_update_min_version" value="<?= e($values['force_update_min_version']) ?>" placeholder="0.0.0"><small>Older apps must update before they can be used.</small><?= $err('force_update_min_version') ?></label>
            <label class="ad-field"><span>Blocked versions</span><input name="force_update_blocked_versions" value="<?= e($values['force_update_blocked_versions']) ?>" placeholder="3.0.0, 3.0.1+31"><small>Comma-separated builds that must always update.</small><?= $err('force_update_blocked_versions') ?></label>
        </div>
        <label class="ad-check"><input type="checkbox" name="force_update_enabled" value="1" <?= $values['force_update_enabled'] === '1' ? 'checked' : '' ?>><span>Force updates enabled<small>Turn off to stop all forced updates without changing the numbers.</small></span></label>
    </div>
    <div class="ad-form-foot"><button class="ad-btn ad-btn-primary" type="submit">Save rules</button>
        <a class="ad-btn" href="<?= e(url('/api/v1/version.php?platform=android&version=' . rawurlencode($values['latest_version']))) ?>" target="_blank" rel="noopener">Test the API</a></div>
</form>
