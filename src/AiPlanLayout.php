<?php
declare(strict_types=1);

/**
 * Layout engine for the AI floor plan creator.
 *
 * Port of lib/services/ai/floor_plan_layout_engine.dart: rooms are grouped into
 * architectural bands (street front → public → service → bedrooms → utility →
 * outdoor), packed into rows that span the plot width, optionally turned into an
 * L-shape and mirrored. Doors go on shared walls, windows on outer walls, plus a
 * main entrance door.
 *
 * The Dart engine emits Room/Door/Window elements only (rooms draw their own
 * walls). For the web editor this class converts the result into the web plan
 * format: de-duplicated wall segments built from the room edges, rooms as
 * polygons, and doors/windows as openings attached to the wall they sit on.
 * All lengths are centimetres, y grows downward, origin = top-left of the plot.
 */
final class AiPlanLayoutException extends RuntimeException
{
}

final class AiPlanLayout
{
    public const WALL_THICKNESS = 15; // DrawingConstants.DEFAULT_ROOM_WALL_THICKNESS

    /** Dart door/window widths are drawing units (default door 25, window 20); the web editor uses cm (door 90, window 120). */
    private const DOOR_CM_PER_UNIT   = 3.6;
    private const WINDOW_CM_PER_UNIT = 5.0;

    private const PRIVATE_ROOM_TYPES = ['bedroom', 'master_bedroom', 'guest_room'];
    private const BATH_TYPES         = ['bathroom', 'toilet'];
    private const WARDROBE_TYPES     = ['wardrobe', 'walk_in_closet'];
    private const CORRIDOR_TYPES     = ['corridor', 'hallway'];
    private const PUBLIC_ROOM_TYPES  = ['drawing_room', 'living_room', 'tv_lounge', 'family_area',
        'dining_room', 'lobby', 'reception'];

    private const NO_DOOR_TYPES = ['stairs', 'staircase', 'balcony', 'car_porch', 'lawn', 'garden',
        'car_parking', 'garage', 'terrace', 'roof_terrace', 'courtyard'];
    private const NO_WINDOW_TYPES = ['bathroom', 'toilet', 'corridor', 'hallway', 'stairs', 'staircase',
        'elevator', 'storage', 'wardrobe', 'walk_in_closet'];

    /** Web editor palette, picked by room type. */
    private const COLOR_BLUE   = '#e3f2fd'; // bedrooms
    private const COLOR_GREEN  = '#e8f5e9'; // living / lounges
    private const COLOR_ORANGE = '#fff3e0'; // kitchen / dining / food
    private const COLOR_PINK   = '#fce4ec'; // entrance / reception / shops
    private const COLOR_PURPLE = '#ede7f6'; // offices / classrooms / clinical
    private const COLOR_CYAN   = '#e0f7fa'; // wet rooms
    private const COLOR_LIGHT  = '#f1f8e9'; // outdoor
    private const COLOR_GREY   = '#eceff1'; // circulation / parking / utility

