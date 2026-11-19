<?php
declare(strict_types=1);

/**
 * Web app accounts: sign in with Google, stay signed in, sign out.
 *
 * The PHP session holds the signed-in user id. A "keep me signed in" cookie (fpd_user, 60 days,
 * HttpOnly) carries a random token; only its SHA-256 hash is stored in user_sessions, so a copy
 * of the database cannot be used to sign in. Admin accounts are separate (admin/src/Auth.php).
 */
final class UserAuth
{
    private const COOKIE = 'fpd_user';
    private const REMEMBER_DAYS = 60;
    private static ?array $user = null;
    private static bool $loaded = false;

    /** The signed-in web app user, or null. */
    public static function current(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = (int) ($_SESSION['web_user'] ?? 0);
        if ($id === 0 && ($token = (string) ($_COOKIE[self::COOKIE] ?? '')) !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
            $st = Database::pdo()->prepare("SELECT user_id FROM user_sessions WHERE token_hash = :h AND expires_at > datetime('now')");
            $st->execute(['h' => hash('sha256', $token)]);
            $id = (int) $st->fetchColumn();
            if ($id > 0 && session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['web_user'] = $id;
            }
        }
        if ($id > 0) {
            $st = Database::pdo()->prepare('SELECT id, email, name, picture FROM users WHERE id = :id');
            $st->execute(['id' => $id]);
            self::$user = $st->fetch() ?: null;
            if (self::$user === null) {
                unset($_SESSION['web_user']); // account was deleted by an admin
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $u = self::current();
        return $u ? (int) $u['id'] : null;
    }

    /** Create or update the account for verified Google claims and sign it in. */
    public static function loginWithGoogle(array $claims): array
    {
        $pdo = Database::pdo();
        $email = mb_substr((string) $claims['email'], 0, 190);
        $name = mb_substr(trim((string) ($claims['name'] ?? '')), 0, 120);
        $picture = (string) ($claims['picture'] ?? '');
        if (!preg_match('#^https://[a-z0-9.-]+\.googleusercontent\.com/#i', $picture)) {
            $picture = '';
        }
        $pdo->prepare(
            "INSERT INTO users (google_sub, email, name, picture, last_login_at) VALUES (:s, :e, :n, :p, datetime('now'))
             ON CONFLICT(google_sub) DO UPDATE SET email = excluded.email, name = excluded.name, picture = excluded.picture, last_login_at = datetime('now')"
        )->execute(['s' => (string) $claims['sub'], 'e' => $email, 'n' => $name, 'p' => $picture]);
        $st = $pdo->prepare('SELECT id FROM users WHERE google_sub = :s');
        $st->execute(['s' => (string) $claims['sub']]);
        $id = (int) $st->fetchColumn();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['web_user'] = $id;
        }
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("INSERT INTO user_sessions (user_id, token_hash, expires_at) VALUES (:u, :h, datetime('now', :d))")
            ->execute(['u' => $id, 'h' => hash('sha256', $token), 'd' => '+' . self::REMEMBER_DAYS . ' days']);
        self::setCookie($token, time() + self::REMEMBER_DAYS * 86400);
        if (random_int(1, 20) === 1) {
            $pdo->exec("DELETE FROM user_sessions WHERE expires_at < datetime('now')");
        }
        self::$loaded = false;
        return self::current() ?? [];
    }

    public static function logout(): void
    {
        $token = (string) ($_COOKIE[self::COOKIE] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            Database::pdo()->prepare('DELETE FROM user_sessions WHERE token_hash = :h')->execute(['h' => hash('sha256', $token)]);
        }
        unset($_SESSION['web_user']);
        self::setCookie('', time() - 3600);
        self::$user = null;
        self::$loaded = true;
    }

    /** What the browser may know about the user. */
    public static function publicUser(?array $u): ?array
    {
        return $u ? ['id' => (int) $u['id'], 'email' => $u['email'], 'name' => $u['name'], 'picture' => $u['picture']] : null;
    }

    private static function setCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        setcookie(self::COOKIE, $value, [
            'expires' => $expires, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
}
