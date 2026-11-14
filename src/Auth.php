<?php
declare(strict_types=1);

/**
 * Admin authentication (session based).
 */
final class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $admin = Repository::findAdmin($username);
        // Always run password_verify to keep timing similar for unknown users.
        $hash = $admin['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidin';
        $ok = password_verify($password, $hash) && $admin !== null;

        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['admin'] = [
                'id'         => (int) $admin['id'],
                'username'   => $admin['username'],
                'last_seen'  => time(),
            ];
        }
        return $ok;
    }

    public static function check(): bool
    {
        if (empty($_SESSION['admin'])) {
            return false;
        }
        $minutes = (int) config('admin_session_minutes', 120);
        if (time() - (int) $_SESSION['admin']['last_seen'] > $minutes * 60) {
            self::logout();
            return false;
        }
        // End the session if the account was removed (or renamed) since sign-in.
        $admin = Repository::findAdmin((string) $_SESSION['admin']['username']);
        if ($admin === null || (int) $admin['id'] !== (int) $_SESSION['admin']['id']) {
            self::logout();
            return false;
        }
        $_SESSION['admin']['last_seen'] = time();
        return true;
    }

    /** Require a logged-in admin or redirect to the login page. */
    public static function require(): void
    {
        if (!self::check()) {
            flash('error', 'Please sign in to continue.');
            redirect('/admin/login');
        }
    }

    public static function id(): int
    {
        return (int) ($_SESSION['admin']['id'] ?? 0);
    }

    public static function user(): ?string
    {
        return $_SESSION['admin']['username'] ?? null;
    }

    public static function logout(): void
    {
        unset($_SESSION['admin']);
        session_regenerate_id(true);
    }
}