    /** @return array{id:string,name:string,type:string,x:float,y:float,w:float,h:float} */
    public static function spec(string $id, string $name, string $type, float $x, float $y, float $w, float $h): array
    {
        return ['id' => $id, 'name' => $name, 'type' => $type, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
    }

    /** Dart num.clamp (returns $lo instead of throwing when $hi < $lo). */
    private static function clamp(float $v, float $lo, float $hi): float
    {
        if ($hi < $lo) {
            return $lo;
        }
        return max($lo, min($hi, $v));
    }

    private static function rand(): float
    {
        return mt_rand() / (mt_getrandmax() + 1);
    }

    /* ------------------------------------------------------------------ */
    /* Room rules                                                          */
    /* ------------------------------------------------------------------ */

    public static function roomBand(string $type): int
    {
        switch ($type) {
            case 'car_porch': case 'garage': case 'car_parking':
            case 'entrance': case 'porch':
                return 0;
            case 'drawing_room': case 'living_room': case 'dining_room':
            case 'tv_lounge': case 'family_area': case 'reception':
            case 'lobby': case 'waiting_area':
                return 1;
            case 'kitchen': case 'pantry': case 'study_office':
            case 'corridor': case 'hallway': case 'prayer_room':
            case 'shop': case 'open_office': case 'meeting_room':
            case 'consultation': case 'pharmacy':
                return 2;
            case 'master_bedroom': case 'bedroom': case 'guest_room':
            case 'bathroom': case 'toilet':
            case 'wardrobe': case 'walk_in_closet':
            case 'master_sitting':
                return 3;
            case 'servant_room': case 'stairs': case 'staircase':
            case 'storage': case 'utility_room': case 'elevator':
            case 'security_room': case 'laundry':
                return 4;
            case 'lawn': case 'garden': case 'balcony':
            case 'terrace': case 'roof_terrace': case 'courtyard':
                return 5;
            default:
                return 2;
        }
    }

    public static function minRoomH(string $type): float
    {
        return match ($type) {
            'car_porch' => 200.0,
            'garage' => 220.0,
            'entrance' => 100.0,
            'master_bedroom' => 220.0,
            'bedroom' => 200.0,
            'drawing_room' => 220.0,
            'tv_lounge' => 200.0,
            'dining_room' => 180.0,
            'kitchen' => 180.0,
            'bathroom' => 120.0,
            'toilet' => 100.0,
            'wardrobe', 'walk_in_closet' => 130.0,
            'master_sitting' => 180.0,
            'servant_room' => 160.0,
            'stairs' => 220.0,
            'corridor' => 100.0,
            'lawn' => 200.0,
            'balcony' => 100.0,
            default => 160.0,
        };
    }

    public static function prefWidthFraction(string $type): float
    {
        return match ($type) {
            'car_porch' => 0.58,
            'entrance' => 0.42,
            'master_bedroom' => 0.38,
            'bedroom' => 0.34,
            'bathroom' => 0.18,
            'wardrobe', 'walk_in_closet' => 0.16,
            'master_sitting' => 0.20,
            'drawing_room', 'living_room' => 0.46,
            'dining_room' => 0.34,
            'tv_lounge' => 0.40,
            'kitchen' => 0.32,
            'corridor' => 0.22,
            'stairs' => 0.20,
            'servant_room' => 0.26,
            'lawn', 'garden', 'balcony' => 1.00,
            default => 0.30,
        };
    }

    public static function roomColor(string $type): string
    {
        $t = strtolower($type);
        switch ($t) {
            case 'bedroom': case 'master_bedroom': case 'guest_room':
            case 'wardrobe': case 'walk_in_closet': case 'master_sitting':
                return self::COLOR_BLUE;
            case 'bathroom': case 'toilet': case 'laundry': case 'storage_laundry':
                return self::COLOR_CYAN;
            case 'kitchen': case 'pantry': case 'dining_room': case 'dining_area':
            case 'restaurant': case 'rooftop_restaurant':
                return self::COLOR_ORANGE;
            case 'living_room': case 'drawing_room': case 'tv_lounge': case 'family_area':
                return self::COLOR_GREEN;
            case 'lawn': case 'garden': case 'courtyard': case 'balcony':
            case 'terrace': case 'roof_terrace':
                return self::COLOR_LIGHT;
            case 'entrance': case 'porch': case 'reception': case 'lobby':
            case 'waiting_area': case 'shop': case 'store':
                return self::COLOR_PINK;
            case 'office': case 'private_office': case 'open_office': case 'meeting_room':
            case 'study_office': case 'classroom': case 'library': case 'lab': case 'computer_lab':
            case 'consultation': case 'emergency_room': case 'pharmacy': case 'hall':
            case 'large_hall': case 'prayer_room':
                return self::COLOR_PURPLE;
            default:
                // corridor, hallway, stairs, elevator, parking, utility, servant, other_space…
                return self::COLOR_GREY;
        }
    }

    /* ------------------------------------------------------------------ */
    /* Main layout                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Group rooms into bands, pack rows, optional L-shape, optional mirror.
     *
     * @param list<array> $rooms room specs (x/y ignored)
     * @return list<array> positioned room specs
     */
    public static function autoGridLayout(array $rooms, float $plotW, ?bool $mirror = null): array
    {
        if ($rooms === []) {
            return [];
        }
        $pw = round($plotW);

        // Plot-aspect-aware depth scaling: 5 Marla (500) → 1.0×, 10 Marla (700) → 1.18×, 1 Kanal → 1.41×.
        $hScale = self::clamp(sqrt($pw / 500.0), 0.85, 1.6);
        $isFlexW = static fn (string $type): bool =>
            !in_array($type, ['lawn', 'garden', 'balcony', 'terrace', 'roof_terrace', 'courtyard'], true);

        // 1. Normalise sizes: per-type rules + plot-aware scaling + ±15% width / ±10% height jitter.
        $normed = [];
        foreach ($rooms as $r) {
            $prefW = self::clamp($pw * self::prefWidthFraction($r['type']), 60.0, $pw * 0.80);
            $minH  = self::minRoomH($r['type']);
            $wJit  = $isFlexW($r['type']) ? (1.0 + (self::rand() - 0.5) * 0.30) : 1.0;
            $hJit  = $isFlexW($r['type']) ? (1.0 + (self::rand() - 0.5) * 0.20) : 1.0;
            $w = ($r['w'] > 0 ? $r['w'] : $prefW) * $wJit;
            $h = self::clamp(($r['h'] > 0 ? $r['h'] : $minH) * $hScale * $hJit, $minH, 700.0);
            $normed[] = self::spec($r['id'], $r['name'], $r['type'], 0, 0, self::clamp($w, 60.0, $pw * 0.85), $h);
        }

        // 2. Separate into bands
        $band = static fn (int $b): array =>
            array_values(array_filter($normed, static fn (array $r): bool => self::roomBand($r['type']) === $b));
        $frontRooms   = $band(0);
        $publicRooms  = $band(1);
        $serviceRooms = $band(2);
        $privateRooms = $band(3);
        $utilityRooms = $band(4);
        $outdoorRooms = $band(5);
        $ofType = static fn (array $list, array $types, bool $want = true): array =>
            array_values(array_filter($list, static fn (array $r): bool => in_array($r['type'], $types, true) === $want));

        $allRows = [];

        // 3. BAND 0 — front face: car porch LEFT (~58%) + entrance RIGHT (~42%)
        if ($frontRooms !== []) {
            $porches   = $ofType($frontRooms, ['car_porch', 'garage', 'car_parking']);
            $entrances = $ofType($frontRooms, ['entrance', 'porch']);
            if ($porches !== [] && $entrances !== []) {
                $frontH = max(max(array_column($porches, 'h')), max(array_column($entrances, 'h')));
                $totalHint = array_sum(array_column($porches, 'w')) + array_sum(array_column($entrances, 'w'));
                $scale = $totalHint > 0 ? $pw / $totalHint : 1.0;
                $frontRow = [];
                $ix = 0.0;
                foreach ([[$porches, false], [$entrances, true]] as [$list, $isEntrances]) {
                    $n = count($list);
                    foreach ($list as $i => $r) {
                        $isLast = ($i === $n - 1) && $isEntrances;
                        $w = $isLast ? $pw - $ix : self::clamp(round($r['w'] * $scale), 60.0, $pw - $ix - 60);
                        $frontRow[] = self::spec($r['id'], $r['name'], $r['type'], $ix, 0, $w, $frontH);
                        $ix += $w;
                    }
                }
                $allRows[] = $frontRow;
            } else {
                // Only one kind present — span full width
                $h = max(array_column($frontRooms, 'h'));
                $n = count($frontRooms);
                $row = [];
                foreach ($frontRooms as $r) {
                    $row[] = self::spec($r['id'], $r['name'], $r['type'], 0, 0, $pw / $n, $h);
                }
                $allRows[] = $row;
            }
        }

        // 4. BAND 1 — public rooms: dining stays LAST (above the kitchen), the rest shuffles.
        if ($publicRooms !== []) {
            $dining = $ofType($publicRooms, ['dining_room']);
            $rest   = $ofType($publicRooms, ['dining_room'], false);
            shuffle($rest);
            array_push($allRows, ...self::packBandRows(array_merge($rest, $dining), $pw));
        }

        // Auto-spine: 2+ bedrooms but no corridor → synthesize one.
        $hasExplicitCorridor = $ofType($serviceRooms, ['corridor', 'hallway']) !== [];
        $bedroomCount = count($ofType($privateRooms, ['master_bedroom', 'bedroom', 'guest_room']));
        if (!$hasExplicitCorridor && $bedroomCount >= 2) {
            $serviceRooms[] = self::spec('auto_corridor', 'Corridor', 'corridor', 0, 0, $pw, 100);
        }

        // 5. BAND 2 — service: shuffled rest, then pantry, then kitchen (last = corner);
        //    every corridor becomes a thin full-width spine row above the bedrooms.
        if ($serviceRooms !== []) {
            $corridors = $ofType($serviceRooms, ['corridor', 'hallway']);
            $kitchens  = $ofType($serviceRooms, ['kitchen']);
            $pantries  = $ofType($serviceRooms, ['pantry']);
            $rest      = $ofType($serviceRooms, ['corridor', 'hallway', 'kitchen', 'pantry'], false);
            shuffle($rest);
            if ($rest !== [] || $kitchens !== [] || $pantries !== []) {
                array_push($allRows, ...self::packBandRows(array_merge($rest, $pantries, $kitchens), $pw));
            }
            foreach ($corridors as $c) {
                $allRows[] = [self::spec($c['id'], $c['name'], $c['type'], 0, 0, $pw, self::clamp($c['h'], 90.0, 130.0))];
            }
        }

        // 6. BAND 3 — bedrooms, each paired with its bathroom. Master 70% back / 30% front.
        if ($privateRooms !== []) {
            $masterAtBack = self::rand() < 0.7;
            $shuffled = self::shuffleNonMasterBedrooms($privateRooms);
            $paired = self::pairBedsWithBaths($shuffled, $masterAtBack);
            array_push($allRows, ...self::packBandRows($paired, $pw));
        }

        // 7. BAND 4 — utility: stairs / elevator first, the rest shuffles.
        if ($utilityRooms !== []) {
            $stairs = $ofType($utilityRooms, ['stairs', 'staircase', 'elevator']);
            $rest   = $ofType($utilityRooms, ['stairs', 'staircase', 'elevator'], false);
            shuffle($rest);
            array_push($allRows, ...self::packBandRows(array_merge($stairs, $rest), $pw));
        }

        // 8. BAND 5 — outdoor: every lawn/garden spans the full plot width.
        foreach ($outdoorRooms as $r) {
            $h = self::clamp($r['h'], self::minRoomH($r['type']), 400.0);
            $allRows[] = [self::spec($r['id'], $r['name'], $r['type'], 0, 0, $pw, $h)];
        }

        // 9. Position rows top → bottom, scaling each to fill plotW.
        $positioned = self::positionRows($allRows, $pw);

        // 9b. L-shape archetype for wide plots with 3+ bedrooms.
        $useLShape = $pw >= 850 && $bedroomCount >= 3;
        $shaped = $useLShape ? self::applyLShape($positioned, $pw) : $positioned;

        // 10. Horizontal mirror.
        $shouldMirror = $mirror ?? (mt_rand(0, 1) === 1);
        return $shouldMirror ? self::mirrorHorizontally($shaped, $pw) : $shaped;
    }

    private static function mirrorHorizontally(array $rooms, float $pw): array
    {
        return array_map(static fn (array $r): array =>
            self::spec($r['id'], $r['name'], $r['type'], $pw - $r['x'] - $r['w'], $r['y'], $r['w'], $r['h']), $rooms);
    }

    /** L-shape: bedroom suites stacked in a left wing (55%), a Side Garden fills the right column. */
    private static function applyLShape(array $rooms, float $pw): array
    {
        $isPrivate = static fn (array $r): bool => in_array($r['type'], self::PRIVATE_ROOM_TYPES, true);
        $isBedroomBand = static fn (array $r): bool =>
            $isPrivate($r) || in_array($r['type'], self::BATH_TYPES, true)
            || in_array($r['type'], self::WARDROBE_TYPES, true) || $r['type'] === 'master_sitting';

        $beds = array_values(array_filter($rooms, $isBedroomBand));
        if ($beds === []) {
            return $rooms;
        }
        $bedTopY = min(array_column($beds, 'y'));
        $bedBottomY = max(array_map(static fn (array $r): float => $r['y'] + $r['h'], $beds));
        $originalH = $bedBottomY - $bedTopY;

        $sorted = $beds;
        usort($sorted, static fn (array $a, array $b): int =>
            $a['y'] != $b['y'] ? $a['y'] <=> $b['y'] : $a['x'] <=> $b['x']);

        $groups = [];
        $current = [];
        foreach ($sorted as $r) {
            if ($isPrivate($r)) {
                if ($current !== []) {
                    $groups[] = $current;
                }
                $current = [$r];
            } else {
                $current[] = $r; // orphan bath/wardrobe before any bedroom starts its own group
            }
        }
        if ($current !== []) {
            $groups[] = $current;
        }

        $colW = round($pw * 0.55);
        $gardenW = $pw - $colW;
        $newBeds = [];
        $rowY = $bedTopY;
        foreach ($groups as $group) {
            $rowH = round(max(array_column($group, 'h')));
            $totalW = 0.0;
            foreach ($group as $r) {
                $totalW += self::clamp($r['w'], 60.0, $colW);
            }
            $scale = $totalW > 0 ? $colW / $totalW : 1.0;
            $x = 0.0;
            $n = count($group);
            foreach ($group as $i => $r) {
                $w = $i === $n - 1
                    ? $colW - $x
                    : self::clamp(round($r['w'] * $scale), 60.0, $colW - $x - 60 * ($n - 1 - $i));
                $newBeds[] = self::spec($r['id'], $r['name'], $r['type'], $x, $rowY, $w, $rowH);
                $x += $w;
            }
            $rowY += $rowH;
        }
        $newH = $rowY - $bedTopY;
        $yShift = $newH - $originalH;

        $result = [];
        foreach ($rooms as $r) {
            if ($isBedroomBand($r)) {
                continue;
            }
            if ($r['y'] >= $bedBottomY - 0.5) {
                $r['y'] += $yShift; // below the bedroom band — push down
            }
            $result[] = $r;
        }
        array_push($result, ...$newBeds);
        $result[] = self::spec('l_side_garden', 'Side Garden', 'lawn', $colW, $bedTopY, $gardenW, $newH);
        return $result;
    }

    /** Split generous master bedrooms (≥ 270 × 230) into bedroom + sitting alcove. */
    private static function splitLargeMasterSuites(array $rooms): array
    {
        $result = [];
        foreach ($rooms as $r) {
            if ($r['type'] === 'master_bedroom' && $r['w'] >= 270 && $r['h'] >= 230) {
                $sittingW = self::clamp($r['w'] * 0.28, 120.0, 200.0);
                $result[] = self::spec($r['id'], $r['name'], $r['type'], 0, 0, $r['w'] - $sittingW, $r['h']);
                $result[] = self::spec($r['id'] . '_sit', 'Sitting Area', 'master_sitting', 0, 0, $sittingW, $r['h']);
            } else {
                $result[] = $r;
            }
        }
        return $result;
    }

    /**
     * Masters, then shuffled regular bedrooms, then baths. Like the Dart code this
     * drops wardrobes and sitting alcoves from the bedroom band.
     */
    private static function shuffleNonMasterBedrooms(array $rooms): array
    {
        $masters = array_values(array_filter($rooms, static fn (array $r): bool => $r['type'] === 'master_bedroom'));
        $regular = array_values(array_filter($rooms, static fn (array $r): bool =>
            $r['type'] === 'bedroom' || $r['type'] === 'guest_room'));
        shuffle($regular);
        $baths = array_values(array_filter($rooms, static fn (array $r): bool =>
            $r['type'] === 'bathroom' || $r['type'] === 'toilet'));
        return array_merge($masters, $regular, $baths);
    }

    /** Greedy row packing: rows ≤ plotW (2% slack). */
    private static function packBandRows(array $rooms, float $pw): array
    {
        $rows = [];
        $row = [];
        $used = 0.0;
        foreach ($rooms as $r) {
            $rw = self::clamp($r['w'], 60.0, $pw * 0.80);
            if ($row !== [] && $used + $rw > $pw * 1.02) {
                $rows[] = $row;
                $row = [];
                $used = 0.0;
            }
            $row[] = $r;
            $used += $rw;
        }
        if ($row !== []) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** [bedroom] [sitting? (master)] [bath] [wardrobe?] — master first or last per $masterAtBack. */
    private static function pairBedsWithBaths(array $rooms, bool $masterAtBack): array
    {
        $beds = array_values(array_filter($rooms, static fn (array $r): bool =>
            in_array($r['type'], self::PRIVATE_ROOM_TYPES, true)));
        $baths = array_values(array_filter($rooms, static fn (array $r): bool =>
            in_array($r['type'], self::BATH_TYPES, true)));
        $wardrobes = array_values(array_filter($rooms, static fn (array $r): bool =>
            in_array($r['type'], self::WARDROBE_TYPES, true)));
        $sittings = array_values(array_filter($rooms, static fn (array $r): bool => $r['type'] === 'master_sitting'));
        $usedBath = $usedWard = $usedSit = [];
        $result = [];

        // Stable sort (PHP 8 usort is stable, like Dart's List.sort for equal keys here).
        usort($beds, static function (array $a, array $b) use ($masterAtBack): int {
            $am = $a['type'] === 'master_bedroom';
            $bm = $b['type'] === 'master_bedroom';
            if ($am === $bm) {
                return 0;
            }
            if ($masterAtBack) {
                return $am ? 1 : -1;
            }
            return $am ? -1 : 1;
        });

        $mastersFirst = $beds;
        usort($mastersFirst, static function (array $a, array $b): int {
            if ($a['type'] === $b['type']) {
                return 0;
            }
            return $a['type'] === 'master_bedroom' ? -1 : 1;
        });
        $wardrobeOf = [];
        foreach ($mastersFirst as $bed) {
            foreach ($wardrobes as $w) {
                if (!isset($usedWard[$w['id']])) {
                    $usedWard[$w['id']] = true;
                    $wardrobeOf[$bed['id']] = $w;
                    break;
                }
            }
        }

        foreach ($beds as $bed) {
            $result[] = $bed;
            if ($bed['type'] === 'master_bedroom') {
                foreach ($sittings as $s) {
                    if (!isset($usedSit[$s['id']])) {
                        $usedSit[$s['id']] = true;
                        $result[] = $s;
                        break;
                    }
                }
            }
            foreach ($baths as $b) {
                if (!isset($usedBath[$b['id']])) {
                    $usedBath[$b['id']] = true;
                    $result[] = $b;
                    break;
                }
            }
            if (isset($wardrobeOf[$bed['id']])) {
                $result[] = $wardrobeOf[$bed['id']];
            }
        }
        foreach ($baths as $b) {
            if (!isset($usedBath[$b['id']])) $result[] = $b;
        }
        foreach ($wardrobes as $w) {
            if (!isset($usedWard[$w['id']])) $result[] = $w;
        }
        foreach ($sittings as $s) {
            if (!isset($usedSit[$s['id']])) $result[] = $s;
        }
        return $result;
    }

    /** Scale each row to fill plotW, then stack rows vertically. */
    private static function positionRows(array $rows, float $pw): array
    {
        $result = [];
        $iy = 0.0;
        foreach ($rows as $rowRooms) {
            $totalW = 0.0;
            foreach ($rowRooms as $r) {
                $totalW += self::clamp($r['w'], 60.0, $pw * 0.80);
            }
            $scale = $totalW > 0 ? $pw / $totalW : 1.0;
            $rowH = round(max(array_column($rowRooms, 'h')));
            $ix = 0.0;
            $n = count($rowRooms);
            foreach ($rowRooms as $i => $r) {
                $w = $i === $n - 1
                    ? self::clamp($pw - $ix, 40.0, $pw)
                    : self::clamp(round(self::clamp($r['w'], 60.0, $pw * 0.80) * $scale), 40.0, $pw - $ix - 40 * ($n - 1 - $i));
                $result[] = self::spec($r['id'], $r['name'], $r['type'], $ix, $iy, $w, $rowH);
                $ix += $w;
            }
            $iy += $rowH;
        }
        return $result;
    }

    /* ------------------------------------------------------------------ */
    /* Doors & windows (Dart geometry)                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Shared wall between two rooms: [vertical, x, y, lo, hi] where (x, y) is the
     * midpoint of the shared run and lo..hi its extent along the wall.
     */
    private static function sharedWall(array $a, array $b): ?array
    {
        $tol = 1.5;
        $minOverlap = 40.0;
        $aR = $a['x'] + $a['w']; $aB = $a['y'] + $a['h'];
        $bR = $b['x'] + $b['w']; $bB = $b['y'] + $b['h'];
        if (abs($aR - $b['x']) < $tol) {
            $y1 = max($a['y'], $b['y']); $y2 = min($aB, $bB);
            if ($y2 - $y1 > $minOverlap) return [true, $aR, ($y1 + $y2) / 2, $y1, $y2];
        }
        if (abs($bR - $a['x']) < $tol) {
            $y1 = max($a['y'], $b['y']); $y2 = min($aB, $bB);
            if ($y2 - $y1 > $minOverlap) return [true, $bR, ($y1 + $y2) / 2, $y1, $y2];
        }
        if (abs($aB - $b['y']) < $tol) {
            $x1 = max($a['x'], $b['x']); $x2 = min($aR, $bR);
            if ($x2 - $x1 > $minOverlap) return [false, ($x1 + $x2) / 2, $aB, $x1, $x2];
        }
        if (abs($bB - $a['y']) < $tol) {
            $x1 = max($a['x'], $b['x']); $x2 = min($aR, $bR);
            if ($x2 - $x1 > $minOverlap) return [false, ($x1 + $x2) / 2, $bB, $x1, $x2];
        }
        return null;
    }

    /** Door width (Dart units) and position by what the door connects. @return array{0: float, 1: float, 2: float} */
    private static function smartDoor(array $a, array $b, bool $vertical, float $mx, float $my): array
    {
        $inEither = static fn (array $types): bool =>
            in_array($a['type'], $types, true) || in_array($b['type'], $types, true);
        $hasBath     = $inEither(self::BATH_TYPES);
        $hasWardrobe = $inEither(self::WARDROBE_TYPES);
        $hasCorridor = $inEither(self::CORRIDOR_TYPES);
        $hasPublic   = $inEither(self::PUBLIC_ROOM_TYPES);
        $hasBedroom  = $inEither(self::PRIVATE_ROOM_TYPES);

        if ($hasBath) $width = 20.0;
        elseif ($hasWardrobe) $width = 18.0;
        elseif ($hasCorridor && $hasPublic) $width = 32.0;
        elseif ($hasPublic) $width = 28.0;
        elseif ($hasCorridor) $width = 26.0;
        else $width = 25.0;

        // Bath / wardrobe next to a bedroom: door toward the corridor end, keeps the bed wall clear.
        if (($hasBath || $hasWardrobe) && $hasBedroom) {
            $bedroom = in_array($a['type'], self::PRIVATE_ROOM_TYPES, true) ? $a : $b;
            if ($vertical) {
                $my = $bedroom['y'] + $bedroom['h'] * 0.22;
            } else {
                $mx = $bedroom['x'] + $bedroom['w'] * 0.22;
            }
        }
        return [$width, $mx, $my];
    }

    private static function isOuterWall(array $room, string $side, array $all): bool
    {
        $tol = 3.0;
        $rR = $room['x'] + $room['w']; $rB = $room['y'] + $room['h'];
        foreach ($all as $o) {
            if ($o['id'] === $room['id']) {
                continue;
            }
            $oR = $o['x'] + $o['w']; $oB = $o['y'] + $o['h'];
            $hOverlap = max($room['x'], $o['x']) < min($rR, $oR) - 20;
            $vOverlap = max($room['y'], $o['y']) < min($rB, $oB) - 20;
            switch ($side) {
                case 'top':    if (abs($oB - $room['y']) < $tol && $hOverlap) return false; break;
                case 'bottom': if (abs($rB - $o['y']) < $tol && $hOverlap) return false; break;
                case 'left':   if (abs($oR - $room['x']) < $tol && $vOverlap) return false; break;
                case 'right':  if (abs($rR - $o['x']) < $tol && $vOverlap) return false; break;
            }
        }
        return true;
    }

    /**
     * Build one floor: layout + doors + windows, returned as a web plan.
     * Throws AiPlanLayoutException when there are no rooms (Dart: specs.first on an empty list).
     *
     * @param list<array> $rawSpecs
     * @return array web plan {version, walls, rooms, openings, items, labels, dims}
     */
    public static function buildFloorElements(array $rawSpecs, float $plotW, ?bool $mirror = null): array
    {
        // Keep the plot within a sane range (degenerate sqft values would otherwise break the clamps).
        $plotW = max(200.0, min(5000.0, $plotW));
        $specs = self::autoGridLayout(self::splitLargeMasterSuites($rawSpecs), $plotW, $mirror);
        if ($specs === []) {
            throw new AiPlanLayoutException('No rooms to lay out.');
        }

        // Doors on shared walls. Each: [vertical, x, y, widthCm, hinge, lo, hi]
        $doors = [];
        $doorCount = array_fill_keys(array_column($specs, 'id'), 0);
        $maxDoors = static fn (string $type): int => match ($type) {
            'corridor', 'hallway' => 8,
            'lobby', 'open_office', 'dining_area', 'restaurant' => 4,
            'living_room', 'drawing_room', 'tv_lounge', 'family_area', 'reception' => 2,
            default => 1,
        };
        $added = 0;
        $n = count($specs);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $specs[$i];
                $b = $specs[$j];
                if (in_array($a['type'], self::NO_DOOR_TYPES, true) || in_array($b['type'], self::NO_DOOR_TYPES, true)) continue;
                if (($doorCount[$a['id']] ?? 0) >= $maxDoors($a['type'])) continue;
                if (($doorCount[$b['id']] ?? 0) >= $maxDoors($b['type'])) continue;
                $wall = self::sharedWall($a, $b);
                if ($wall === null) continue;
                [$vertical, $mx, $my, $lo, $hi] = $wall;
                [$width, $px, $py] = self::smartDoor($a, $b, $vertical, $mx, $my);
                // Alternate swing so arcs face varied sides (Dart: DoorSwing.left / right).
                $hinge = $added % 2 === 1;
                $span = $hi - $lo;
                $wCm = min(round($width * self::DOOR_CM_PER_UNIT), max($span - 10, 50.0));
                $doors[] = [$vertical, $px, $py, $wCm, $hinge, $lo, $hi];
                $added++;
                $doorCount[$a['id']] = ($doorCount[$a['id']] ?? 0) + 1;
                $doorCount[$b['id']] = ($doorCount[$b['id']] ?? 0) + 1;
            }
        }

        // Main entrance door — top wall of the entrance room.
        $entrance = $specs[0];
        foreach ($specs as $s) {
            if ($s['type'] === 'entrance' || str_contains(AiPlanText::lower($s['name']), 'entrance')) {
                $entrance = $s;
                break;
            }
        }
        $doors[] = [false, $entrance['x'] + $entrance['w'] / 2, $entrance['y'],
            round(30 * self::DOOR_CM_PER_UNIT), false, $entrance['x'], $entrance['x'] + $entrance['w']];

        // Windows on outer walls; big rooms get up to two (30% / 70%), others one (50%).
        $windows = [];
        foreach ($specs as $s) {
            if (in_array($s['type'], self::NO_WINDOW_TYPES, true)) continue;
            $isBig = $s['w'] >= 280 || $s['h'] >= 240;
            $maxWindows = $isBig ? 2 : 1;
            $winCount = 0;
            foreach (['top', 'left', 'right', 'bottom'] as $side) {
                if ($winCount >= $maxWindows) break;
                if (!self::isOuterWall($s, $side, $specs)) continue;
                $wallLen = ($side === 'top' || $side === 'bottom') ? $s['w'] : $s['h'];
                if ($wallLen < 80) continue;
                $off = $winCount === 0 ? ($maxWindows === 2 ? 0.30 : 0.50) : 0.70;
                $wCm = round(($isBig ? 28.0 : 22.0) * self::WINDOW_CM_PER_UNIT);
                $windows[] = match ($side) {
                    'top'    => [false, $s['x'] + $s['w'] * $off, $s['y'], $wCm],
                    'bottom' => [false, $s['x'] + $s['w'] * $off, $s['y'] + $s['h'], $wCm],
                    'left'   => [true, $s['x'], $s['y'] + $s['h'] * $off, $wCm],
                    default  => [true, $s['x'] + $s['w'], $s['y'] + $s['h'] * $off, $wCm],
                };
                $winCount++;
            }
        }

        return self::toWebPlan($specs, $doors, $windows);
    }

