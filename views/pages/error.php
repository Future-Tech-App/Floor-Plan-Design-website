<?php /** @var int $status  @var string $message */ ?>
<section class="page-hero">
    <div class="container center">
        <h1><?= (int) $status ?></h1>
        <p class="lead"><?= e($message ?: 'Something went wrong.') ?></p>
        <a class="btn" href="<?= e(url('/')) ?>">Back to home</a>
    </div>
</section>
