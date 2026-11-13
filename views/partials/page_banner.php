<?php
/**
 * Inner-page banner with breadcrumb.
 * @var string $bannerTitle
 * @var string $bannerLead   optional
 * @var string $bannerKicker optional small label above the title
 */
?>
<section class="page-banner">
    <div class="container page-banner-inner">
        <?php if (!empty($bannerKicker)): ?><p class="kicker kicker-light"><?= e($bannerKicker) ?></p><?php endif; ?>
        <h1><?= e($bannerTitle) ?></h1>
        <?php if (!empty($bannerLead)): ?><p class="page-banner-lead"><?= e($bannerLead) ?></p><?php endif; ?>
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="<?= e(url('/')) ?>">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?= e($bannerTitle) ?></span>
        </nav>
    </div>
</section>
