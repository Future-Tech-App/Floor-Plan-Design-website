<?php /** AI floor plan generator. The browser posts the description to /api/v1/generate-plan.php (PHP builds the layout). */
$headLead = 'Describe a house or building in plain words. We design the rooms and walls, and you can open the result in the editor.';
require ROOT_DIR . '/views/app/_head.php';
$examples = [
    '5 Marla house with drawing room, 3 bedrooms, attached bathrooms, kitchen and car porch',
    '10 Marla 2 floor house with 4 bedrooms, TV lounge, dining room and lawn',
    '2 bedroom apartment with living room, kitchen and balcony',
    'Small clinic with reception, waiting area and 3 consultation rooms',
    '3 floor plaza. Ground floor: 6 shops, lobby, bathroom. 1st floor: 8 offices, reception. 2nd floor: 4 halls, kitchen',
];
?>
<div class="page-body" data-ai-generator data-endpoint="<?= e(url('/api/v1/generate-plan.php')) ?>">
    <div class="a-container">
        <div class="calc-layout ai-layout">
            <form class="panel" id="ai-form">
                <h2>Describe your plan</h2>
                <div class="field">
                    <label for="ai-desc">Description</label>
                    <textarea id="ai-desc" name="description" rows="6" maxlength="2000" required placeholder="e.g. 5 Marla house with drawing room, 3 bedrooms, attached bathrooms, kitchen and car porch"></textarea>
                    <p class="hint"><span id="ai-count">0</span> / 2000 · Mention the plot size (Marla, Kanal, sq ft or feet), floors, and the rooms you need.</p>
                </div>
                <span class="flabel" style="display:block;font:700 11px var(--sans);letter-spacing:2.5px;text-transform:uppercase;color:var(--ink);margin:18px 0 8px">Try an example</span>
                <div class="ai-chips">
                    <?php foreach ($examples as $ex): ?><button type="button" class="ai-chip" data-example="<?= e($ex) ?>"><?= e(mb_strimwidth($ex, 0, 60, '…')) ?></button><?php endforeach; ?>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn" id="ai-go"><?= icon('sparkles') ?> Generate Floor Plan</button>
                </div>
                <p class="ai-status" id="ai-status" role="status" aria-live="polite" hidden></p>
            </form>
            <aside class="panel ai-tips">
                <h2>Tips</h2>
                <ul>
                    <li>Give the plot size: <em>“5 Marla”</em>, <em>“1 Kanal”</em>, <em>“30 x 60 ft”</em>.</li>
                    <li>Say how many floors: <em>“2 floor house”</em>.</li>
                    <li>List rooms with counts: <em>“3 bedrooms with attached baths”</em>.</li>
                    <li>For buildings, describe each floor: <em>“Ground floor: 6 shops. 1st floor: 8 offices.”</em></li>
                    <li>Every floor opens in the editor as its own plan, ready to change and export.</li>
                </ul>
            </aside>
        </div>

        <section id="ai-results" hidden style="margin-top:46px">
            <span class="label">Your Floor Plan</span>
            <p class="ai-summary" id="ai-summary"></p>
            <div class="notice" id="ai-local" hidden><?= icon('help') ?><div>The AI service wasn't available, so this layout was made by our built-in layout engine. You can still edit it, or try again later.</div></div>
            <div class="grid grid-3" id="ai-floors" style="margin-top:22px"></div>
            <div class="form-actions" style="justify-content:center;margin-top:30px">
                <button type="button" class="btn" id="ai-save-all"><?= icon('download') ?> Save all floors to My Plans</button>
                <button type="button" class="btn btn-outline" id="ai-again">Generate another version</button>
            </div>
        </section>
    </div>
</div>
<script src="<?= e(url('/assets/app/ai.js')) ?>" defer></script>
