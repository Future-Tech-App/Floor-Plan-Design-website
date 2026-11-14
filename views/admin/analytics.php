<?php /** @var int $days @var array $byDay @var array $appDay @var int $total @var array $split @var array $top */
$siteDay = [];
foreach ($byDay as $d => $v) { $siteDay[$d] = max(0, $v - ($appDay[$d] ?? 0)); }
$maxTop = max(1, ...array_map(static fn ($r) => (int) $r['views'], $top ?: [['views' => 1]]));
$avg = $days ? round($total / $days, 1) : 0;
?>
<div class="ad-actions ad-mb">
    <nav class="ad-tabs" aria-label="Range">
        <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $label): ?>
            <a href="<?= e(url('/admin/analytics?days=' . $d)) ?>" class="<?= $days === $d ? 'is-active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <span class="ad-muted" style="font-size:12.5px">Counts only — no cookies, IP addresses or visitor IDs are stored. Bots are ignored.</span>
</div>
<div class="ad-grid ad-grid-4 ad-mb">
    <div class="ad-card ad-stat"><span class="ad-stat-ic"><?= icon('globe') ?></span><span><small>Total views</small><strong><?= number_format($total) ?></strong><em class="ad-muted">Last <?= $days ?> days</em></span></div>
    <div class="ad-card ad-stat"><span class="ad-stat-ic green"><?= icon('zap') ?></span><span><small>Daily average</small><strong><?= $avg ?></strong><em class="ad-muted">Views per day</em></span></div>
    <div class="ad-card ad-stat"><span class="ad-stat-ic amber"><?= icon('home') ?></span><span><small>Website</small><strong><?= number_format($split['site']) ?></strong><em class="ad-muted"><?= $total ? round($split['site'] / $total * 100) : 0 ?>% of views</em></span></div>
    <div class="ad-card ad-stat"><span class="ad-stat-ic purple"><?= icon('monitor') ?></span><span><small>Web app</small><strong><?= number_format($split['app']) ?></strong><em class="ad-muted"><?= $total ? round($split['app'] / $total * 100) : 0 ?>% of views</em></span></div>
</div>
<section class="ad-card ad-mb">
    <div class="ad-card-head"><h2>Views per day</h2><span class="ad-legend"><span><i style="background:#1e88e5"></i>Website</span><span><i style="background:#90caf9"></i>Web app</span></span></div>
    <div class="ad-card-body"><?= AdminApp::chart([['values' => $siteDay, 'class' => 'bar'], ['values' => $appDay, 'class' => 'bar-2']], 260, 1100) ?></div>
</section>
<section class="ad-card">
    <div class="ad-card-head"><h2>Most viewed pages</h2><span class="ad-muted">Last <?= $days ?> days</span></div>
    <?php if ($top): ?>
        <div class="ad-table-wrap"><table class="ad-table">
            <thead><tr><th>Page</th><th class="num">Views</th><th class="num">Share</th></tr></thead>
            <tbody>
            <?php foreach ($top as $r): ?>
                <tr>
                    <td class="ad-bar-cell"><i style="width:<?= round($r['views'] / $maxTop * 100) ?>%"></i><span><a href="<?= e(url(str_replace('*', '', $r['path']))) ?>" target="_blank" rel="noopener"><?= e($r['path']) ?></a></span></td>
                    <td class="num"><?= number_format((int) $r['views']) ?></td>
                    <td class="num ad-muted"><?= $total ? round($r['views'] / $total * 100, 1) : 0 ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php else: ?><p class="ad-empty">No page views recorded in this period yet.</p><?php endif; ?>
</section>
