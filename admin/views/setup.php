<?php /** @var string $token  @var string $username  @var array $errors */ ?>
<h1>Create the first admin</h1>
<p class="ad-muted">This page works once. After the account is created it switches off.</p>
<form method="post" action="<?= e(url('/admin/setup')) ?>" class="ad-form">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label class="ad-field"><span>Username</span><input name="username" value="<?= e($username) ?>" required autocomplete="username">
        <?php if (isset($errors['username'])): ?><em class="ad-err"><?= e($errors['username']) ?></em><?php endif; ?></label>
    <label class="ad-field"><span>Password (10+ characters)</span><input type="password" name="password" required autocomplete="new-password">
        <?php if (isset($errors['password'])): ?><em class="ad-err"><?= e($errors['password']) ?></em><?php endif; ?></label>
    <label class="ad-field"><span>Confirm password</span><input type="password" name="password_confirm" required autocomplete="new-password">
        <?php if (isset($errors['password_confirm'])): ?><em class="ad-err"><?= e($errors['password_confirm']) ?></em><?php endif; ?></label>
    <button class="ad-btn ad-btn-primary ad-btn-block" type="submit">Create admin</button>
</form>