    /* ------------------------------------------------------------------ */
    /* Web plan conversion                                                 */
    /* ------------------------------------------------------------------ */

    private static function num(float $v): int|float
    {
        $r = round($v, 1);
        return abs($r - round($r)) < 1e-9 ? (int) round($r) : $r;
    }

    /**
     * Walls = union of collinear room edges (each shared edge drawn once), rooms =
     * polygons, doors/windows = openings attached to the wall they lie on.
     */
    private static function toWebPlan(array $specs, array $doors, array $windows): array
    {
        $rooms = [];
        $hEdges = [];
        $vEdges = [];
        foreach ($specs as $i => $s) {
            $x1 = round($s['x'], 1); $y1 = round($s['y'], 1);
            $x2 = round($s['x'] + $s['w'], 1); $y2 = round($s['y'] + $s['h'], 1);
            if ($x2 - $x1 < 1 || $y2 - $y1 < 1) {
                continue; // degenerate
            }
            $rooms[] = [
                'id'    => 'r' . (count($rooms) + 1),
                'pts'   => [
                    ['x' => self::num($x1), 'y' => self::num($y1)],
                    ['x' => self::num($x2), 'y' => self::num($y1)],
                    ['x' => self::num($x2), 'y' => self::num($y2)],
                    ['x' => self::num($x1), 'y' => self::num($y2)],
                ],
                'name'  => $s['name'],
                'color' => self::roomColor($s['type']),
            ];
            $hEdges[] = [$y1, $x1, $x2];
            $hEdges[] = [$y2, $x1, $x2];
            $vEdges[] = [$x1, $y1, $y2];
            $vEdges[] = [$x2, $y1, $y2];
        }

        $walls = [];
        foreach (self::mergeEdges($hEdges) as [$c, $a, $b]) {
            $walls[] = ['id' => 'w' . (count($walls) + 1), 'x1' => self::num($a), 'y1' => self::num($c),
                'x2' => self::num($b), 'y2' => self::num($c), 't' => self::WALL_THICKNESS];
        }
        foreach (self::mergeEdges($vEdges) as [$c, $a, $b]) {
            $walls[] = ['id' => 'w' . (count($walls) + 1), 'x1' => self::num($c), 'y1' => self::num($a),
                'x2' => self::num($c), 'y2' => self::num($b), 't' => self::WALL_THICKNESS];
        }

        $openings = [];
        $placed = []; // wall id → list of [centre along wall, width]
        $attach = static function (string $kind, bool $vertical, float $x, float $y, float $w, bool $hinge, ?float $lo, ?float $hi)
            use (&$walls, &$openings, &$placed): void {
            $tol = 1.5;
            foreach ($walls as $wall) {
                $isVertical = $wall['x1'] == $wall['x2'];
                if ($isVertical !== $vertical) continue;
                if ($vertical) {
                    if (abs($wall['x1'] - $x) > $tol || $y < $wall['y1'] - $tol || $y > $wall['y2'] + $tol) continue;
                    $start = (float) $wall['y1']; $len = $wall['y2'] - $wall['y1']; $pos = $y - $start;
                } else {
                    if (abs($wall['y1'] - $y) > $tol || $x < $wall['x1'] - $tol || $x > $wall['x2'] + $tol) continue;
                    $start = (float) $wall['x1']; $len = $wall['x2'] - $wall['x1']; $pos = $x - $start;
                }
                $w = min($w, $len - 10);
                if ($w < 20) return;
                $half = $w / 2;
                // Keep the opening within the shared run when it fits.
                if ($lo !== null && $hi !== null && ($hi - $lo) >= $w) {
                    $pos = max($lo - $start + $half, min($hi - $start - $half, $pos));
                }
                $pos = max($half, min($len - $half, $pos));
                foreach ($placed[$wall['id']] ?? [] as [$p2, $w2]) {
                    if (abs($pos - $p2) < ($w + $w2) / 2 + 5) return; // would overlap another opening
                }
                $placed[$wall['id']][] = [$pos, $w];
                $openings[] = ['id' => 'o' . (count($openings) + 1), 'kind' => $kind, 'wall' => $wall['id'],
                    't' => round($pos / $len, 4), 'w' => self::num($w), 'flip' => false, 'hinge' => $hinge];
                return;
            }
            // Not on any wall → skipped.
        };
        foreach ($doors as [$vertical, $x, $y, $w, $hinge, $lo, $hi]) {
            $attach('door', $vertical, $x, $y, $w, $hinge, $lo, $hi);
        }
        foreach ($windows as [$vertical, $x, $y, $w]) {
            $attach('window', $vertical, $x, $y, $w, false, null, null);
        }

        return ['version' => 1, 'walls' => $walls, 'rooms' => $rooms, 'openings' => $openings,
            'items' => [], 'labels' => [], 'dims' => []];
    }

