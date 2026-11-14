<?php /** @var array $byDay @var array $totals7 @var array $totals30 @var array $statuses @var array $ips @var bool $enabled @var int $limit @var bool $hasKey */
$statusLabel = static fn (int $s): string => match (true) { $s === 200 => 'OK', $s === 0 => 'No connection', $s === 429 => 'Rate limited', $s >= 500 => 'Service error', $s >= 400 => 'Rejected', default => (string) $s };
?>
<?php if (!$hasKey): ?>
    <div class="ad-note warn ad-mb"><?= icon('help') ?><span>No Groq API key is set on this server, so the AI generator uses its built-in layouts and the analyzer is unavailable. Add the <code>GROQ_API_KEY</code> secret in GitHub (or <code>config/secrets.php</code>).</span></div>
<?php endif; ?>
<div class="ad-grid ad-grid-4 ad-mb">
    <div class="ad-card ad-stat"><span class="ad-stat-ic purple"><?= icon('sparkles') ?></span><span><small>Requests · 7 days</small><strong><?= number_format($totals7['total']) ?></strong><em class="ad-muted"><?= number_format($totals30['total']) ?> in 30 days</em></span></div>
    <div class="ad-card ad-stat"><span class="ad-stat-ic green"><?= icon('check') ?></span><span><small>Success rate · 7 days</small><strong><?= $totals7['rate'] ?>%</strong><em class="ad-muted"><?= number_format($totals7['ok']) ?> successful</em></span></div>
    <div class="ad-card ad-stat"><span class="ad-stat-ic amber"><?= icon('help') ?></span><span><small>Errors · 7 days</small><strong><?= number_format($totals7['err']) ?></strong><em class="ad-muted">Upstream or limit errors</em></span></div>
    <div class="ad-card ad-stat"><span class="ad-stat-ic"><?= icon('shield') ?></span><span><small>Limit per visitor</small><strong><?= $limit ?>/h</strong><em class="<?= $enabled ? 'up' : 'down' ?>"><?= $enabled ? 'AI tools are on' : 'AI tools are off' ?></em></span></div>
</div>
<section class="ad-card ad-mb">
    <div class="ad-card-head"><h2>Requests per day</h2><span class="ad-legend"><span><i style="background:#43a047"></i>OK</span><span><i style="background:#e57373"></i>Error</span></span></div>
    <div class="ad-card-body"><?= AdminApp::chart([
        ['values' => array_map(static fn ($d) => $d['ok'], $byDay), 'class' => 'bar-ok'],
        ['values' => array_map(static fn ($d) => $d['err'], $byDay), 'class' => 'bar-err'],
    ], 260, 1100) ?></div>
</section>
<div class="ad-grid ad-grid-3">
    <section class="ad-card">
        <div class="ad-card-head"><h2>Settings</h2></div>
        <form method="post" action="<?= e(url('/admin/ai')) ?>">
            <?= csrf_field() ?>
            <div class="ad-card-body ad-form">
                <label class="ad-check"><input type="checkbox" name="ai_enabled" value="1" <?= $enabled ? 'checked' : '' ?>><span>AI tools on<small>Switch off to stop all AI calls; the generator then uses built-in layouts.</small></span></label>
                <label class="ad-field"><span>Requests per visitor per hour</span><input type="number" name="ai_rate_max" min="1" max="1000" value="<?= $limit ?>" required></label>
            </div>
            <div class="ad-form-foot"><button class="ad-btn ad-btn-primary" name="action" value="save">Save</button></div>
        </form>
    </section>
    <section class="ad-card">
        <div class="ad-card-head"><h2>Results · 30 days</h2></div>
        <?php if ($statuses): ?>
            <table class="ad-table"><tbody>
            <?php foreach ($statuses as $s): ?>
                <tr><td><span class="ad-pill <?= (int) $s['status'] === 200 ? 'green' : 'red' ?>"><?= e($statusLabel((int) $s['status'])) ?></span> <span class="ad-muted"><?= (int) $s['status'] ?: '' ?></span></td><td class="num"><?= number_format((int) $s['n']) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        <?php else: ?><p class="ad-empty">No AI requests yet.</p><?php endif; ?>
    </section>
    <section class="ad-card">
        <div class="ad-card-head"><h2>Busiest visitors · 7 days</h2></div>
        <?php if ($ips): ?>
            <table class="ad-table"><tbody>
            <?php foreach ($ips as $r): ?>
                <tr><td><code><?= e($r['ip']) ?></code><br><span class="ad-muted" style="font-size:12px">Last: <?= e(fmt_date($r['last'])) ?></span></td><td class="num"><?= number_format((int) $r['n']) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        <?php else: ?><p class="ad-empty">No AI requests in the last 7 days.</p><?php endif; ?>
        <form method="post" action="<?= e(url('/admin/ai')) ?>" class="ad-form-foot">
            <?= csrf_field() ?>
            <button class="ad-btn ad-btn-sm ad-btn-danger" name="action" value="purge" data-confirm="Delete AI log entries older than 30 days?"><?= icon('trash') ?> Clear logs older than 30 days</button>
        </form>
    </section>
</div>
