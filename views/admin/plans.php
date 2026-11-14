<?php /** @var array $plans @var int $total @var int $page @var int $pages @var string $source @var string $q @var array $stats */
$link = static fn (array $extra) => url('/admin/plans') . '?' . http_build_query(array_filter($extra + ['source' => $source ?: null, 'q' => $q ?: null]));
$srcPill = ['web' => 'blue', 'android' => 'green', 'ios' => 'amber'];
?>
<div class="ad-grid ad-grid-4 ad-mb">
    <div class="ad-card ad-stat"><span class="ad-stat-ic"><?= icon('pencil') ?></span><span><small>Saved plans</small><strong><?= number_format($stats['total']) ?></strong><em class="ad-muted"><?= $stats['week'] ?> new this week</em></span></div>
    <div class="ad-card ad-stat"><span class="ad-stat-ic purple"><?= icon('device') ?></span><span><small>Devices</small><strong><?= number_format($stats['devices']) ?></strong><em class="ad-muted">Browsers and phones</em></span></div>
    <div class="ad-card ad-stat"><span class="ad-stat-ic green"><?= icon('monitor') ?></span><span><small>From web app</small><strong><?= number_format($stats['source']['web'] ?? 0) ?></strong><em class="ad-muted">floorplandesigner.app/app</em></span></div>
    <div class="ad-card ad-stat"><span class="ad-stat-ic amber"><?= icon('device') ?></span><span><small>From mobile apps</small><strong><?= number_format(($stats['source']['android'] ?? 0) + ($stats['source']['ios'] ?? 0)) ?></strong><em class="ad-muted"><?= (int) ($stats['source']['android'] ?? 0) ?> Android · <?= (int) ($stats['source']['ios'] ?? 0) ?> iOS</em></span></div>
</div>
<section class="ad-card">
    <div class="ad-toolbar">
        <nav class="ad-tabs" aria-label="Source">
            <a href="<?= e($link(['source' => null])) ?>" class="<?= $source === '' ? 'is-active' : '' ?>">All</a>
            <?php foreach (PlanStore::SOURCES as $k => $label): ?>
                <a href="<?= e($link(['source' => $k])) ?>" class="<?= $source === $k ? 'is-active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <form class="ad-search" method="get" action="<?= e(url('/admin/plans')) ?>" role="search">
            <?php if ($source !== ''): ?><input type="hidden" name="source" value="<?= e($source) ?>"><?php endif; ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search plan names…" aria-label="Search plans">
            <button class="ad-btn" type="submit"><?= icon('search') ?></button>
        </form>
    </div>
    <form method="post" action="<?= e($link([])) ?>">
        <?= csrf_field() ?>
        <div class="ad-bulk">
            <span data-selected></span>
            <button class="ad-btn ad-btn-sm ad-btn-danger" name="action" value="delete" data-bulk disabled data-confirm="Delete the selected plans from the server? People keep their own copies on their devices.">Delete</button>
            <span class="ad-muted" style="margin-left:auto;font-size:12.5px"><?= number_format($total) ?> plan<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' matching “' . e($q) . '”' : '' ?></span>
        </div>
        <?php if ($plans): ?>
            <div class="ad-table-wrap"><table class="ad-table">
                <thead><tr><th class="w-check"><input type="checkbox" data-check-all aria-label="Select all"></th><th>Plan</th><th>Source</th><th class="num">Floors</th><th class="num">Rooms</th><th class="num">Area</th><th class="num">Size</th><th class="num">Last saved</th></tr></thead>
                <tbody>
                <?php foreach ($plans as $p): ?>
                    <tr>
                        <td class="w-check"><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" aria-label="Select <?= e($p['name']) ?>"></td>
                        <td><a href="<?= e(url('/admin/plans/' . $p['id'])) ?>"><strong><?= e($p['name']) ?></strong></a><br><span class="ad-muted" style="font-size:12px"><?= (int) $p['walls'] ?> walls · <?= (int) $p['items'] ?> items</span></td>
                        <td><span class="ad-pill <?= $srcPill[$p['source']] ?? '' ?>"><?= e(PlanStore::SOURCES[$p['source']] ?? $p['source']) ?></span></td>
                        <td class="num"><?= (int) $p['floors'] ?></td>
                        <td class="num"><?= (int) $p['rooms'] ?></td>
                        <td class="num"><?= $p['area_m2'] > 0 ? number_format((float) $p['area_m2'], 1) . ' m²' : '—' ?></td>
                        <td class="num ad-muted"><?= e(AdminApp::bytes((int) $p['size'])) ?></td>
                        <td class="num ad-muted" style="white-space:nowrap"><?= e(fmt_date($p['updated_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php else: ?>
            <p class="ad-empty"><?= $q !== '' || $source !== '' ? 'No plans match these filters.' : 'No plans saved yet. Plans appear here as soon as someone saves one in the web app or the mobile app.' ?></p>
        <?php endif; ?>
    </form>
    <?= AdminApp::pager($page, $pages, array_filter(['source' => $source ?: null, 'q' => $q ?: null])) ?>
</section>
