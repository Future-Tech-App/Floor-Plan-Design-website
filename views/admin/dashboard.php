<?php /** @var array $messages @var array $latest @var int $views7 @var ?int $viewsDiff @var int $today @var array $viewsDays @var array $ai @var array $aiDays @var array $topPages @var array $system */
$maxTop = max(1, ...array_map(static fn ($r) => (int) $r['views'], $topPages ?: [['views' => 1]]));
?>
<div class="ad-grid ad-grid-4 ad-mb">
    <a class="ad-card ad-stat" href="<?= e(url('/admin/messages?filter=unread')) ?>" style="text-decoration:none">
        <span class="ad-stat-ic"><?= icon('mail') ?></span>
        <span><small>Unread messages</small><strong><?= number_format($messages['unread']) ?></strong><em class="ad-muted"><?= number_format($messages['total']) ?> total · <?= $messages['week'] ?> this week</em></span>
    </a>
    <div class="ad-card ad-stat">
        <span class="ad-stat-ic green"><?= icon('globe') ?></span>
        <span><small>Page views · 7 days</small><strong><?= number_format($views7) ?></strong>
            <em class="<?= $viewsDiff === null ? 'ad-muted' : ($viewsDiff >= 0 ? 'up' : 'down') ?>"><?= $viewsDiff === null ? 'No earlier data' : ($viewsDiff >= 0 ? '▲ ' : '▼ ') . abs($viewsDiff) . '% vs previous 7 days' ?></em></span>
    </div>
    <a class="ad-card ad-stat" href="<?= e(url('/admin/plans')) ?>" style="text-decoration:none">
        <span class="ad-stat-ic amber"><?= icon('pencil') ?></span>
        <span><small>Saved plans</small><strong><?= number_format($plans['total']) ?></strong><em class="ad-muted"><?= $plans['week'] ?> new this week · <?= $plans['devices'] ?> devices</em></span>
    </a>
    <a class="ad-card ad-stat" href="<?= e(url('/admin/ai')) ?>" style="text-decoration:none">
        <span class="ad-stat-ic purple"><?= icon('sparkles') ?></span>
        <span><small>AI requests · 7 days</small><strong><?= number_format($ai['total']) ?></strong><em class="<?= $ai['rate'] >= 90 ? 'up' : 'down' ?>"><?= $ai['rate'] ?>% successful</em></span>
    </a>
</div>

<div class="ad-grid ad-grid-main ad-mb">
    <section class="ad-card">
        <div class="ad-card-head"><h2>Page views</h2><span class="ad-muted">Last 14 days · <?= number_format($today) ?> today</span></div>
        <div class="ad-card-body"><?= AdminApp::chart([['values' => $viewsDays, 'class' => 'bar']]) ?></div>
    </section>
    <section class="ad-card">
        <div class="ad-card-head"><h2>Top pages</h2><a href="<?= e(url('/admin/analytics')) ?>" class="ad-muted">Analytics →</a></div>
        <?php if ($topPages): ?>
            <table class="ad-table"><tbody>
                <?php foreach ($topPages as $r): ?>
                    <tr><td class="ad-bar-cell"><i style="width:<?= round($r['views'] / $maxTop * 100) ?>%"></i><span><?= e($r['path']) ?></span></td><td class="num"><?= number_format((int) $r['views']) ?></td></tr>
                <?php endforeach; ?>
            </tbody></table>
        <?php else: ?><p class="ad-empty">No page views recorded yet.</p><?php endif; ?>
    </section>
</div>

<div class="ad-grid ad-grid-main">
    <section class="ad-card">
        <div class="ad-card-head"><h2>Latest messages</h2><a href="<?= e(url('/admin/messages')) ?>" class="ad-muted">All messages →</a></div>
        <?php if ($latest): ?>
            <div class="ad-table-wrap"><table class="ad-table"><tbody>
                <?php foreach ($latest as $m): ?>
                    <tr class="<?= $m['is_read'] ? '' : 'is-unread' ?>">
                        <td><a href="<?= e(url('/admin/messages/' . $m['id'])) ?>"><?= e($m['name']) ?></a><br><span class="ad-muted"><?= e($m['email']) ?></span></td>
                        <td><span class="ad-pill <?= $m['is_read'] ? '' : 'blue' ?>"><?= e($m['subject']) ?></span></td>
                        <td class="num ad-muted"><?= e(fmt_date($m['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?><p class="ad-empty">No messages yet.</p><?php endif; ?>
    </section>
    <div class="ad-grid">
        <section class="ad-card">
            <div class="ad-card-head"><h2>AI requests</h2><span class="ad-legend"><span><i style="background:#43a047"></i>OK</span><span><i style="background:#e57373"></i>Error</span></span></div>
            <div class="ad-card-body"><?= AdminApp::chart([
                ['values' => array_map(static fn ($d) => $d['ok'], $aiDays), 'class' => 'bar-ok'],
                ['values' => array_map(static fn ($d) => $d['err'], $aiDays), 'class' => 'bar-err'],
            ], 210, 440) ?></div>
        </section>
        <section class="ad-card">
            <div class="ad-card-head"><h2>System status</h2></div>
            <ul class="ad-status-list">
                <?php foreach ($system as [$label, $value, $ok]): ?>
                    <li><span><?= e($label) ?></span><span class="ad-pill <?= $ok ? 'green' : 'amber' ?>"><?= e($value) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>

<section class="ad-card" style="margin-top:18px">
    <div class="ad-card-head"><h2>Latest saved plans</h2><a href="<?= e(url('/admin/plans')) ?>" class="ad-muted">All plans →</a></div>
    <?php if ($recentPlans): ?>
        <div class="ad-table-wrap"><table class="ad-table"><tbody>
            <?php foreach ($recentPlans as $p): ?>
                <tr>
                    <td><a href="<?= e(url('/admin/plans/' . $p['id'])) ?>"><strong><?= e($p['name']) ?></strong></a></td>
                    <td><span class="ad-pill"><?= e(PlanStore::SOURCES[$p['source']] ?? $p['source']) ?></span></td>
                    <td class="num"><?= (int) $p['rooms'] ?> rooms</td>
                    <td class="num"><?= $p['area_m2'] > 0 ? number_format((float) $p['area_m2'], 1) . ' m²' : '—' ?></td>
                    <td class="num ad-muted"><?= e(fmt_date($p['updated_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?><p class="ad-empty">No plans saved yet.</p><?php endif; ?>
</section>
