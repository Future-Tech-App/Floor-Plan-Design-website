<?php
declare(strict_types=1);

/**
 * One-time, browser-based creation of the first admin account (for hosts without SSH).
 *
 * Only works while:
 *   - no admin account exists yet, AND
 *   - config/setup_token.txt exists and the ?token= in the URL matches it.
 *
 * bin/build-deploy.sh writes a random token into that file and prints the setup URL.
 * After the first admin is created this page is permanently disabled; delete
 * config/setup_token.txt afterwards for good measure.
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';

$tokenFile = ROOT_DIR . '/config/setup_token.txt';
$expected = is_file($tokenFile) ? trim((string) file_get_contents($tokenFile)) : '';
$token = is_post() ? input('token') : input('token', 'get');

if (Repository::adminCount() > 0 || strlen($expected) < 32 || !hash_equals($expected, $token)) {
    abort(404, 'The page you are looking for does not exist.');
}

$errors = [];
$username = '';

if (is_post()) {
    csrf_verify();
    $username = input('username');
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $errors['username'] = 'Use 3–50 letters, digits, dots, dashes or underscores.';
    }
    if (strlen($password) < 10) {
        $errors['password'] = 'Password must be at least 10 characters.';
    } elseif (!hash_equals($password, $confirm)) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    if ($errors === []) {
        Repository::saveAdmin($username, password_hash($password, PASSWORD_DEFAULT));
        @unlink($tokenFile); // best effort; the page is disabled anyway once an admin exists
        flash('success', 'Admin account created. Please sign in.');
        redirect('/admin/login.php');
    }
}

render('admin/setup', [
    'title'    => 'Create admin account',
    'token'    => $token,
    'username' => $username,
    'errors'   => $errors,
], 'admin');
