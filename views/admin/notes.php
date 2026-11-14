<?php /** @var array $notes @var int $total @var int $page @var int $pages @var string $source @var string $q @var string $device @var array $query @var array $stats */
$link = static fn (array $extra) => url('/admin/notes') . (($qq = array_filter($extra + $query)) ? '?' . http_build_query($qq) : '');
$srcPill = ['web' => 'blue', 'android' => 'green', 'ios' => 'amber'];
?>
<div class="ad-grid ad-grid-4 ad-mb">
    <div class="ad-card ad-stat"><span class="ad-stat-ic"><?= icon('note') ?></span><span><small>User notes</small><strong><?= number_format($stats['total']) ?></strong><em class="ad-muted">On the server</em></span></div>
    <div class="ad-card ad-stat"><span class="ad-stat-ic purple"><?= icon('device') ?></span><span><small>Devices</small><strong><?= number_format($stats['devices']) ?></strong><em class="ad-muted">Browsers and phones</em></span></div>
    <div class="ad-card ad-stat"><span class="ad-stat-ic green"><?= icon('monitor') ?></span><span><small>From web app</small><strong><?= number_format($stats['source']['web'] ?? 0) ?></strong><em class="ad-muted">floorplandesigner.app/app</em></span></div>
    <div class="ad-card ad-stat"><span class="ad-stat-ic amber"><?= icon('device') ?></span><span><small>From mobile apps</small><strong><?= number_format(($stats['source']['android'] ?? 0) + ($stats['source']['ios'] ?? 0)) ?></strong><em class="ad-muted"><?= (int) ($stats['source']['android'] ?? 0) ?> Android · <?= (int) ($stats['source']['ios'] ?? 0) ?> iOS</em></span></div>
</div>
<?php if ($device !== ''): ?>
    <div class="ad-note ad-mb"><?= icon('device') ?><span>Showing notes from one device (<code><?= e(substr($device, 0, 10)) ?>…</code>). <a href="<?= e($link(['device' => null])) ?>">Show all notes</a></span></div>
<?php endif; ?>
<section class="ad-card">
    <div class="ad-toolbar">
        <nav class="ad-tabs" aria-label="Source">
            <a href="<?= e($link(['source' => null])) ?>" class="<?= $source === '' ? 'is-active' : '' ?>">All</a>
            <?php foreach (PlanStore::SOURCES as $k => $label): ?>
                <a href="<?= e($link(['source' => $k])) ?>" class="<?= $source === $k ? 'is-active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <form class="ad-search" method="get" action="<?= e(url('/admin/notes')) ?>" role="search">
            <?php foreach (['source' => $source, 'device' => $device] as $k => $v): if ($v !== ''): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search titles and text…" aria-label="Search notes">
            <button class="ad-btn" type="submit"><?= icon('search') ?></button>
        </form>
    </div>
    <form method="post" action="<?= e($link([])) ?>">
        <?= csrf_field() ?>
        <div class="ad-bulk">
            <span data-selected></span>
            <button class="ad-btn ad-btn-sm ad-btn-danger" name="action" value="delete" data-bulk disabled data-confirm="Delete the selected notes from the server? If the person edits their notes again, their device sends its list again.">Delete</button>
            <span class="ad-muted" style="margin-left:auto;font-size:12.5px"><?= number_format($total) ?> note<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' matching “' . e($q) . '”' : '' ?></span>
        </div>
        <?php if ($notes): ?>
            <div class="ad-table-wrap"><table class="ad-table">
                <thead><tr><th class="w-check"><input type="checkbox" data-check-all aria-label="Select all"></th><th>Note</th><th>Category</th><th>Source</th><th>Device</th><th class="num">Last edited</th></tr></thead>
                <tbody>
                <?php foreach ($notes as $n): ?>
                    <tr>
                        <td class="w-check"><input type="checkbox" name="ids[]" value="<?= (int) $n['id'] ?>" aria-label="Select <?= e($n['title'] ?: 'note') ?>"></td>
                        <td><a href="<?= e(url('/admin/notes/' . $n['id'])) ?>"><strong><?= e($n['title'] !== '' ? $n['title'] : 'Untitled') ?></strong></a><br>
                            <span class="ad-muted ad-trunc" style="font-size:12.5px"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', (string) $n['excerpt']), 0, 120, '…')) ?></span></td>
                        <td><?= $n['category'] !== '' ? '<span class="ad-pill">' . e($n['category']) . '</span>' : '<span class="ad-muted">—</span>' ?></td>
                        <td><span class="ad-pill <?= $srcPill[$n['source']] ?? '' ?>"><?= e(PlanStore::SOURCES[$n['source']] ?? $n['source']) ?></span></td>
                        <td><a href="<?= e($link(['device' => $n['owner'], 'page' => null])) ?>" title="Show this device's notes"><code><?= e(substr((string) $n['owner'], 0, 8)) ?>…</code></a></td>
                        <td class="num ad-muted" style="white-space:nowrap"><?= e(fmt_date($n['note_time'] !== '' ? $n['note_time'] : $n['synced_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php else: ?>
            <p class="ad-empty"><?= $q !== '' || $source !== '' || $device !== '' ? 'No notes match these filters.' : 'No notes yet. Notes appear here when someone saves one in the web app or the mobile app.' ?></p>
        <?php endif; ?>
    </form>
    <?= AdminApp::pager($page, $pages, $query) ?>
</section>
