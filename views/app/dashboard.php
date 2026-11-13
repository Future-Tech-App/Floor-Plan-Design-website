<?php /** @var array $tools */
$slides = [
    ['gallery/3bed-2.jpg', 'Floor Plan Design App', 'Design Your Dream Home<br>Room By Room', 'Draw rooms, add doors, windows and furniture, and export a PDF — all in your browser.'],
    ['gallery/2bed-2.jpg', 'Measure & Calculate', 'Plan Your Budget<br>With Confidence', 'Estimate bricks, concrete, paint and tiles with the construction calculator.'],
    ['gallery/1bed-1.jpg', 'Print-Ready Plans', 'Professional Plans<br>In Minutes', 'Export high-resolution PNG and PDF plans with measurements to share with anyone.'],
];
$templateCards = [
    ['01.', 'One Bedroom', 'templates/1bed-2.jpg'],
    ['02.', 'Two Bedroom', 'templates/2bed-1.jpg'],
    ['03.', 'Three Bedroom', 'templates/3bed-1.jpg'],
];
$icon = static fn (string $n): string => icon($n === 'note' ? 'note' : $n);
?>
<section class="hero" aria-roledescription="carousel" aria-label="Highlights">
    <?php foreach ($slides as $i => [$bg, $kicker, $titleHtml, $text]): ?>
        <div class="hero-slide<?= $i === 0 ? ' is-active' : '' ?>" style="background-image:url('<?= e(url('/assets/img/' . $bg)) ?>')" aria-hidden="<?= $i ? 'true' : 'false' ?>">
            <div class="hero-content">
                <span class="kicker kicker-light"><?= e($kicker) ?></span>
                <span class="rule"></span>
                <?php if ($i === 0): ?><h1><?= $titleHtml ?></h1><?php else: ?><h2><?= $titleHtml ?></h2><?php endif; ?>
                <p><?= e($text) ?></p>
                <div class="hero-actions">
                    <a class="btn" href="<?= e(url('/app/editor')) ?>"<?= $i ? ' tabindex="-1"' : '' ?>>Start Designing</a>
                    <a class="btn btn-ghost-light" href="<?= e(url('/app/templates')) ?>"<?= $i ? ' tabindex="-1"' : '' ?>>Browse Templates</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="hero-dots">
        <?php foreach ($slides as $i => $s): ?><button type="button" class="<?= $i ? '' : 'is-active' ?>" aria-label="Show slide <?= $i + 1 ?>"></button><?php endforeach; ?>
    </div>
</section>

<div class="framed">
    <div class="a-container">
        <span class="label">Your Plans</span>
        <div class="grid grid-3" data-plans data-max="6" data-empty="#plans-empty" data-more="#plans-more"></div>
        <div class="empty" id="plans-empty">
            <p>No saved plans yet. Your plans will appear here.</p>
            <a class="btn" href="<?= e(url('/app/editor')) ?>"><?= icon('plus') ?> New plan</a>
        </div>
        <p class="empty" id="plans-more" hidden style="margin-top:34px"><a class="btn btn-dark" href="<?= e(url('/app/plans')) ?>">View All Plans</a></p>
        <noscript><p class="empty">Turn on JavaScript to see and edit your saved plans.</p></noscript>
    </div>
</div>

<section class="sec">
    <div class="a-container">
        <h2 class="sec-title">Main Tools</h2>
        <span class="rule"></span>
        <p class="sec-sub">Everything you need to plan a space, from the first wall to a print-ready PDF</p>

        <span class="label">Design</span>
        <div class="grid grid-3">
            <?php foreach ($tools['design'] as [$key, $name, $desc, $ic, $href]): ?>
                <a class="tool-card" href="<?= e(url($href)) ?>">
                    <span class="ring"><?= $icon($ic) ?></span>
                    <h3><?= e($name) ?></h3>
                    <p><?= e($desc) ?></p>
                    <span class="link-arrow">Open →</span>
                </a>
            <?php endforeach; ?>
        </div>

        <span class="label" style="margin-top:46px">Measure &amp; Calculate</span>
        <div class="grid grid-3">
            <?php foreach ($tools['measure'] as [$key, $name, $desc, $ic, $href]): ?>
                <a class="tool-card" href="<?= e(url($href)) ?>">
                    <span class="ring"><?= $icon($ic) ?></span>
                    <h3><?= e($name) ?></h3>
                    <p><?= e($desc) ?></p>
                    <span class="link-arrow">Open →</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div class="framed" style="margin-bottom:80px">
    <div class="a-container">
        <span class="label">Plan Templates</span>
        <div class="grid grid-3">
            <?php foreach ($templateCards as [$num, $name, $img]): ?>
                <a class="img-card" href="<?= e(url('/app/templates')) ?>">
                    <img src="<?= e(url('/assets/app/' . $img)) ?>" alt="<?= e($name) ?> template" loading="lazy">
                    <span class="ic-info"><span class="ic-num"><?= e($num) ?></span><h3><?= e($name) ?></h3><span class="ic-more">View Templates</span></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
