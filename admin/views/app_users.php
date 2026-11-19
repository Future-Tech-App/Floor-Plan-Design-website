<?php /** @var array $users @var int $total @var int $page @var int $pages @var string $q @var array $stats @var bool $googleReady */
$avatar = static fn (array $u): string => $u['picture'] !== ''
    ? '<img class="ad-pic" src="' . e($u['picture']) . '" alt="" referrerpolicy="no-referrer" loading="lazy">'
    : '<span class="ad-pic">' . e(strtoupper(mb_substr($u['name'] ?: $u['email'], 0, 1))) . '</span>';
?>
<?php if (!$googleReady): ?>
    <div class="ad-note ad-mb"><?= icon('shield') ?><span>Google sign-in is not set up, so nobody can sign in or save in the web app. Add the Google OAuth client ID in <a href="<?= e(url('/admin/settings')) ?>">Site Settings</a>.</span></div>
<?php endif; ?>
<div class="ad-grid ad-grid-4 ad-mb">
    <div class="ad-card ad-stat"><span class="ad-stat-ic"><?= icon('user') ?></span><span><small>Web app users</small><strong><?= number_format($stats['total']) ?></strong><em class="ad-muted"><?= $stats['week'] ?> new this week</em></span></div>
    <div class="ad-card ad-stat"><span class="ad-stat-ic green"><?= icon('check') ?></span><span><small>Active</small><strong><?= number_format($stats['active']) ?></strong><em class="ad-muted">Signed in within 30 days</em></span></div>
</div>
<section class="ad-card">
    <div class="ad-toolbar">
        <div class="ad-muted" style="font-size:13px">People who signed in to the web app with Google. Their saved plans and notes are linked below.</div>
        <form class="ad-search" method="get" action="<?= e(url('/admin/app-users')) ?>" role="search">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or email…" aria-label="Search users">
            <button class="ad-btn" type="submit"><?= icon('search') ?></button>
        </form>
    </div>
    <?php if ($users): ?>
        <div class="ad-table-wrap"><table class="ad-table">
            <thead><tr><th>User</th><th class="num">Plans</th><th class="num">Notes</th><th class="num">Last save</th><th class="num">Last sign-in</th><th class="num">Joined</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><a class="ad-person" href="<?= e(url('/admin/app-users/' . $u['id'])) ?>"><?= $avatar($u) ?><span><strong><?= e($u['name'] ?: $u['email']) ?></strong><br><span class="ad-muted" style="font-size:12px"><?= e($u['email']) ?></span></span></a></td>
                    <td class="num"><?= (int) $u['plans'] ? '<a href="' . e(url('/admin/plans?user=' . $u['id'])) . '">' . (int) $u['plans'] . '</a>' : '0' ?></td>
                    <td class="num"><?= (int) $u['notes'] ? '<a href="' . e(url('/admin/notes?user=' . $u['id'])) . '">' . (int) $u['notes'] . '</a>' : '0' ?></td>
                    <td class="num ad-muted" style="white-space:nowrap"><?= $u['last_save'] ? e(fmt_date($u['last_save'])) : '—' ?></td>
                    <td class="num ad-muted" style="white-space:nowrap"><?= $u['last_login_at'] ? e(fmt_date($u['last_login_at'])) : '—' ?></td>
                    <td class="num ad-muted" style="white-space:nowrap"><?= e(fmt_date($u['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php else: ?>
        <p class="ad-empty"><?= $q !== '' ? 'No users match “' . e($q) . '”.' : 'No one has signed in to the web app yet.' ?></p>
    <?php endif; ?>
    <?= AdminApp::pager($page, $pages, array_filter(['q' => $q ?: null])) ?>
</section>
