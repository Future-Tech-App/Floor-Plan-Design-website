<?php
declare(strict_types=1);

/**
 * Notes saved in the web app (/app/notes) and the Android/iOS apps.
 * Devices send their complete notes list; replaceForOwner() stores exactly that
 * list, so edits and deletions on the device are mirrored here.
 */
final class NoteStore
{
    public const MAX_NOTES = 500;            // per device
    public const MAX_NOTE_CHARS = 20000;     // per note

    private static function q(string $sql, array $params = []): PDOStatement
    {
        $st = Database::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /**
     * Replace a device's notes. Accepts web notes {title, content, updatedAt}
     * and app notes {title, content, category, timestamp}. Returns the count stored.
     */
    public static function replaceForOwner(string $owner, string $source, array $notes, ?int $userId = null): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::q('DELETE FROM notes WHERE owner = :o', ['o' => $owner]);
            $ins = $pdo->prepare('INSERT INTO notes (owner, source, title, content, category, note_time, user_id) VALUES (:o, :s, :t, :c, :cat, :nt, :u)');
            $n = 0;
            foreach (array_slice($notes, 0, self::MAX_NOTES) as $note) {
                if (!is_array($note)) {
                    continue;
                }
                $title = mb_substr(trim((string) ($note['title'] ?? '')), 0, 200);
                $content = mb_substr((string) ($note['content'] ?? ''), 0, self::MAX_NOTE_CHARS);
                if ($title === '' && trim($content) === '') {
                    continue;
                }
                $time = (string) ($note['updatedAt'] ?? $note['timestamp'] ?? $note['createdAt'] ?? '');
                $ins->execute([
                    'o' => $owner, 's' => $source, 't' => $title, 'c' => $content, 'u' => $userId,
                    'cat' => mb_substr((string) ($note['category'] ?? ''), 0, 60),
                    'nt' => preg_match('/^\d{4}-\d\d-\d\d[T ]\d\d:\d\d/', $time) ? substr(str_replace('T', ' ', $time), 0, 19) : '',
                ]);
                $n++;
            }
            $pdo->commit();
            return $n;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** The owner's notes for the web app to download. */
    public static function listForOwner(string $owner): array
    {
        return self::q(
            "SELECT title, content, note_time updatedAt FROM notes WHERE owner = :o ORDER BY id LIMIT " . self::MAX_NOTES,
            ['o' => $owner]
        )->fetchAll();
    }

    public static function search(int $page, int $perPage, string $source, string $q, string $owner = '', int $userId = 0): array
    {
        $where = [];
        $params = [];
        if ($owner !== '') {
            $where[] = 'n.owner = :own';
            $params['own'] = $owner;
        }
        if ($userId > 0) {
            $where[] = 'n.user_id = :uid';
            $params['uid'] = $userId;
        }
        if (isset(PlanStore::SOURCES[$source])) {
            $where[] = 'n.source = :src';
            $params['src'] = $source;
        }
        if ($q !== '') {
            $where[] = '(n.title LIKE :q OR n.content LIKE :q OR n.category LIKE :q OR u.email LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        $from = ' FROM notes n LEFT JOIN users u ON u.id = n.user_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        return [
            'total' => (int) self::q('SELECT COUNT(*)' . $from, $params)->fetchColumn(),
            'items' => self::q(
                "SELECT n.id, n.owner, n.source, n.title, substr(n.content, 1, 240) excerpt, length(n.content) chars, n.category, n.note_time, n.synced_at,
                        n.user_id, u.email user_email"
                . $from . " ORDER BY COALESCE(NULLIF(n.note_time, ''), n.synced_at) DESC, n.id DESC LIMIT " . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
                $params
            )->fetchAll(),
        ];
    }

    public static function find(int $id): ?array
    {
        $r = self::q('SELECT n.*, u.email user_email, u.name user_name FROM notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.id = :id', ['id' => $id])->fetch();
        return $r ?: null;
    }

    public static function delete(int $id): void
    {
        self::q('DELETE FROM notes WHERE id = :id', ['id' => $id]);
    }

    public static function stats(): array
    {
        return [
            'total'   => (int) self::q('SELECT COUNT(*) FROM notes')->fetchColumn(),
            'devices' => (int) self::q('SELECT COUNT(DISTINCT owner) FROM notes')->fetchColumn(),
            'source'  => array_map('intval', self::q('SELECT source, COUNT(*) FROM notes GROUP BY source')->fetchAll(PDO::FETCH_KEY_PAIR)),
        ];
    }

    public static function countForOwner(string $owner): int
    {
        return (int) self::q('SELECT COUNT(*) FROM notes WHERE owner = :o', ['o' => $owner])->fetchColumn();
    }
}
