<?php /** Calculator hub. @var array $hub */
$headLead = 'Estimate materials and costs, work out areas, and convert units. Every result is calculated on our server — nothing to install.';
require ROOT_DIR . '/views/app/_head.php';
$known = ['pencil', 'cube', 'calculator', 'ruler', 'layers', 'home', 'compass', 'level', 'globe', 'zap', 'clock', 'chair', 'image'];
$tabs = $hub['tabs'] ?? [];
?>
<div class="page-body">
    <div class="a-container">
        <?php if (count($tabs) > 1): ?>
            <div class="tabs" data-tabs role="tablist">
                <?php foreach ($tabs as $i => $tab): ?>
                    <button type="button" role="tab" data-tab="<?= e($tab['id']) ?>" class="<?= $i === 0 ? 'is-active' : '' ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= e($tab['name']) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php foreach ($tabs as $i => $tab): ?>
            <section data-tab-panel="<?= e($tab['id']) ?>"<?= $i ? ' hidden' : '' ?> aria-label="<?= e($tab['name']) ?>">
                <div class="calc-grid">
                    <?php foreach ($tab['tiles'] as $tile): ?>
                        <a class="calc-tile" href="<?= e(url('/app/calculators/' . $tile['id'])) ?>">
                            <span class="ring"<?= !empty($tile['color']) ? ' style="color:' . e($tile['color']) . '"' : '' ?>><?= icon(in_array($tile['icon'] ?? '', $known, true) ? $tile['icon'] : 'calculator') ?></span>
                            <?= e($tile['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        <div class="notice"><?= icon('help') ?><div>Results are estimates for planning. Add a waste margin and confirm quantities with your supplier before you buy.</div></div>
    </div>
</div>
