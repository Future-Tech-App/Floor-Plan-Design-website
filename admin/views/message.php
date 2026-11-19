<?php /** @var array $msg @var array $nav2 */
$reply = 'mailto:' . rawurlencode($msg['email']) . '?subject=' . rawurlencode('Re: ' . $msg['subject'] . ' — Floor Plan Designer');
?>
<div class="ad-actions ad-mb">
    <a class="ad-btn" href="<?= e(url('/admin/messages')) ?>"><?= icon('chevron-left') ?> All messages</a>
    <span style="flex:1"></span>
    <?php if ($nav2['newer']): ?><a class="ad-btn" href="<?= e(url('/admin/messages/' . $nav2['newer'])) ?>">← Newer</a><?php endif; ?>
    <?php if ($nav2['older']): ?><a class="ad-btn" href="<?= e(url('/admin/messages/' . $nav2['older'])) ?>">Older →</a><?php endif; ?>
</div>
<div class="ad-grid ad-grid-main">
    <section class="ad-card">
        <div class="ad-card-head"><h2><?= e($msg['subject']) ?></h2><span class="ad-muted"><?= e(fmt_date($msg['created_at'])) ?></span></div>
        <div class="ad-card-body"><pre class="ad-msg-body"><?= e($msg['message']) ?></pre></div>
        <div class="ad-form-foot">
            <a class="ad-btn ad-btn-primary" href="<?= e($reply) ?>"><?= icon('mail') ?> Reply by email</a>
            <form method="post" action="<?= e(url('/admin/messages/' . $msg['id'])) ?>" class="ad-actions" style="margin-left:auto">
                <?= csrf_field() ?>
                <button class="ad-btn" name="action" value="unread">Mark unread</button>
                <button class="ad-btn ad-btn-danger" name="action" value="delete" data-confirm="Delete this message? This cannot be undone."><?= icon('trash') ?> Delete</button>
            </form>
        </div>
    </section>
    <section class="ad-card">
        <div class="ad-card-head"><h2>Sender</h2></div>
        <div class="ad-card-body">
            <dl class="ad-msg-meta">
                <dt>Name</dt><dd><?= e($msg['name']) ?></dd>
                <dt>Email</dt><dd><a href="mailto:<?= e($msg['email']) ?>"><?= e($msg['email']) ?></a></dd>
                <dt>Topic</dt><dd><?= e($msg['subject']) ?></dd>
                <dt>Received</dt><dd><?= e(fmt_date($msg['created_at'])) ?> (UTC)</dd>
                <dt>IP</dt><dd><?= e(AdminRepo::maskIp((string) $msg['ip'])) ?></dd>
                <dt>Browser</dt><dd class="ad-muted" style="font-size:12.5px"><?= e(mb_strimwidth((string) $msg['user_agent'], 0, 160, '…')) ?></dd>
            </dl>
        </div>
    </section>
</div>
