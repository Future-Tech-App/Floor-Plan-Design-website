<?php
declare(strict_types=1);

/**
 * CLI: create or reset an admin account.
 *
 *   php bin/create-admin.php <username>            (prompts for the password)
 *
 * The password is never accepted as a command-line argument, so it does not
 * end up in shell history or the process list.
 */
if (PHP_SAPI !== 'cli') {
    exit("Run this script from the command line.\n");
}

define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/bootstrap.php';

$username = $argv[1] ?? '';
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    fwrite(STDERR, "Usage: php bin/create-admin.php <username>  (3-50 chars: letters, digits, _ . -)\n");
    exit(1);
}

// Read the password without echoing it (POSIX systems); fall back to plain read elsewhere.
echo 'Password (min 10 characters): ';
if (stripos(PHP_OS_FAMILY, 'Windows') === false) {
    shell_exec('stty -echo');
}
$password = trim((string) fgets(STDIN));
if (stripos(PHP_OS_FAMILY, 'Windows') === false) {
    shell_exec('stty echo');
}
echo "\n";

if (strlen($password) < 10) {
    fwrite(STDERR, "Password must be at least 10 characters.\n");
    exit(1);
}

Repository::saveAdmin($username, password_hash($password, PASSWORD_DEFAULT));
echo "Admin '{$username}' saved. Sign in at /admin/login\n";
