<?php /** @var int $status  @var string $message */ ?>
<section class="page-banner">
    <div class="container page-banner-inner center">
        <p class="kicker kicker-light" style="justify-content:center">Error <?= (int) $status ?></p>
        <h1 style="font-size:clamp(4rem,12vw,7rem);margin-bottom:.1em"><?= (int) $status ?></h1>
        <p class="page-banner-lead" style="margin:0 auto 26px"><?= e($message ?: 'Something went wrong.') ?></p>
        <div class="cta-row" style="justify-content:center">
            <a class="btn btn-light" href="<?= e(url('/')) ?>">Back to home</a>
            <a class="btn btn-outline-light" href="<?= e(url('/app/')) ?>">Open the web app</a>
        </div>
    </div>
</section>
