<?php /** @var array $row @var string $svg @var array $floors @var int $floor @var int $deviceCount */ ?>
<div class="ad-actions ad-mb">
    <a class="ad-btn" href="<?= e(url('/admin/plans')) ?>"><?= icon('chevron-left') ?> All plans</a>
    <span style="flex:1"></span>
    <a class="ad-btn" href="<?= e(url('/admin/plans/' . $row['id'] . '/download')) ?>"><?= icon('download') ?> Download JSON</a>
    <form method="post" action="<?= e(url('/admin/plans/' . $row['id'])) ?>">
        <?= csrf_field() ?>
        <button class="ad-btn ad-btn-danger" name="action" value="delete" data-confirm="Delete this plan from the server? The person keeps their own copy."><?= icon('trash') ?> Delete</button>
    </form>
</div>
<div class="ad-grid ad-grid-main">
    <section class="ad-card">
        <div class="ad-card-head">
            <h2>Preview</h2>
            <?php if (count($floors) > 1): ?>
                <nav class="ad-tabs" aria-label="Floors">
                    <?php foreach ($floors as $i => $fname): ?>
                        <a href="<?= e(url('/admin/plans/' . $row['id'] . '?floor=' . $i)) ?>" class="<?= $i === $floor ? 'is-active' : '' ?>"><?= e($fname) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        </div>
        <div class="ad-card-body ad-plan-preview"><?= $svg ?></div>
    </section>
    <section class="ad-card">
        <div class="ad-card-head"><h2>Details</h2></div>
        <div class="ad-card-body">
            <dl class="ad-msg-meta">
                <dt>Name</dt><dd><?= e($row['name']) ?></dd>
                <dt>Source</dt><dd><?= e(PlanStore::SOURCES[$row['source']] ?? $row['source']) ?></dd>
                <dt>Floors</dt><dd><?= (int) $row['floors'] ?></dd>
                <dt>Rooms</dt><dd><?= (int) $row['rooms'] ?></dd>
                <dt>Walls</dt><dd><?= (int) $row['walls'] ?></dd>
                <dt>Items</dt><dd><?= (int) $row['items'] ?></dd>
                <dt>Floor area</dt><dd><?= number_format((float) $row['area_m2'], 2) ?> m²</dd>
                <dt>Size</dt><dd><?= e(AdminApp::bytes((int) $row['size'])) ?></dd>
                <dt>First saved</dt><dd><?= e(fmt_date($row['created_at'])) ?></dd>
                <dt>Last saved</dt><dd><?= e(fmt_date($row['updated_at'])) ?></dd>
                <dt>Device</dt><dd><code><?= e(substr((string) $row['owner'], 0, 10)) ?>…</code> · <?= $deviceCount ?> plan<?= $deviceCount === 1 ? '' : 's' ?></dd>
            </dl>
        </div>
    </section>
</div>
