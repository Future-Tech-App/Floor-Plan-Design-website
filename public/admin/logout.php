<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

if (is_post()) {
    csrf_verify();
    Auth::logout();
    flash('success', 'You have been signed out.');
}
redirect('/admin/login.php');
