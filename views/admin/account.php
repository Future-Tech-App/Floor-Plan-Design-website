<?php /** @var array $errors */
$err = static fn (string $k) => isset($errors[$k]) ? '<em class="ad-err">' . e($errors[$k]) . '</em>' : '';
?>
<section class="ad-card" style="max-width:560px">
    <div class="ad-card-head"><h2>Change password</h2><span class="ad-muted">Signed in as <?= e((string) Auth::user()) ?></span></div>
    <form method="post" action="<?= e(url('/admin/account')) ?>">
        <?= csrf_field() ?>
        <div class="ad-card-body ad-form">
            <label class="ad-field"><span>Current password</span><input type="password" name="current" autocomplete="current-password" required><?= $err('current') ?></label>
            <label class="ad-field"><span>New password (10+ characters)</span><input type="password" name="password" autocomplete="new-password" required><?= $err('password') ?></label>
            <label class="ad-field"><span>Confirm new password</span><input type="password" name="password_confirm" autocomplete="new-password" required><?= $err('password_confirm') ?></label>
        </div>
        <div class="ad-form-foot"><button class="ad-btn ad-btn-primary" type="submit">Change password</button></div>
    </form>
</section>
