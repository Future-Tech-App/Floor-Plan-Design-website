<?php
declare(strict_types=1);

/**
 * Queries used only by the admin panel (stats, analytics, AI usage, admins, logs).
 */
final class AdminRepo
{
    private static function q(string $sql, array $params = []): PDOStatement
    {
        $st = Database::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    private static function scalar(string $sql, array $params = []): int
    {
        return (int) self::q($sql, $params)->fetchColumn();
    }

    /* ---------- Dashboard ---------- */

    public static function messageCounts(): array
    {
        return [
            'total'  => self::scalar('SELECT COUNT(*) FROM contact_messages'),
            'unread' => self::scalar('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0'),
            'week'   => self::scalar("SELECT COUNT(*) FROM contact_messages WHERE created_at >= datetime('now', '-7 days')"),
        ];
    }

    public static function latestMessages(int $limit = 5): array
    {
        return self::q('SELECT id, name, email, subject, is_read, created_at FROM contact_messages ORDER BY id DESC LIMIT ' . max(1, $limit))->fetchAll();
    }

    /* ---------- Page views ---------- */

    /** Views per day for the last $days days (oldest first, missing days filled with 0). */
    public static function viewsByDay(int $days, string $prefix = ''): array
    {
        $rows = self::q(
            "SELECT day, SUM(views) v FROM page_views WHERE day >= date('now', :from)" . ($prefix !== '' ? ' AND path LIKE :p' : '') . ' GROUP BY day',
            ['from' => '-' . ($days - 1) . ' days'] + ($prefix !== '' ? ['p' => $prefix . '%'] : [])
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        return self::fillDays($rows, $days);
    }

    public static function viewsTotal(int $fromDaysAgo, int $toDaysAgo = 0): int
    {
        return self::scalar(
            "SELECT COALESCE(SUM(views), 0) FROM page_views WHERE day >= date('now', :a) AND day <= date('now', :b)",
            ['a' => '-' . $fromDaysAgo . ' days', 'b' => '-' . $toDaysAgo . ' days']
        );
    }

    public static function topPages(int $days, int $limit = 10): array
    {
        return self::q(
            "SELECT path, SUM(views) views FROM page_views WHERE day >= date('now', :from) GROUP BY path ORDER BY views DESC LIMIT " . max(1, $limit),
            ['from' => '-' . ($days - 1) . ' days']
        )->fetchAll();
    }

    /** Split of views between the website and the web app. */
    public static function viewsSplit(int $days): array
    {
        $row = self::q(
            "SELECT COALESCE(SUM(CASE WHEN path LIKE '/app%' THEN views END), 0) app,
                    COALESCE(SUM(CASE WHEN path NOT LIKE '/app%' THEN views END), 0) site
             FROM page_views WHERE day >= date('now', :from)",
            ['from' => '-' . ($days - 1) . ' days']
        )->fetch();
        return ['app' => (int) $row['app'], 'site' => (int) $row['site']];
    }

    /* ---------- AI usage ---------- */

    /** Requests per day: [day => ['ok' => n, 'err' => n]]. */
    public static function aiByDay(int $days): array
    {
        $rows = self::q(
            "SELECT date(created_at) d, SUM(status = 200) ok, SUM(status <> 200) err FROM ai_requests
             WHERE created_at >= date('now', :from) GROUP BY d",
            ['from' => '-' . ($days - 1) . ' days']
        )->fetchAll();
        $map = [];
        foreach ($rows as $r) {
            $map[$r['d']] = ['ok' => (int) $r['ok'], 'err' => (int) $r['err']];
        }
        $out = [];
        foreach (self::dayKeys($days) as $d) {
            $out[$d] = $map[$d] ?? ['ok' => 0, 'err' => 0];
        }
        return $out;
    }

    public static function aiTotals(int $days): array
    {
        $r = self::q(
            "SELECT COUNT(*) total, COALESCE(SUM(status = 200), 0) ok FROM ai_requests WHERE created_at >= datetime('now', :from)",
            ['from' => '-' . $days . ' days']
        )->fetch();
        $total = (int) $r['total'];
        return ['total' => $total, 'ok' => (int) $r['ok'], 'err' => $total - (int) $r['ok'], 'rate' => $total ? round((int) $r['ok'] / $total * 100) : 100];
    }

    public static function aiStatuses(int $days): array
    {
        return self::q(
            "SELECT status, COUNT(*) n FROM ai_requests WHERE created_at >= datetime('now', :from) GROUP BY status ORDER BY n DESC",
            ['from' => '-' . $days . ' days']
        )->fetchAll();
    }

    /** Busiest IPs, with the last part hidden. */
    public static function aiTopIps(int $days, int $limit = 8): array
    {
        $rows = self::q(
            "SELECT ip, COUNT(*) n, MAX(created_at) last FROM ai_requests WHERE created_at >= datetime('now', :from) GROUP BY ip ORDER BY n DESC LIMIT " . max(1, $limit),
            ['from' => '-' . $days . ' days']
        )->fetchAll();
        foreach ($rows as &$r) {
            $r['ip'] = self::maskIp((string) $r['ip']);
        }
        return $rows;
    }

    public static function aiPurge(int $olderThanDays): int
    {
        return self::q("DELETE FROM ai_requests WHERE created_at < datetime('now', :d)", ['d' => '-' . $olderThanDays . ' days'])->rowCount();
    }

    /* ---------- Messages ---------- */

    public static function searchMessages(int $page, int $perPage, string $filter, string $search): array
    {
        $where = [];
        $params = [];
        if ($filter === 'unread') {
            $where[] = 'is_read = 0';
        } elseif ($filter === 'read') {
            $where[] = 'is_read = 1';
        }
        if ($search !== '') {
            $where[] = '(name LIKE :s OR email LIKE :s OR subject LIKE :s OR message LIKE :s)';
            $params['s'] = '%' . $search . '%';
        }
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = self::scalar('SELECT COUNT(*) FROM contact_messages' . $sqlWhere, $params);
        $items = self::q(
            'SELECT * FROM contact_messages' . $sqlWhere . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        )->fetchAll();
        return ['total' => $total, 'items' => $items];
    }

    public static function allMessages(): array
    {
        return self::q('SELECT id, created_at, name, email, subject, message, is_read FROM contact_messages ORDER BY id DESC')->fetchAll();
    }

    /** Previous and next message ids (by id). */
    public static function neighbours(int $id): array
    {
        return [
            'newer' => self::scalar('SELECT id FROM contact_messages WHERE id > :id ORDER BY id ASC LIMIT 1', ['id' => $id]),
            'older' => self::scalar('SELECT id FROM contact_messages WHERE id < :id ORDER BY id DESC LIMIT 1', ['id' => $id]),
        ];
    }

    /* ---------- Admin users ---------- */

    public static function admins(): array
    {
        return self::q('SELECT id, username, created_at, last_login_at FROM admin_users ORDER BY id')->fetchAll();
    }

    public static function adminById(int $id): ?array
    {
        $r = self::q('SELECT * FROM admin_users WHERE id = :id', ['id' => $id])->fetch();
        return $r ?: null;
    }

    public static function deleteAdmin(int $id): void
    {
        self::q('DELETE FROM admin_users WHERE id = :id', ['id' => $id]);
    }

    public static function setPassword(int $id, string $hash): void
    {
        self::q('UPDATE admin_users SET password_hash = :h WHERE id = :id', ['h' => $hash, 'id' => $id]);
    }

    public static function touchLogin(int $id): void
    {
        self::q("UPDATE admin_users SET last_login_at = datetime('now') WHERE id = :id", ['id' => $id]);
    }

    /* ---------- Sign-in throttling ---------- */

    public static function failedLogins(string $ip, int $minutes): int
    {
        return self::scalar(
            "SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND created_at >= datetime('now', :m)",
            ['ip' => $ip, 'm' => '-' . $minutes . ' minutes']
        );
    }

    public static function recordFailedLogin(string $ip, string $username): void
    {
        self::q('INSERT INTO login_attempts (ip, username) VALUES (:ip, :u)', ['ip' => $ip, 'u' => substr($username, 0, 60)]);
        self::q("DELETE FROM login_attempts WHERE created_at < datetime('now', '-1 day')");
    }

    public static function clearFailedLogins(string $ip): void
    {
        self::q('DELETE FROM login_attempts WHERE ip = :ip', ['ip' => $ip]);
    }

    /* ---------- Activity log ---------- */

    public static function log(string $action, string $details = ''): void
    {
        self::q(
            'INSERT INTO admin_log (admin, action, details, ip) VALUES (:a, :ac, :d, :ip)',
            ['a' => Auth::user() ?? '-', 'ac' => $action, 'd' => substr($details, 0, 500), 'ip' => client_ip()]
        );
    }

    public static function activity(int $page, int $perPage): array
    {
        return [
            'total' => self::scalar('SELECT COUNT(*) FROM admin_log'),
            'items' => self::q('SELECT * FROM admin_log ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage))->fetchAll(),
        ];
    }

    /* ---------- Helpers ---------- */

    public static function dayKeys(int $days): array
    {
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $out[] = gmdate('Y-m-d', strtotime('-' . $i . ' days'));
        }
        return $out;
    }

    private static function fillDays(array $map, int $days): array
    {
        $out = [];
        foreach (self::dayKeys($days) as $d) {
            $out[$d] = (int) ($map[$d] ?? 0);
        }
        return $out;
    }

    public static function maskIp(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 3)) . ':…';
        }
        return preg_replace('/\.\d+$/', '.x', $ip) ?? $ip;
    }

    public static function dbSize(): int
    {
        $path = (string) config('db_path');
        return is_file($path) ? (int) filesize($path) : 0;
    }
}
