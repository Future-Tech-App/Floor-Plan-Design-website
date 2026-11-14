<?php /** Premium plans. Prices come from Admin → Premium Pricing. @var array $site */
$bannerTitle  = 'Floor Plan Premium';
$bannerKicker = 'Premium';
$bannerLead   = 'Everything you need to design like a pro — no ads, unlimited plans, every export format and full AI tools.';
require ROOT_DIR . '/views/partials/page_banner.php';
$plans = Pricing::plans();
$perks = ['No ads, ever', 'Unlimited plans & projects', 'All export formats: PNG, PDF, SVG & more', 'Full AI generator & analyzer', 'Android, iOS & macOS'];
$faqs = [
    ['How do I get Premium?', 'Install Floor Plan Designer on Android or iOS, open the menu and choose Premium. Pick a plan and pay through Google Play or the App Store.'],
    ['Can I cancel anytime?', 'Yes. Subscriptions renew automatically until you cancel. Cancel at least 24 hours before the end of the current period in Google Play or your Apple ID settings.'],
    ['I changed phones. How do I get Premium back?', 'Open Premium in the app and tap Restore, signed in with the same Google or Apple account you used to buy.'],
    ['Do I need Premium for the web app?', 'No. The web app is free and has no ads. Premium applies to the Android and iOS apps.'],
];
?>
<section class="section">
    <div class="container">
        <div class="section-head reveal">
            <p class="kicker">Choose your plan</p>
            <h2>Simple pricing, cancel anytime</h2>
            <p class="muted">Every plan unlocks all Premium features. Pay through Google Play or the App Store.</p>
        </div>
        <div class="price-grid price-grid-<?= count($plans) ?>">
            <?php foreach ($plans as $p): ?>
                <article class="price-card reveal<?= $p['badgeKey'] === 'popular' ? ' is-featured' : '' ?>">
                    <?php if ($p['badge'] !== '' && $p['badge'] !== 'None'): ?><span class="price-badge price-badge-<?= e($p['badgeKey']) ?>"><?= e($p['badge']) ?></span><?php endif; ?>
                    <h3><?= e($p['label']) ?></h3>
                    <?php if ($p['priceText'] !== null): ?>
                        <p class="price-amount"><strong><?= e($p['priceText']) ?></strong><span>/ <?= e($p['per']) ?></span></p>
                        <p class="price-note"><?= e($p['perWeekText'] ?? 'Billed every week') ?><?php if ($p['save']): ?> · <b>Save <?= (int) $p['save'] ?>%</b><?php endif; ?></p>
                    <?php else: ?>
                        <p class="price-amount price-tba"><strong>See price in the app</strong></p>
                        <p class="price-note">Billed every <?= e($p['per']) ?> · local prices in your store</p>
                    <?php endif; ?>
                    <ul class="price-perks">
                        <?php foreach ($perks as $perk): ?><li><?= icon('check') ?><?= e($perk) ?></li><?php endforeach; ?>
                    </ul>
                    <div class="price-actions">
                        <?php if ($site['android_url']): ?>
                            <a class="btn<?= $p['badgeKey'] === 'popular' ? ' btn-light' : '' ?>" href="<?= e($site['android_url']) ?>" target="_blank" rel="noopener"><?= icon('device') ?> Get it on Google Play</a>
                        <?php endif; ?>
                        <?php if ($site['ios_url']): ?>
                            <a class="btn <?= $p['badgeKey'] === 'popular' ? 'btn-outline-light' : 'btn-ghost' ?>" href="<?= e($site['ios_url']) ?>" target="_blank" rel="noopener"><?= icon('device') ?> Download on the App Store</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="price-web-note reveal"><?= icon('monitor') ?> The <a href="<?= e(url('/app')) ?>">web app</a> stays free with no ads — Premium is for the Android and iOS apps.</p>
    </div>
</section>

<section class="section section-tint">
    <div class="container narrow">
        <div class="section-head reveal">
            <p class="kicker">Compare</p>
            <h2>Free vs Premium</h2>
        </div>
        <div class="table-wrap reveal">
            <table class="compare">
                <thead><tr><th>Feature</th><th>Free</th><th>Premium</th></tr></thead>
                <tbody>
                <?php foreach (Pricing::FEATURES as [$feature, $free, $pro]): ?>
                    <tr>
                        <td><?= e($feature) ?></td>
                        <td<?= $free === true ? ' class="yes"' : '' ?>><?= $free === true ? '✓' : e((string) $free) ?></td>
                        <td class="yes"><?= $pro === true ? '✓' : e((string) $pro) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="section">
    <div class="container narrow">
        <div class="section-head reveal"><p class="kicker">Questions</p><h2>Premium FAQ</h2></div>
        <?php foreach ($faqs as [$q, $a]): ?>
            <details class="faq-item reveal"><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details>
        <?php endforeach; ?>
        <p class="muted small" style="margin-top:22px">Subscriptions renew automatically unless cancelled at least 24 hours before the end of the current period. Payment is charged to your Google Play or Apple account. Manage or cancel in your store account settings. See our <a href="<?= e(url('/terms-of-use')) ?>">Terms of Use</a> and <a href="<?= e(url('/privacy-policy')) ?>">Privacy Policy</a>.</p>
    </div>
</section>
