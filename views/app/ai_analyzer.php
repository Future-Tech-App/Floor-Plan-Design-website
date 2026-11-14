<?php /** AI floor plan analyzer. The browser shrinks the photo and posts it to /api/v1/analyze-plan.php. */
$headLead = 'Upload a photo or scan of a floor plan. AI finds the rooms, measures them and turns the drawing into an editable plan.';
require ROOT_DIR . '/views/app/_head.php';
?>
<div class="page-body" data-ai-analyzer data-endpoint="<?= e(url('/api/v1/analyze-plan.php')) ?>">
    <div class="a-container">
        <div class="calc-layout ai-layout">
            <div class="panel">
                <h2>Your floor plan image</h2>
                <label class="ai-drop" id="ai-drop" for="ai-file">
                    <span id="ai-drop-empty"><?= icon('upload') ?><br><strong>Choose an image</strong> or drop it here<br><small>JPG, PNG or WebP · a clear, straight-on photo works best</small></span>
                    <img id="ai-preview" alt="Selected floor plan" hidden>
                </label>
                <input type="file" id="ai-file" accept="image/jpeg,image/png,image/webp" class="visually-hidden">
                <div class="form-actions">
                    <button type="button" class="btn" id="ai-analyze" disabled><?= icon('sparkles') ?> Analyze Floor Plan</button>
                    <button type="button" class="btn btn-outline" id="ai-clear" hidden>Choose another image</button>
                </div>
                <p class="ai-status" id="ai-status" role="status" aria-live="polite" hidden></p>
            </div>
            <aside class="panel ai-tips">
                <h2>What you get</h2>
                <ul>
                    <li>Every room detected and labelled on your image.</li>
                    <li>Room sizes and areas, plus the total area.</li>
                    <li>Counts of bedrooms, bathrooms and other spaces.</li>
                    <li>An editable plan you can open in the editor or view in 3D.</li>
                </ul>
                <p class="hint" style="margin-top:14px">Your image is sent to our server and our AI provider only to analyse it. It is not stored.</p>
            </aside>
        </div>

        <section id="ai-results" hidden style="margin-top:46px">
            <span class="label">Analysis</span>
            <div class="calc-layout">
                <div class="panel" style="text-align:center"><div class="ai-annot" id="ai-annot"></div></div>
                <div class="results" id="ai-summary-box"></div>
            </div>
            <div class="panel" style="margin-top:22px">
                <h2>Rooms</h2>
                <div class="ai-table-wrap" id="ai-rooms"></div>
            </div>
            <div class="form-actions" style="justify-content:center;margin-top:30px">
                <button type="button" class="btn" id="ai-open"><?= icon('pencil') ?> Open in Editor</button>
                <button type="button" class="btn btn-outline" id="ai-3d"><?= icon('cube') ?> 3D View</button>
            </div>
        </section>
    </div>
</div>
<script src="<?= e(asset('/assets/app/ai.js')) ?>" defer></script>
