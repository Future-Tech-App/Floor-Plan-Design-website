<?php /** @var array $values  @var array $errors */
$err = static fn (string $f): string => isset($errors[$f]) ? '<p class="field-error">' . e($errors[$f]) . '</p>' : '';
$apiUrl = url('/api/v1/version.php?platform=android&version=' . rawurlencode($values['latest_version'] ?: '1.0.0'));
?>
<div class="admin-head">
    <h1>Version &amp; settings</h1>
    <p class="muted">Controls the public version shown on the site and the decision returned by the version API.</p>
</div>

<form class="form card" method="post" action="<?= e(url('/admin/settings.php')) ?>">
    <?= csrf_field() ?>

    <div class="field">
        <label for="latest_version">Latest version (shown on the website)</label>
        <input id="latest_version" name="latest_version" type="text" placeholder="3.0.2" value="<?= e($values['latest_version']) ?>">
        <?= $err('latest_version') ?>
    </div>

    <fieldset>
        <legend>Update gate</legend>
        <label class="check">
            <input type="checkbox" name="force_update_enabled" value="1"<?= $values['force_update_enabled'] === '1' ? ' checked' : '' ?>>
            Enable version gate
        </label>

        <div class="field">
            <label for="force_update_min_version">Minimum version (older builds must update)</label>
            <input id="force_update_min_version" name="force_update_min_version" type="text" placeholder="0.0.0" value="<?= e($values['force_update_min_version']) ?>">
            <?= $err('force_update_min_version') ?>
        </div>

        <div class="field">
            <label for="optional_update_version">Optional update version (older builds get a dismissible prompt)</label>
            <input id="optional_update_version" name="optional_update_version" type="text" placeholder="0.0.0" value="<?= e($values['optional_update_version']) ?>">
            <?= $err('optional_update_version') ?>
        </div>

        <div class="field">
            <label for="force_update_blocked_versions">Blocked versions (comma-separated, exact match)</label>
            <input id="force_update_blocked_versions" name="force_update_blocked_versions" type="text" placeholder="3.0.0, 3.0.1+31" value="<?= e($values['force_update_blocked_versions']) ?>">
            <?= $err('force_update_blocked_versions') ?>
        </div>
    </fieldset>

    <button class="btn" type="submit">Save settings</button>
</form>

<div class="card">
    <h2>Test the API</h2>
    <p>The app (or any client) can call:</p>
    <pre><code>GET <?= e($apiUrl) ?></code></pre>
    <p><a href="<?= e($apiUrl) ?>" target="_blank" rel="noopener">Open in a new tab →</a></p>
    <p class="muted small">Store links, support email and other site details are set in <code>config/local.php</code>.</p>
</div>
