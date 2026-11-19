<?php
/**
 * Moving background for dark sections (hero, page banners, stats and CTA bands).
 * Layers, back to front: photo (slow zoom and pan, scroll and pointer parallax), colour shade,
 * 3D blueprint floor sliding toward the viewer, see-through 3D cubes turning, and soft lights.
 * Styles: "Live backgrounds" in public/assets/css/style.css; motion vars: main.js.
 *
 * @var string $liveImg   photo under public/assets/img/ ('' for none)
 * @var int    $liveCubes number of cubes (0–4)
 * @var bool   $liveFloor show the 3D grid floor
 */
$liveImg   = $liveImg ?? '';
$liveCubes = max(0, min(4, (int) ($liveCubes ?? 2)));
$liveFloor = $liveFloor ?? true;
?>
<div class="live-bg" aria-hidden="true">
    <?php if ($liveImg !== ''): ?>
        <div class="live-bg-img" style="background-image: url('<?= e(url('/assets/img/' . $liveImg)) ?>')"></div>
    <?php endif; ?>
    <div class="live-bg-shade"></div>
    <?php if ($liveFloor): ?><div class="live-bg-floor"></div><?php endif; ?>
    <?php if ($liveCubes > 0): ?>
        <div class="live-bg-cubes">
            <?php for ($i = 1; $i <= $liveCubes; $i++): ?>
                <span class="cube cube-<?= $i ?>"><i></i><i></i><i></i><i></i><i></i><i></i></span>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <span class="live-bg-light light-1"></span>
    <span class="live-bg-light light-2"></span>
</div>
