<?php /** @var string $mode */
$headLead = $mode === 'generator'
    ? 'Describe a building and get a complete floor plan layout.'
    : 'Upload a photo of a floor plan and detect its rooms.';
require ROOT_DIR . '/views/app/_head.php';
$route = $mode === 'generator' ? 'ai-floor-plan-creator' : 'ai-floor-plan-input'; ?>
<div class="page-body">
    <div class="a-container" style="max-width:760px;text-align:center">
        <span class="ring" style="margin:0 auto 18px"><?= icon($mode === 'generator' ? 'sparkles' : 'image') ?></span>
        <h2 style="font-size:2rem"><?= $mode === 'generator' ? 'AI Floor Plans' : 'AI Analyzer' ?> is moving to the new web app</h2>
        <p>The AI tools are being rebuilt for this PHP version (phase 3 of the rewrite). Until then they are fully available in the classic web app, which uses the same secure AI service.</p>
        <div class="form-actions" style="justify-content:center">
            <a class="btn" href="<?= e(url('/classic/#/' . $route)) ?>">Open in the classic app</a>
            <a class="btn btn-outline" href="<?= e(url('/app/editor')) ?>">Draw a plan instead</a>
        </div>
    </div>
</div>
