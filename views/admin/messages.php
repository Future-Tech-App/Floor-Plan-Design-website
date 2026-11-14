<?php /** @var string $filter @var string $search @var int $page @var int $pages @var int $total @var array $messages @var array $counts */
$q = static fn (array $extra) => url('/admin/messages') . '?' . http_build_query(array_filter($extra + ['filter' => $filter !== 'all' ? $filter : null, 'q' => $search ?: null]));
?>
<section class="ad-card">
    <div class="ad-toolbar">
        <nav class="ad-tabs" aria-label="Filter">
            <a href="<?= e($q(['filter' => null])) ?>" class="<?= $filter === 'all' ? 'is-active' : '' ?>">All (<?= $counts['total'] ?>)</a>
            <a href="<?= e($q(['filter' => 'unread'])) ?>" class="<?= $filter === 'unread' ? 'is-active' : '' ?>">Unread (<?= $counts['unread'] ?>)</a>
            <a href="<?= e($q(['filter' => 'read'])) ?>" class="<?= $filter === 'read' ? 'is-active' : '' ?>">Read</a>
        </nav>
        <div class="ad-actions">
            <form class="ad-search" method="get" action="<?= e(url('/admin/messages')) ?>" role="search">
                <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email, text…" aria-label="Search messages">
                <button class="ad-btn" type="submit"><?= icon('search') ?></button>
            </form>
            <a class="ad-btn" href="<?= e(url('/admin/messages/export')) ?>"><?= icon('download') ?> Export CSV</a>
        </div>
    </div>
    <form method="post" action="<?= e($q([])) ?>">
        <?= csrf_field() ?>
        <div class="ad-bulk">
            <span data-selected></span>
            <button class="ad-btn ad-btn-sm" name="action" value="read" data-bulk disabled>Mark read</button>
            <button class="ad-btn ad-btn-sm" name="action" value="unread" data-bulk disabled>Mark unread</button>
            <button class="ad-btn ad-btn-sm ad-btn-danger" name="action" value="delete" data-bulk disabled data-confirm="Delete the selected messages? This cannot be undone.">Delete</button>
            <span class="ad-muted" style="margin-left:auto;font-size:12.5px"><?= number_format($total) ?> message<?= $total === 1 ? '' : 's' ?><?= $search !== '' ? ' matching “' . e($search) . '”' : '' ?></span>
        </div>
        <?php if ($messages): ?>
            <div class="ad-table-wrap"><table class="ad-table">
                <thead><tr><th class="w-check"><input type="checkbox" data-check-all aria-label="Select all"></th><th>From</th><th>Subject</th><th>Message</th><th class="num">Received</th></tr></thead>
                <tbody>
                <?php foreach ($messages as $m): ?>
                    <tr class="<?= $m['is_read'] ? '' : 'is-unread' ?>">
                        <td class="w-check"><input type="checkbox" name="ids[]" value="<?= (int) $m['id'] ?>" aria-label="Select message from <?= e($m['name']) ?>"></td>
                        <td><a href="<?= e(url('/admin/messages/' . $m['id'])) ?>"><?= e($m['name']) ?></a><br><span class="ad-muted"><?= e($m['email']) ?></span></td>
                        <td><span class="ad-pill <?= $m['is_read'] ? '' : 'blue' ?>"><?= e($m['subject']) ?></span></td>
                        <td><a href="<?= e(url('/admin/messages/' . $m['id'])) ?>" class="ad-trunc" style="color:inherit"><?= e(mb_strimwidth($m['message'], 0, 140, '…')) ?></a></td>
                        <td class="num ad-muted" style="white-space:nowrap"><?= e(fmt_date($m['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php else: ?>
            <p class="ad-empty"><?= $search !== '' ? 'No messages match your search.' : 'No messages here yet.' ?></p>
        <?php endif; ?>
    </form>
    <?= AdminApp::pager($page, $pages, array_filter(['filter' => $filter !== 'all' ? $filter : null, 'q' => $search ?: null])) ?>
</section>
