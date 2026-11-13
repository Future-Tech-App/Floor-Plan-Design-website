<?php /** @var array $templates */
require ROOT_DIR . '/views/app/_head.php';
$counts = array_count_values(array_column($templates, 'cat'));
?>
<div class="page-body">
    <div class="a-container">
        <div class="tabs" data-filter-group role="toolbar" aria-label="Filter templates">
            <button type="button" class="is-active" data-filter="all">All (<?= count($templates) ?>)</button>
            <button type="button" data-filter="1bed">1 Bed Apartment (<?= $counts['1bed'] ?? 0 ?>)</button>
            <button type="button" data-filter="2bed">2 Bed Apartment (<?= $counts['2bed'] ?? 0 ?>)</button>
            <button type="button" data-filter="3bed">3 Bed Apartment (<?= $counts['3bed'] ?? 0 ?>)</button>
        </div>
        <div class="grid grid-4">
            <?php foreach ($templates as $t): ?>
                <a class="tool-card" style="padding:14px" data-cat="<?= e($t['cat']) ?>" data-template data-name="<?= e($t['name']) ?>" href="<?= e(url('/assets/app/templates/' . $t['file'])) ?>">
                    <img src="<?= e(url('/assets/app/templates/' . $t['file'])) ?>" alt="<?= e($t['name']) ?>" loading="lazy" style="aspect-ratio:4/3;object-fit:contain;width:100%;background:#fff">
                    <h3 style="font-size:1.15rem;margin-top:8px"><?= e($t['name']) ?></h3>
                    <span class="link-arrow">View →</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Template preview">
    <figure>
        <img src="" alt="">
        <figcaption>
            <strong></strong>
            <span style="display:flex;gap:10px">
                <a class="btn btn-sm" data-download href="#" download><?= icon('download') ?> Download</a>
                <button class="btn btn-sm btn-dark" type="button" data-close>Close</button>
            </span>
        </figcaption>
    </figure>
</div>
