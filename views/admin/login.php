<div class="auth-box card">
    <h1>Admin sign in</h1>
    <form class="form" method="post" action="<?= e(url('/admin/login.php')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="btn" type="submit">Sign in</button>
    </form>
    <p class="muted small">No account yet? Run <code>php bin/create-admin.php &lt;username&gt;</code> on the server.</p>
</div>
