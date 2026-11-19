<?php /** @var array $values @var array $errors */
$err = static fn (string $k) => isset($errors[$k]) ? '<em class="ad-err">' . e($errors[$k]) . '</em>' : '';
?>
<div class="ad-note ad-mb"><?= icon('zap') ?><span>These prices appear on the website's <a href="<?= e(url('/premium')) ?>" target="_blank" rel="noopener">Premium page</a>. Purchases happen in the Android and iOS apps, so keep them in line with your Google Play and App Store prices. Leave a price empty to show “See price in the app”.</span></div>
<form method="post" action="<?= e(url('/admin/pricing')) ?>">
    <?= csrf_field() ?>
    <section class="ad-card ad-mb">
        <div class="ad-card-head"><h2>Currency</h2></div>
        <div class="ad-card-body ad-form-grid">
            <label class="ad-field"><span>Currency symbol or code</span><input name="price_currency" maxlength="6" value="<?= e($values['currency']) ?>" placeholder="$"><small>Shown before every price, e.g. $, €, £ or PKR.</small><?= $err('price_currency') ?></label>
        </div>
    </section>
    <div class="ad-grid ad-grid-3">
        <?php foreach (Pricing::PLANS as $k => [$label, $per]): $v = $values[$k]; ?>
            <section class="ad-card">
                <div class="ad-card-head"><h2><?= e($label) ?></h2><span class="ad-muted">per <?= e($per) ?></span></div>
                <div class="ad-card-body ad-form">
                    <label class="ad-check"><input type="checkbox" name="price_<?= $k ?>_on" value="1" <?= $v['on'] ? 'checked' : '' ?>><span>Show this plan</span></label>
                    <label class="ad-field"><span>Price</span><input name="price_<?= $k ?>" inputmode="decimal" value="<?= e($v['price']) ?>" placeholder="<?= ['weekly' => '2.99', 'monthly' => '7.99', 'yearly' => '39.99'][$k] ?>"><?= $err('price_' . $k) ?></label>
                    <label class="ad-field"><span>Badge</span>
                        <select name="price_<?= $k ?>_badge"><?php foreach (Pricing::BADGES as $bk => $bl): ?><option value="<?= e($bk) ?>"<?= $v['badge'] === $bk ? ' selected' : '' ?>><?= e($bl) ?></option><?php endforeach; ?></select>
                    </label>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
    <div class="ad-actions" style="margin-top:18px">
        <button class="ad-btn ad-btn-primary" type="submit">Save pricing</button>
        <a class="ad-btn" href="<?= e(url('/premium')) ?>" target="_blank" rel="noopener">View Premium page</a>
    </div>
</form>
