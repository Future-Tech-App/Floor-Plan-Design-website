<?php
declare(strict_types=1);

/**
 * Plans saved from the web app (/app) and the Android/iOS apps.
 *
 * Two JSON formats are stored as sent:
 *  - web: {name, walls:[{x1,y1,x2,y2,t}], rooms:[{pts:[{x,y}], name, color}], openings:[…], items:[…]}
 *  - app: {name, floors:[{name, elements:[{type:'wall', start:{x,y}, end:{x,y}, thickness},
 *          {type:'room', vertices:[{x,y}], name, floorColor}, {type:'door'|'window', position, width, angle},
 *          {type:'furniture', position, width, depth, rotation, modelName}, …]}]}
 * All lengths are centimetres.
 */
final class PlanStore
{
    public const MAX_BYTES = 2 * 1024 * 1024;   // one plan
    public const MAX_PER_OWNER = 300;            // plans per device
    public const SOURCES = ['web' => 'Web app', 'android' => 'Android', 'ios' => 'iOS'];

    private static function q(string $sql, array $params = []): PDOStatement
    {
        $st = Database::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function ownerHash(string $clientId): string
    {
        return hash('sha256', 'fpd-plan-owner|' . $clientId);
    }

    /** Insert or update one plan. Returns the server row id. */
    public static function save(string $owner, string $planId, string $source, array $plan, string $json): int
    {
        $sum = self::summary($plan);
        $name = mb_substr(trim((string) ($plan['name'] ?? '')) ?: 'Untitled plan', 0, 120);
        self::q(
            "INSERT INTO plans (owner, plan_id, source, name, data, size, floors, rooms, walls, items, area_m2)
             VALUES (:o, :p, :s, :n, :d, :z, :f, :r, :w, :i, :a)
             ON CONFLICT(owner, plan_id) DO UPDATE SET source = excluded.source, name = excluded.name, data = excluded.data,
               size = excluded.size, floors = excluded.floors, rooms = excluded.rooms, walls = excluded.walls,
               items = excluded.items, area_m2 = excluded.area_m2, updated_at = datetime('now')",
            ['o' => $owner, 'p' => $planId, 's' => $source, 'n' => $name, 'd' => $json, 'z' => strlen($json),
             'f' => $sum['floors'], 'r' => $sum['rooms'], 'w' => $sum['walls'], 'i' => $sum['items'], 'a' => $sum['area_m2']]
        );
        return (int) self::q('SELECT id FROM plans WHERE owner = :o AND plan_id = :p', ['o' => $owner, 'p' => $planId])->fetchColumn();
    }

    public static function exists(string $owner, string $planId): bool
    {
        return (bool) self::q('SELECT 1 FROM plans WHERE owner = :o AND plan_id = :p', ['o' => $owner, 'p' => $planId])->fetchColumn();
    }

    public static function countForOwner(string $owner): int
    {
        return (int) self::q('SELECT COUNT(*) FROM plans WHERE owner = :o', ['o' => $owner])->fetchColumn();
    }

    public static function deleteForOwner(string $owner, string $planId): bool
    {
        return self::q('DELETE FROM plans WHERE owner = :o AND plan_id = :p', ['o' => $owner, 'p' => $planId])->rowCount() > 0;
    }

    /* ---------- Admin ---------- */

    public static function search(int $page, int $perPage, string $source, string $q): array
    {
        $where = [];
        $params = [];
        if (isset(self::SOURCES[$source])) {
            $where[] = 'source = :src';
            $params['src'] = $source;
        }
        if ($q !== '') {
            $where[] = 'name LIKE :q';
            $params['q'] = '%' . $q . '%';
        }
        $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) self::q('SELECT COUNT(*) FROM plans' . $w, $params)->fetchColumn();
        $items = self::q(
            'SELECT id, owner, plan_id, source, name, size, floors, rooms, walls, items, area_m2, created_at, updated_at FROM plans'
            . $w . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        )->fetchAll();
        return ['total' => $total, 'items' => $items];
    }

    public static function find(int $id): ?array
    {
        $r = self::q('SELECT * FROM plans WHERE id = :id', ['id' => $id])->fetch();
        return $r ?: null;
    }

    public static function delete(int $id): void
    {
        self::q('DELETE FROM plans WHERE id = :id', ['id' => $id]);
    }

    public static function stats(): array
    {
        $bySource = self::q('SELECT source, COUNT(*) n FROM plans GROUP BY source')->fetchAll(PDO::FETCH_KEY_PAIR);
        return [
            'total'   => (int) self::q('SELECT COUNT(*) FROM plans')->fetchColumn(),
            'week'    => (int) self::q("SELECT COUNT(*) FROM plans WHERE created_at >= datetime('now', '-7 days')")->fetchColumn(),
            'devices' => (int) self::q('SELECT COUNT(DISTINCT owner) FROM plans')->fetchColumn(),
            'source'  => array_map('intval', $bySource),
        ];
    }

