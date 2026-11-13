<?php /** @var array $message */ ?>
<p><a href="<?= e(url('/admin/index.php')) ?>">← Back to messages</a></p>

<article class="card">
    <h1><?= e($message['subject']) ?></h1>
    <dl class="meta">
        <dt>From</dt><dd><?= e($message['name']) ?> &lt;<a href="mailto:<?= e($message['email']) ?>?subject=<?= rawurlencode('Re: ' . $message['subject']) ?>"><?= e($message['email']) ?></a>&gt;</dd>
        <dt>Received</dt><dd><?= e(fmt_date($message['created_at'])) ?></dd>
        <dt>IP</dt><dd><?= e($message['ip']) ?></dd>
        <dt>Browser</dt><dd class="small muted"><?= e($message['user_agent']) ?></dd>
    </dl>
    <div class="message-body"><?= nl2br(e($message['message'])) ?></div>

    <div class="cta-row">
        <a class="btn" href="mailto:<?= e($message['email']) ?>?subject=<?= rawurlencode('Re: ' . $message['subject']) ?>">Reply by email</a>
        <form method="post" action="<?= e(url('/admin/index.php')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $message['id'] ?>">
            <button class="btn btn-ghost" name="action" value="unread">Mark unread</button>
            <button class="btn btn-danger" name="action" value="delete" data-confirm="Delete this message permanently?">Delete</button>
        </form>
    </div>
</article>
