<?php /** @var string $token  @var string $username  @var array $errors */
$err = static fn (string $f): string => isset($errors[$f]) ? '<p class="field-error">' . e($errors[$f]) . '</p>' : '';
?>
<div class="auth-box card">
    <h1>Create admin account</h1>
    <p class="muted small">This one-time page disappears once the first admin exists.</p>
    <form class="form" method="post" action="<?= e(url('/admin/setup.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" autocomplete="username" required autofocus value="<?= e($username) ?>">
            <?= $err('username') ?>
        </div>
        <div class="field">
            <label for="password">Password (min 10 characters)</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required>
            <?= $err('password') ?>
        </div>
        <div class="field">
            <label for="password_confirm">Confirm password</label>
            <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="10" required>
            <?= $err('password_confirm') ?>
        </div>
        <button class="btn" type="submit">Create admin</button>
    </form>
</div>
