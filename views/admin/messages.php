<?php /** @var array $messages  @var string $filter  @var int $page  @var int $pages  @var int $total  @var int $unread */ ?>
<div class="admin-head">
    <h1>Contact messages</h1>
    <p class="muted"><?= (int) $total ?> shown · <?= (int) $unread ?> unread</p>
</div>

<nav class="tabs" aria-label="Filter">
    <?php foreach (['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $key => $label): ?>
        <a href="<?= e(url('/admin/index.php?filter=' . $key)) ?>" class="<?= $filter === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$messages): ?>
    <div class="card"><p class="muted">No messages here yet.</p></div>
<?php else: ?>
<div class="table-wrap">
    <table class="admin-table">
        <thead>
            <tr><th>From</th><th>Subject</th><th>Received</th><th class="right">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($messages as $m): ?>
            <tr class="<?= (int) $m['is_read'] ? '' : 'unread' ?>">
                <td>
                    <a href="<?= e(url('/admin/message.php?id=' . (int) $m['id'])) ?>"><?= e($m['name']) ?></a>
                    <div class="muted small"><?= e($m['email']) ?></div>
                </td>
                <td>
                    <?= e($m['subject']) ?>
                    <div class="muted small clamp"><?= e(mb_strimwidth($m['message'], 0, 90, '…')) ?></div>
                </td>
                <td class="nowrap"><?= e(fmt_date($m['created_at'])) ?></td>
                <td class="right nowrap">
                    <form method="post" action="<?= e(url('/admin/index.php?filter=' . $filter)) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <?php if ((int) $m['is_read']): ?>
                            <button class="btn btn-small btn-ghost" name="action" value="unread">Mark unread</button>
                        <?php else: ?>
                            <button class="btn btn-small btn-ghost" name="action" value="read">Mark read</button>
                        <?php endif; ?>
                        <button class="btn btn-small btn-danger" name="action" value="delete" data-confirm="Delete this message permanently?">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
<nav class="pagination" aria-label="Pages">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="<?= e(url('/admin/index.php?filter=' . $filter . '&page=' . $i)) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
