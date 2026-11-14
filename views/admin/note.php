<?php /** @var array $row @var int $devicePlans @var int $deviceNotes */ ?>
<div class="ad-actions ad-mb">
    <a class="ad-btn" href="<?= e(url('/admin/notes')) ?>"><?= icon('chevron-left') ?> All notes</a>
    <span style="flex:1"></span>
    <form method="post" action="<?= e(url('/admin/notes/' . $row['id'])) ?>">
        <?= csrf_field() ?>
        <button class="ad-btn ad-btn-danger" name="action" value="delete" data-confirm="Delete this note from the server?"><?= icon('trash') ?> Delete</button>
    </form>
</div>
<div class="ad-grid ad-grid-main">
    <section class="ad-card">
        <div class="ad-card-head"><h2><?= e($row['title'] !== '' ? $row['title'] : 'Untitled note') ?></h2><?php if ($row['category'] !== ''): ?><span class="ad-pill"><?= e($row['category']) ?></span><?php endif; ?></div>
        <div class="ad-card-body"><pre class="ad-msg-body"><?= e($row['content'] !== '' ? $row['content'] : '(No text)') ?></pre></div>
    </section>
    <section class="ad-card">
        <div class="ad-card-head"><h2>Details</h2></div>
        <div class="ad-card-body">
            <dl class="ad-msg-meta">
                <dt>Source</dt><dd><?= e(PlanStore::SOURCES[$row['source']] ?? $row['source']) ?></dd>
                <dt>Last edited</dt><dd><?= e($row['note_time'] !== '' ? fmt_date($row['note_time']) : '—') ?></dd>
                <dt>Backed up</dt><dd><?= e(fmt_date($row['synced_at'])) ?></dd>
                <dt>Length</dt><dd><?= number_format(mb_strlen((string) $row['content'])) ?> characters</dd>
                <dt>Device</dt><dd><code><?= e(substr((string) $row['owner'], 0, 10)) ?>…</code><br>
                    <a href="<?= e(url('/admin/notes?device=' . $row['owner'])) ?>"><?= $deviceNotes ?> note<?= $deviceNotes === 1 ? '' : 's' ?></a>
                    <?php if ($devicePlans > 0): ?> · <?= $devicePlans ?> saved plan<?= $devicePlans === 1 ? '' : 's' ?><?php endif; ?></dd>
            </dl>
        </div>
    </section>
</div>
