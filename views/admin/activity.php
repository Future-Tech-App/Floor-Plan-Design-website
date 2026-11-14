<?php /** @var array $items @var int $page @var int $pages @var int $total */
$labels = [
    'sign-in' => ['Signed in', 'green'], 'sign-out' => ['Signed out', ''], 'password-change' => ['Changed password', 'amber'],
    'admin-add' => ['Added admin', 'amber'], 'admin-delete' => ['Removed admin', 'red'],
    'messages-read' => ['Marked messages read', ''], 'messages-unread' => ['Marked messages unread', ''], 'messages-delete' => ['Deleted messages', 'red'],
    'message-delete' => ['Deleted a message', 'red'], 'messages-export' => ['Exported messages', 'blue'],
    'template-upload' => ['Uploaded template', 'blue'], 'template-delete' => ['Deleted template', 'red'],
    'versions-save' => ['Saved app versions', 'blue'], 'site-settings' => ['Saved site settings', 'blue'],
    'ai-settings' => ['Saved AI settings', 'blue'], 'ai-purge' => ['Cleared AI logs', 'red'],
    'plans-delete' => ['Deleted plans', 'red'], 'plan-delete' => ['Deleted a plan', 'red'],
    'notes-delete' => ['Deleted notes', 'red'], 'note-delete' => ['Deleted a note', 'red'],
];
?>
<section class="ad-card">
    <div class="ad-card-head"><h2>Recent activity</h2><span class="ad-muted"><?= number_format($total) ?> event<?= $total === 1 ? '' : 's' ?></span></div>
    <?php if ($items): ?>
        <div class="ad-table-wrap"><table class="ad-table">
            <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): [$label, $tone] = $labels[$it['action']] ?? [$it['action'], '']; ?>
                <tr>
                    <td class="ad-muted" style="white-space:nowrap"><?= e(fmt_date($it['created_at'])) ?></td>
                    <td><strong><?= e($it['admin']) ?></strong></td>
                    <td><span class="ad-pill <?= e($tone) ?>"><?= e($label) ?></span></td>
                    <td class="ad-muted"><?= e($it['details']) ?></td>
                    <td><code><?= e(AdminRepo::maskIp((string) $it['ip'])) ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php else: ?><p class="ad-empty">No activity yet.</p><?php endif; ?>
    <?= AdminApp::pager($page, $pages) ?>
</section>
