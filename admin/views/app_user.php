<?php /** @var array $user @var array $plans @var array $notes */ ?>
<p class="ad-mb"><a href="<?= e(url('/admin/app-users')) ?>">← All web app users</a></p>
<div class="ad-grid ad-grid-main">
    <div>
        <section class="ad-card ad-mb">
            <div class="ad-card-head"><h2>Saved plans</h2><span class="ad-muted"><?= (int) $user['plans'] ?> plan<?= (int) $user['plans'] === 1 ? '' : 's' ?></span></div>
            <?php if ($plans): ?>
                <div class="ad-table-wrap"><table class="ad-table">
                    <thead><tr><th>Plan</th><th class="num">Rooms</th><th class="num">Area</th><th class="num">Last saved</th></tr></thead>
                    <tbody>
                    <?php foreach ($plans as $p): ?>
                        <tr>
                            <td><a href="<?= e(url('/admin/plans/' . $p['id'])) ?>"><strong><?= e($p['name']) ?></strong></a></td>
                            <td class="num"><?= (int) $p['rooms'] ?></td>
                            <td class="num"><?= $p['area_m2'] > 0 ? number_format((float) $p['area_m2'], 1) . ' m²' : '—' ?></td>
                            <td class="num ad-muted" style="white-space:nowrap"><?= e(fmt_date($p['updated_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php if ((int) $user['plans'] > count($plans)): ?><p class="ad-card-body"><a href="<?= e(url('/admin/plans?user=' . $user['id'])) ?>">See all <?= (int) $user['plans'] ?> plans</a></p><?php endif; ?>
            <?php else: ?>
                <p class="ad-empty">No plans saved yet.</p>
            <?php endif; ?>
        </section>
        <section class="ad-card">
            <div class="ad-card-head"><h2>Notes</h2><span class="ad-muted"><?= (int) $user['notes'] ?> note<?= (int) $user['notes'] === 1 ? '' : 's' ?></span></div>
            <?php if ($notes): ?>
                <div class="ad-table-wrap"><table class="ad-table">
                    <thead><tr><th>Note</th><th class="num">Last edited</th></tr></thead>
                    <tbody>
                    <?php foreach ($notes as $n): ?>
                        <tr>
                            <td><a href="<?= e(url('/admin/notes/' . $n['id'])) ?>"><strong><?= e($n['title'] !== '' ? $n['title'] : 'Untitled') ?></strong></a><br><span class="ad-muted" style="font-size:12px"><?= e(mb_substr((string) $n['excerpt'], 0, 120)) ?></span></td>
                            <td class="num ad-muted" style="white-space:nowrap"><?= e(fmt_date($n['note_time'] !== '' ? $n['note_time'] : $n['synced_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php else: ?>
                <p class="ad-empty">No notes saved yet.</p>
            <?php endif; ?>
        </section>
    </div>
    <section class="ad-card">
        <div class="ad-card-head"><h2>Account</h2></div>
        <div class="ad-card-body">
            <div class="ad-person ad-mb">
                <?php if ($user['picture'] !== ''): ?><img class="ad-pic ad-pic-lg" src="<?= e($user['picture']) ?>" alt="" referrerpolicy="no-referrer"><?php else: ?><span class="ad-pic ad-pic-lg"><?= e(strtoupper(mb_substr($user['name'] ?: $user['email'], 0, 1))) ?></span><?php endif; ?>
                <span><strong><?= e($user['name'] ?: 'No name') ?></strong><br><a href="mailto:<?= e($user['email']) ?>"><?= e($user['email']) ?></a></span>
            </div>
            <dl class="ad-msg-meta">
                <dt>Signs in with</dt><dd>Google</dd>
                <dt>Joined</dt><dd><?= e(fmt_date($user['created_at'])) ?></dd>
                <dt>Last sign-in</dt><dd><?= $user['last_login_at'] ? e(fmt_date($user['last_login_at'])) : '—' ?></dd>
                <dt>Signed in on</dt><dd><?= (int) $user['sessions'] ?> device<?= (int) $user['sessions'] === 1 ? '' : 's' ?></dd>
            </dl>
        </div>
        <div class="ad-form-foot" style="flex-wrap:wrap;gap:8px">
            <form method="post" action="<?= e(url('/admin/app-users/' . $user['id'])) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="signout">
                <button class="ad-btn" data-confirm="Sign <?= e($user['email']) ?> out on all their devices?">Sign out everywhere</button>
            </form>
            <form method="post" action="<?= e(url('/admin/app-users/' . $user['id'])) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete">
                <button class="ad-btn ad-btn-danger" data-confirm="Delete <?= e($user['email']) ?> and all <?= (int) $user['plans'] ?> plan(s) and <?= (int) $user['notes'] ?> note(s) saved in this account? This cannot be undone.">Delete user and data</button>
            </form>
        </div>
    </section>
</div>