    /** How many plans came from the same device. */
    public static function ownerPlanCount(string $owner): int
    {
        return self::countForOwner($owner);
    }

    /* ---------- Reading both formats ---------- */

    /** Normalised geometry for previews and counts. */
    public static function geometry(array $plan, int $floorIndex = 0): array
    {
        $g = ['walls' => [], 'rooms' => [], 'doors' => [], 'windows' => [], 'items' => []];
        if (isset($plan['floors']) && is_array($plan['floors'])) {
            $floor = $plan['floors'][$floorIndex] ?? $plan['floors'][0] ?? [];
            foreach ((array) ($floor['elements'] ?? []) as $el) {
                if (!is_array($el)) {
                    continue;
                }
                $type = (string) ($el['type'] ?? '');
                if ($type === 'wall' && isset($el['start'], $el['end'])) {
                    $g['walls'][] = [(float) $el['start']['x'], (float) $el['start']['y'], (float) $el['end']['x'], (float) $el['end']['y'], (float) ($el['thickness'] ?? 15)];
                } elseif ($type === 'room' && !empty($el['vertices'])) {
                    $g['rooms'][] = ['pts' => array_map(static fn ($v) => [(float) $v['x'], (float) $v['y']], (array) $el['vertices']),
                        'name' => (string) ($el['name'] ?? ''), 'color' => self::argb($el['floorColor'] ?? null)];
                } elseif (($type === 'door' || $type === 'window') && isset($el['position'])) {
                    $g[$type . 's'][] = [(float) $el['position']['x'], (float) $el['position']['y'], (float) ($el['width'] ?? 80), (float) ($el['angle'] ?? 0)];
                } elseif (($type === 'furniture' || $type === 'basic') && isset($el['position'])) {
                    $g['items'][] = [(float) $el['position']['x'], (float) $el['position']['y'], (float) ($el['width'] ?? 50), (float) ($el['depth'] ?? $el['height'] ?? 50), (float) ($el['rotation'] ?? 0)];
                }
            }
            return $g;
        }
        $walls = [];
        foreach ((array) ($plan['walls'] ?? []) as $w) {
            if (isset($w['x1'], $w['y1'], $w['x2'], $w['y2'])) {
                $walls[(string) ($w['id'] ?? count($walls))] = $w;
                $g['walls'][] = [(float) $w['x1'], (float) $w['y1'], (float) $w['x2'], (float) $w['y2'], (float) ($w['t'] ?? 15)];
            }
        }
        foreach ((array) ($plan['rooms'] ?? []) as $r) {
            if (!empty($r['pts'])) {
                $g['rooms'][] = ['pts' => array_map(static fn ($p) => [(float) $p['x'], (float) $p['y']], (array) $r['pts']),
                    'name' => (string) ($r['name'] ?? ''), 'color' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($r['color'] ?? '')) ? $r['color'] : '#e3f2fd'];
            }
        }
        foreach ((array) ($plan['openings'] ?? []) as $o) {
            $w = $walls[(string) ($o['wall'] ?? '')] ?? null;
            if (!$w) {
                continue;
            }
            $t = (float) ($o['t'] ?? .5);
            $x = $w['x1'] + ($w['x2'] - $w['x1']) * $t;
            $y = $w['y1'] + ($w['y2'] - $w['y1']) * $t;
            $ang = rad2deg(atan2($w['y2'] - $w['y1'], $w['x2'] - $w['x1']));
            $g[($o['kind'] ?? '') === 'window' ? 'windows' : 'doors'][] = [$x, $y, (float) ($o['w'] ?? 80), $ang];
        }
        foreach ((array) ($plan['items'] ?? []) as $it) {
            if (isset($it['x'], $it['y'])) {
                $g['items'][] = [(float) $it['x'], (float) $it['y'], (float) ($it['w'] ?? 50), (float) ($it['h'] ?? 50), (float) ($it['rot'] ?? 0)];
            }
        }
        return $g;
    }

    public static function summary(array $plan): array
    {
        $floors = isset($plan['floors']) && is_array($plan['floors']) ? max(1, count($plan['floors'])) : 1;
        $s = ['floors' => $floors, 'rooms' => 0, 'walls' => 0, 'items' => 0, 'area_m2' => 0.0];
        for ($i = 0; $i < $floors; $i++) {
            $g = self::geometry($plan, $i);
            $s['rooms'] += count($g['rooms']);
            $s['walls'] += count($g['walls']);
            $s['items'] += count($g['items']);
            foreach ($g['rooms'] as $r) {
                $s['area_m2'] += self::polyArea($r['pts']) / 10000;
            }
        }
        $s['area_m2'] = round($s['area_m2'], 2);
        return $s;
    }

    private static function polyArea(array $pts): float
    {
        $a = 0.0;
        $n = count($pts);
        for ($i = 0; $i < $n; $i++) {
            [$x1, $y1] = $pts[$i];
            [$x2, $y2] = $pts[($i + 1) % $n];
            $a += $x1 * $y2 - $x2 * $y1;
        }
        return abs($a) / 2;
    }

    private static function argb(mixed $v): string
    {
        if (!is_numeric($v)) {
            return '#e3f2fd';
        }
        return sprintf('#%06x', ((int) $v) & 0xFFFFFF);
    }

    /** Static SVG drawing of one floor (escaped, safe to print in the admin panel). */
    public static function svg(array $plan, int $floorIndex = 0): string
    {
        $g = self::geometry($plan, $floorIndex);
        $xs = $ys = [];
        foreach ($g['walls'] as [$x1, $y1, $x2, $y2]) {
            array_push($xs, $x1, $x2);
            array_push($ys, $y1, $y2);
        }
        foreach ($g['rooms'] as $r) {
            foreach ($r['pts'] as [$x, $y]) {
                $xs[] = $x;
                $ys[] = $y;
            }
        }
        foreach ($g['items'] as [$x, $y]) {
            $xs[] = $x;
            $ys[] = $y;
        }
        if (!$xs) {
            return '<p class="ad-empty">This plan has nothing drawn yet.</p>';
        }
        $pad = 60;
        $x0 = min($xs) - $pad;
        $y0 = min($ys) - $pad;
        $w = max(100, max($xs) - min($xs) + $pad * 2);
        $h = max(100, max($ys) - min($ys) + $pad * 2);
        $f = static fn (float $v): string => rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
        $fs = max(14, min($w, $h) / 28);
        $out = '<svg class="plan-svg" viewBox="' . $f($x0) . ' ' . $f($y0) . ' ' . $f($w) . ' ' . $f($h) . '" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Plan preview">';
        foreach ($g['rooms'] as $r) {
            $pts = implode(' ', array_map(static fn ($p) => $f($p[0]) . ',' . $f($p[1]), $r['pts']));
            $out .= '<polygon points="' . $pts . '" fill="' . e($r['color']) . '" stroke="none"/>';
        }
        foreach ($g['walls'] as [$x1, $y1, $x2, $y2, $t]) {
            $out .= '<line x1="' . $f($x1) . '" y1="' . $f($y1) . '" x2="' . $f($x2) . '" y2="' . $f($y2) . '" stroke="#37474f" stroke-width="' . $f(max(4, $t)) . '" stroke-linecap="square"/>';
        }
        foreach (['doors' => '#8d6e63', 'windows' => '#64b5f6'] as $key => $col) {
            foreach ($g[$key] as [$x, $y, $wd, $ang]) {
                $dx = cos(deg2rad($ang)) * $wd / 2;
                $dy = sin(deg2rad($ang)) * $wd / 2;
                $out .= '<line x1="' . $f($x - $dx) . '" y1="' . $f($y - $dy) . '" x2="' . $f($x + $dx) . '" y2="' . $f($y + $dy) . '" stroke="' . $col . '" stroke-width="' . ($key === 'doors' ? 14 : 10) . '"/>';
            }
        }
        foreach ($g['items'] as [$x, $y, $iw, $id, $rot]) {
            $out .= '<rect x="' . $f($x - $iw / 2) . '" y="' . $f($y - $id / 2) . '" width="' . $f($iw) . '" height="' . $f($id) . '" rx="6" fill="#ffffff" fill-opacity=".85" stroke="#78909c" stroke-width="3" transform="rotate(' . $f($rot) . ' ' . $f($x) . ' ' . $f($y) . ')"/>';
        }
        foreach ($g['rooms'] as $r) {
            $cx = array_sum(array_column($r['pts'], 0)) / count($r['pts']);
            $cy = array_sum(array_column($r['pts'], 1)) / count($r['pts']);
            if ($r['name'] !== '') {
                $out .= '<text x="' . $f($cx) . '" y="' . $f($cy) . '" font-size="' . $f($fs) . '" text-anchor="middle" dominant-baseline="middle" fill="#0b1b2b" font-weight="700" font-family="system-ui, sans-serif">' . e(mb_substr($r['name'], 0, 40)) . '</text>';
            }
        }
        return $out . '</svg>';
    }

    /** Floor names for the floor switcher. */
    public static function floorNames(array $plan): array
    {
        if (!isset($plan['floors']) || !is_array($plan['floors'])) {
            return ['Plan'];
        }
        return array_map(static fn ($fl, $i) => (string) ($fl['name'] ?? 'Floor ' . ($i + 1)), $plan['floors'], array_keys($plan['floors']));
    }
}
