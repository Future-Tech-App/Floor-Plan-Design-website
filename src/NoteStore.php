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
    public static function replaceForOwner(string $owner, string $source, array $notes): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::q('DELETE FROM notes WHERE owner = :o', ['o' => $owner]);
            $ins = $pdo->prepare('INSERT INTO notes (owner, source, title, content, category, note_time) VALUES (:o, :s, :t, :c, :cat, :nt)');
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
                    'o' => $owner, 's' => $source, 't' => $title, 'c' => $content,
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

    public static function search(int $page, int $perPage, string $source, string $q, string $owner = ''): array
    {
        $where = [];
        $params = [];
        if ($owner !== '') {
            $where[] = 'owner = :own';
            $params['own'] = $owner;
        }
        if (isset(PlanStore::SOURCES[$source])) {
            $where[] = 'source = :src';
            $params['src'] = $source;
        }
        if ($q !== '') {
            $where[] = '(title LIKE :q OR content LIKE :q OR category LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        return [
            'total' => (int) self::q('SELECT COUNT(*) FROM notes' . $w, $params)->fetchColumn(),
            'items' => self::q(
                "SELECT id, owner, source, title, substr(content, 1, 240) excerpt, length(content) chars, category, note_time, synced_at FROM notes"
                . $w . " ORDER BY COALESCE(NULLIF(note_time, ''), synced_at) DESC, id DESC LIMIT " . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
                $params
            )->fetchAll(),
        ];
    }

    public static function find(int $id): ?array
    {
        $r = self::q('SELECT * FROM notes WHERE id = :id', ['id' => $id])->fetch();
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
