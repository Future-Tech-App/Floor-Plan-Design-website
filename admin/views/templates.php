<?php /** @var array $templates @var array $cats @var bool $writable */
$byCat = [];
foreach ($templates as $t) { $byCat[$t['cat']][] = $t; }
?>
<?php if (!$writable): ?>
    <div class="ad-note warn ad-mb"><?= icon('help') ?><span>The folder <code>public/assets/app/templates</code> is not writable on this server, so uploads and deletions will fail. Set its permissions to 755 in cPanel → File Manager.</span></div>
<?php endif; ?>
<section class="ad-card ad-mb">
    <div class="ad-card-head"><h2>Add a template</h2><span class="ad-muted">JPG, PNG or WebP · up to 5 MB · square images look best</span></div>
    <form method="post" action="<?= e(url('/admin/templates')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <div class="ad-card-body ad-form-grid">
            <label class="ad-field"><span>Category</span>
                <select name="cat" required><?php foreach ($cats as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
            </label>
            <div class="ad-field"><span>Image</span>
                <label class="ad-file"><span class="ad-btn ad-btn-sm"><?= icon('upload') ?> Choose image</span><span data-file-name>No file chosen</span>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label>
            </div>
        </div>
        <div class="ad-form-foot"><button class="ad-btn ad-btn-primary" type="submit"><?= icon('plus') ?> Upload template</button></div>
    </form>
</section>
<?php foreach ($cats as $k => $label): ?>
    <section class="ad-card ad-mb">
        <div class="ad-card-head"><h2><?= e($label) ?></h2><span class="ad-muted"><?= count($byCat[$k] ?? []) ?> plan<?= count($byCat[$k] ?? []) === 1 ? '' : 's' ?></span></div>
        <div class="ad-card-body">
            <?php if (!empty($byCat[$k])): ?>
                <div class="ad-tpl-grid">
                    <?php foreach ($byCat[$k] as $t): ?>
                        <figure class="ad-tpl" style="margin:0">
                            <a href="<?= e(url('/assets/app/templates/' . $t['file'])) ?>" target="_blank" rel="noopener"><img src="<?= e(url('/assets/app/templates/' . $t['file'])) ?>" alt="<?= e($t['name']) ?>" loading="lazy"></a>
                            <div><span>Plan <?= (int) $t['num'] ?></span>
                                <form method="post" action="<?= e(url('/admin/templates')) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="file" value="<?= e($t['file']) ?>">
                                    <button class="ad-btn ad-btn-sm ad-btn-danger" type="submit" aria-label="Delete plan <?= (int) $t['num'] ?>" data-confirm="Delete <?= e($t['name']) ?>? It disappears from the web app's Templates page."><?= icon('trash') ?></button>
                                </form>
                            </div>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><p class="ad-empty" style="padding:10px">No templates in this category.</p><?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>
