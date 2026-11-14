<?php /** @var string $username */ ?>
<h1>Sign in</h1>
<p class="ad-muted">Sign in to manage the website and web app.</p>
<form method="post" action="<?= e(url('/admin/login')) ?>" class="ad-form">
    <?= csrf_field() ?>
    <label class="ad-field"><span>Username</span><input name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus></label>
    <label class="ad-field"><span>Password</span><input type="password" name="password" autocomplete="current-password" required></label>
    <button class="ad-btn ad-btn-primary ad-btn-block" type="submit">Sign in</button>
</form>
