<?php $headLead = 'Plans are saved in this browser and backed up to our server. Download a plan (.json) to keep your own copy or move it to another browser.'; require ROOT_DIR . '/views/app/_head.php'; ?>
<div class="page-body">
    <div class="a-container">
        <div class="form-actions" style="margin:0 0 26px;justify-content:center">
            <a class="btn" href="<?= e(url('/app/editor')) ?>"><?= icon('plus') ?> New plan</a>
            <label class="btn btn-outline" style="cursor:pointer"><?= icon('upload') ?> Import plan (.json)
                <input type="file" id="plan-import" accept="application/json,.json" class="visually-hidden">
            </label>
        </div>
        <div class="sync-status" id="sync-status" role="status">
            <?= icon('upload') ?><span data-sync-label>Checking backups…</span>
            <button type="button" class="btn btn-outline" data-sync-now>Sync now</button>
        </div>
        <div class="grid grid-3" data-plans data-actions data-empty="#plans-empty"></div>
        <div class="empty" id="plans-empty" hidden><p>No saved plans yet.</p></div>
        <noscript><p class="empty">Turn on JavaScript to see and edit your saved plans.</p></noscript>
        <div class="notice"><?= icon('download') ?><div><strong>Export:</strong> open a plan and use <em>Export</em> in the editor to download it as a PNG image or PDF.</div></div>
    </div>
</div>
