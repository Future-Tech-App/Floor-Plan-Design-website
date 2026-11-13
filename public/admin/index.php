<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::require();

// Actions from the list (mark read/unread, delete)
if (is_post()) {
    csrf_verify();
    $action = input('action');
    $id = (int) ($_POST['id'] ?? 0);
    $message = $id > 0 ? Repository::findMessage($id) : null;

    if ($message === null) {
        flash('error', 'That message no longer exists.');
    } elseif ($action === 'read') {
        Repository::setMessageRead($id, true);
        flash('success', 'Marked as read.');
    } elseif ($action === 'unread') {
        Repository::setMessageRead($id, false);
        flash('success', 'Marked as unread.');
    } elseif ($action === 'delete') {
        Repository::deleteMessage($id);
        flash('success', 'Message deleted.');
    }
    redirect('/admin/index.php?filter=' . urlencode(input('filter', 'get') ?: 'all'));
}

$filter = input('filter', 'get');
if (!in_array($filter, ['all', 'unread', 'read'], true)) {
    $filter = 'all';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$list = Repository::listMessages($page, $perPage, $filter);
$pages = max(1, (int) ceil($list['total'] / $perPage));

render('admin/messages', [
    'title'    => 'Messages — Admin',
    'filter'   => $filter,
    'page'     => $page,
    'pages'    => $pages,
    'total'    => $list['total'],
    'messages' => $list['items'],
    'unread'   => Repository::unreadCount(),
], 'admin');
