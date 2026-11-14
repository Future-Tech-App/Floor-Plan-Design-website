<?php /** Page banner for app pages. @var string $pageTitle  @var string|null $headLead */ ?>
<?php
// A different banner photo for each section of the web app (free Unsplash photos).
$banners = [
    'templates'   => 'interior/banner-plan.jpg',
    'plans'       => 'banners/plans.jpg',
    'calculators' => 'banners/calculators.jpg',
    'ai'          => 'banners/ai.jpg',
    'notes'       => 'banners/notes.jpg',
    'about'       => 'interior/hero-dining.jpg',
];
$banner = $banners[$nav ?? ''] ?? 'interior/banner-lounge.jpg';
?>
<section class="page-head<?= !empty($headClass) ? ' ' . e($headClass) : '' ?>" style="background-image:url('<?= e(url('/assets/img/' . $banner)) ?>')">
    <div class="a-container">
        <h1><?= e($pageTitle) ?></h1>
        <p><a href="<?= e(url('/app')) ?>">Dashboard</a> &nbsp;/&nbsp; <?= e($pageTitle) ?></p>
        <?php if (!empty($headLead)): ?><p style="margin-top:10px;max-width:640px;margin-left:auto;margin-right:auto"><?= e($headLead) ?></p><?php endif; ?>
    </div>
</section>
