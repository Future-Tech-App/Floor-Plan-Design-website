<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::require();

$id = (int) ($_GET['id'] ?? 0);
$message = $id > 0 ? Repository::findMessage($id) : null;
if ($message === null) {
    abort(404, 'Message not found.');
}

// Opening a message marks it as read
if (!(int) $message['is_read']) {
    Repository::setMessageRead($id, true);
    $message['is_read'] = 1;
}

render('admin/message', [
    'title'   => 'Message #' . $id . ' — Admin',
    'message' => $message,
], 'admin');
