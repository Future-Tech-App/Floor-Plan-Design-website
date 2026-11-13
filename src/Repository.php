<?php
declare(strict_types=1);

/**
 * All database access lives here. Every query uses prepared statements.
 */
final class Repository
{
    /* ---------- Settings (key/value) ---------- */

    public static function setting(string $key, ?string $default = null): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }

    /** @return array<string, string> */
    public static function allSettings(): array
    {
        $rows = Database::pdo()->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
        return $out;
    }

    public static function setSetting(string $key, string $value): void
    {
        $stmt = Database::pdo()->prepare(
            "INSERT INTO app_settings (setting_key, setting_value, updated_at)
             VALUES (?, ?, datetime('now'))
             ON CONFLICT(setting_key) DO UPDATE SET
                setting_value = excluded.setting_value,
                updated_at    = excluded.updated_at"
        );
        $stmt->execute([$key, $value]);
    }

    /* ---------- Contact messages ---------- */

    /** @param array{name:string,email:string,subject:string,message:string} $data */
    public static function createMessage(array $data, string $ip, string $userAgent): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO contact_messages (name, email, subject, message, ip, user_agent)
             VALUES (:name, :email, :subject, :message, :ip, :ua)'
        );
        $stmt->execute([
            ':name'    => $data['name'],
            ':email'   => $data['email'],
            ':subject' => $data['subject'],
            ':message' => $data['message'],
            ':ip'      => $ip,
            ':ua'      => mb_substr($userAgent, 0, 255),
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Number of messages from an IP inside the rate-limit window. */
    public static function recentMessageCount(string $ip, int $windowMinutes): int
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM contact_messages
             WHERE ip = ? AND created_at >= datetime('now', ?)"
        );
        $stmt->execute([$ip, '-' . $windowMinutes . ' minutes']);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int} */
    public static function listMessages(int $page, int $perPage, string $filter = 'all'): array
    {
        $where = match ($filter) {
            'unread' => 'WHERE is_read = 0',
            'read'   => 'WHERE is_read = 1',
            default  => '',
        };
        $pdo = Database::pdo();
        $total = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages $where")->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM contact_messages $where ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, ($page - 1) * $perPage), PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function findMessage(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM contact_messages WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function setMessageRead(int $id, bool $read): void
    {
        $stmt = Database::pdo()->prepare('UPDATE contact_messages SET is_read = ? WHERE id = ?');
        $stmt->execute([$read ? 1 : 0, $id]);
    }

    public static function deleteMessage(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM contact_messages WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function unreadCount(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
    }

    /* ---------- Admin users ---------- */

    public static function findAdmin(string $username): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function adminCount(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    }

    public static function saveAdmin(string $username, string $passwordHash): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO admin_users (username, password_hash) VALUES (?, ?)
             ON CONFLICT(username) DO UPDATE SET password_hash = excluded.password_hash'
        );
        $stmt->execute([$username, $passwordHash]);
    }
}