    /**
     * Merge collinear edges [coord, from, to] into maximal non-overlapping segments.
     *
     * @return list<array{0: float, 1: float, 2: float}>
     */
    private static function mergeEdges(array $edges): array
    {
        usort($edges, static fn (array $p, array $q): int => [$p[0], $p[1]] <=> [$q[0], $q[1]]);
        // Group edges on the same line (coordinates are already rounded to 0.1 cm; the
        // engine produces whole-cm values, so walls 1 cm apart are genuinely distinct).
        $lines = [];
        foreach ($edges as $e) {
            $last = count($lines) - 1;
            if ($last >= 0 && abs($e[0] - $lines[$last][0]) < 0.05) {
                $lines[$last][1][] = [$e[1], $e[2]];
            } else {
                $lines[] = [$e[0], [[$e[1], $e[2]]]];
            }
        }
        $out = [];
        foreach ($lines as [$c, $spans]) {
            usort($spans, static fn (array $p, array $q): int => $p[0] <=> $q[0]);
            $cur = null;
            foreach ($spans as [$a, $b]) {
                if ($cur !== null && $a <= $cur[1] + 1.0) {
                    $cur[1] = max($cur[1], $b);
                    continue;
                }
                if ($cur !== null && $cur[1] - $cur[0] >= 1) {
                    $out[] = [$c, $cur[0], $cur[1]];
                }
                $cur = [$a, $b];
            }
            if ($cur !== null && $cur[1] - $cur[0] >= 1) {
                $out[] = [$c, $cur[0], $cur[1]];
            }
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Floor helpers                                                       */
    /* ------------------------------------------------------------------ */

    /** Room specs from AI JSON (Dart RoomSpec.fromJson; throws on malformed input). */
    public static function specsFromJsonRooms(mixed $rawRooms): array
    {
        if ($rawRooms === null) {
            return [];
        }
        if (!is_array($rawRooms) || !array_is_list($rawRooms)) {
            throw new AiPlanLayoutException('rooms is not a list');
        }
        $specs = [];
        foreach (array_slice($rawRooms, 0, AiPlanText::MAX_ROOMS_PER_FLOOR) as $k => $j) {
            if (!is_array($j)) {
                throw new AiPlanLayoutException('room is not an object');
            }
            $str = static function (string $key, string $default) use ($j): string {
                $v = $j[$key] ?? null;
                if ($v === null) return $default;
                if (!is_string($v)) throw new AiPlanLayoutException("room.$key is not a string");
                return function_exists('mb_substr') ? mb_substr($v, 0, 60, 'UTF-8') : substr($v, 0, 60);
            };
            $num = static function (string $key) use ($j): float {
                $v = $j[$key] ?? null;
                if ($v === null) return 200.0;
                if (!is_int($v) && !is_float($v)) throw new AiPlanLayoutException("room.$key is not a number");
                return (float) $v;
            };
            $specs[] = self::spec($str('id', 'r' . $k), $str('name', 'Room'), $str('type', 'other_space'),
                0, 0, $num('w'), $num('h'));
        }
        return $specs;
    }

    /** Dart floorDataFromJson. @return array{name: string, plan: array} */
    public static function floorDataFromJson(mixed $j, string $defaultName, float $plotW, ?bool $mirror): array
    {
        if (!is_array($j)) {
            throw new AiPlanLayoutException('floor is not an object');
        }
        $name = $j['name'] ?? null;
        if ($name !== null && !is_string($name)) {
            throw new AiPlanLayoutException('floor.name is not a string');
        }
        $name = $name === null ? $defaultName : (function_exists('mb_substr') ? mb_substr($name, 0, 60, 'UTF-8') : substr($name, 0, 60));
        $specs = self::specsFromJsonRooms($j['rooms'] ?? null);
        return ['name' => $name, 'plan' => self::buildFloorElements($specs, $plotW, $mirror)];
    }

    /** Dart floorsFromJson. @return list<array{name: string, plan: array}> */
    public static function floorsFromJson(array $json, float $plotW, ?bool $mirror): array
    {
        $rawFloors = $json['floors'] ?? null;
        if ($rawFloors !== null && !is_array($rawFloors)) {
            throw new AiPlanLayoutException('floors is not a list');
        }
        if (is_array($rawFloors) && $rawFloors !== []) {
            $out = [];
            foreach (array_slice(array_values($rawFloors), 0, 25) as $f) {
                $out[] = self::floorDataFromJson($f, 'Floor', $plotW, $mirror);
            }
            return $out;
        }
        $specs = self::specsFromJsonRooms($json['rooms'] ?? null);
        if ($specs === []) {
            return [];
        }
        return [['name' => 'Ground Floor', 'plan' => self::buildFloorElements($specs, $plotW, $mirror)]];
    }

    /** Dart replicateFloor: same layout under an ordinal label (ids are per floor, so reused as-is). */
    public static function replicateFloor(array $template, int $floorIndex): array
    {
        $ordinals = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th',
            '11th', '12th', '13th', '14th', '15th', '16th', '17th', '18th', '19th', '20th'];
        $label = $floorIndex >= 1 && $floorIndex <= count($ordinals)
            ? $ordinals[$floorIndex - 1] . ' Floor'
            : 'Floor ' . $floorIndex;
        return ['name' => $label, 'plan' => $template['plan']];
    }

    public static function roomCount(array $floor): int
    {
        return count($floor['plan']['rooms'] ?? []);
    }
}
