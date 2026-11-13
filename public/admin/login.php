<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

if (Auth::check()) {
    redirect('/admin/index.php');
}

if (is_post()) {
    csrf_verify();
    $username = input('username');
    $password = (string) ($_POST['password'] ?? '');

    if (Auth::attempt($username, $password)) {
        flash('success', 'Welcome back.');
        redirect('/admin/index.php');
    }
    usleep(400000); // slow down brute-force attempts
    flash('error', 'Invalid username or password.');
    redirect('/admin/login.php');
}

render('admin/login', ['title' => 'Admin sign in'], 'admin');
