<?php
declare(strict_types=1);

/**
 * PDO connection (SQLite by default) plus automatic schema creation.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $path = (string) config('db_path');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');

        self::migrate($pdo);
        self::$pdo = $pdo;
        return $pdo;
    }

    /** Create tables if they do not exist. Safe to run on every request. */
    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS contact_messages (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL,
                email       TEXT    NOT NULL,
                subject     TEXT    NOT NULL,
                message     TEXT    NOT NULL,
                ip          TEXT    NOT NULL DEFAULT '',
                user_agent  TEXT    NOT NULL DEFAULT '',
                is_read     INTEGER NOT NULL DEFAULT 0,
                created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
            )
        SQL);

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_contact_ip_created ON contact_messages (ip, created_at)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS app_settings (
                setting_key   TEXT PRIMARY KEY,
                setting_value TEXT NOT NULL,
                updated_at    TEXT NOT NULL DEFAULT (datetime('now'))
            )
        SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS ai_requests (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                ip         TEXT NOT NULL,
                status     INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ai_ip_created ON ai_requests (ip, created_at)');

        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS admin_users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                username      TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at    TEXT NOT NULL DEFAULT (datetime('now'))
            )
        SQL);
        $cols = array_column($pdo->query('PRAGMA table_info(admin_users)')->fetchAll(), 'name');
        if (!in_array('last_login_at', $cols, true)) {
            $pdo->exec('ALTER TABLE admin_users ADD COLUMN last_login_at TEXT');
        }

        // Privacy-friendly page view counts: one row per day and path, no visitor data.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS page_views (
                day   TEXT NOT NULL,
                path  TEXT NOT NULL,
                views INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (day, path)
            )
        SQL);

        // What admins did in the panel.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS admin_log (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                admin      TEXT NOT NULL,
                action     TEXT NOT NULL,
                details    TEXT NOT NULL DEFAULT '',
                ip         TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        SQL);

        // Failed sign-in attempts, for throttling password guessing.
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS login_attempts (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                ip         TEXT NOT NULL,
                username   TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_login_ip_created ON login_attempts (ip, created_at)');
    }
}
