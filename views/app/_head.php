<?php /** Page banner for app pages. @var string $pageTitle  @var string|null $headLead */ ?>
<section class="page-head">
    <div class="a-container">
        <h1><?= e($pageTitle) ?></h1>
        <p><a href="<?= e(url('/app')) ?>">Dashboard</a> &nbsp;/&nbsp; <?= e($pageTitle) ?></p>
        <?php if (!empty($headLead)): ?><p style="margin-top:10px;max-width:640px;margin-left:auto;margin-right:auto"><?= e($headLead) ?></p><?php endif; ?>
    </div>
</section>
