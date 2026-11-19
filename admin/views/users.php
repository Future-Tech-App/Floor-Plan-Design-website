<?php /** @var array $admins @var array $errors @var string $username @var int $me */
$err = static fn (string $k) => isset($errors[$k]) ? '<em class="ad-err">' . e($errors[$k]) . '</em>' : '';
?>
<div class="ad-grid ad-grid-main">
    <section class="ad-card">
        <div class="ad-card-head"><h2>Admins</h2><span class="ad-muted"><?= count($admins) ?> account<?= count($admins) === 1 ? '' : 's' ?></span></div>
        <div class="ad-table-wrap"><table class="ad-table">
            <thead><tr><th>Username</th><th>Created</th><th>Last sign-in</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($admins as $a): ?>
                <tr>
                    <td><strong><?= e($a['username']) ?></strong> <?= (int) $a['id'] === $me ? '<span class="ad-pill blue">You</span>' : '' ?></td>
                    <td class="ad-muted"><?= e(fmt_date($a['created_at'])) ?></td>
                    <td class="ad-muted"><?= $a['last_login_at'] ? e(fmt_date($a['last_login_at'])) : 'Never' ?></td>
                    <td class="num">
                        <?php if ((int) $a['id'] !== $me): ?>
                            <form method="post" action="<?= e(url('/admin/users')) ?>">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <button class="ad-btn ad-btn-sm ad-btn-danger" data-confirm="Remove admin “<?= e($a['username']) ?>”?">Remove</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
    <section class="ad-card">
        <div class="ad-card-head"><h2>Add an admin</h2></div>
        <form method="post" action="<?= e(url('/admin/users')) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="add">
            <div class="ad-card-body ad-form">
                <label class="ad-field"><span>Username</span><input name="username" value="<?= e($username) ?>" autocomplete="off" required><?= $err('username') ?></label>
                <label class="ad-field"><span>Password (10+ characters)</span><input type="password" name="password" autocomplete="new-password" required><?= $err('password') ?></label>
                <label class="ad-field"><span>Confirm password</span><input type="password" name="password_confirm" autocomplete="new-password" required><?= $err('password_confirm') ?></label>
            </div>
            <div class="ad-form-foot"><button class="ad-btn ad-btn-primary" type="submit"><?= icon('plus') ?> Add admin</button></div>
        </form>
    </section>
</div>
