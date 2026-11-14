<?php
declare(strict_types=1);

/**
 * Description parsing for the AI floor plan creator.
 *
 * Port of lib/services/ai/floor_plan_request.dart: FloorPlanRequest (free text →
 * floor/bedroom counts, feature flags and fallback room lists) plus the
 * building-shape detectors and the per-floor description parser.
 *
 * A room "spec" is an array {id, name, type, x, y, w, h}; lengths in centimetres.
 */
final class AiPlanRequest
{
    public readonly string $raw;
    public readonly string $d;
    public readonly int $floors;
    public readonly int $bedrooms;
    public readonly int $kitchens;
    public readonly bool $hasMaster;
    public readonly bool $attachedBaths;
    public readonly bool $drawing;
    public readonly bool $tvLounge;
    public readonly bool $dining;
    public readonly bool $servant;
    public readonly bool $carPorch;
    public readonly bool $lawn;
    public readonly bool $balcony;
    public readonly bool $study;

    public function __construct(string $description)
    {
        $d = AiPlanText::lower($description);
        $this->raw           = $description;
        $this->d             = $d;
        $this->floors        = AiPlanText::detectFloorCount($description);
        $this->bedrooms      = self::extractBedCount($d);
        $this->kitchens      = self::extractKitchenCount($d);
        $this->hasMaster     = str_contains($d, 'master');
        $this->attachedBaths = str_contains($d, 'attached');
        $this->drawing       = str_contains($d, 'drawing');
        $this->tvLounge      = str_contains($d, 'tv') || str_contains($d, 'lounge');
        $this->dining        = str_contains($d, 'dining');
        $this->servant       = str_contains($d, 'servant');
        $this->carPorch      = str_contains($d, 'car porch') || str_contains($d, 'porch')
            || str_contains($d, 'parking') || str_contains($d, 'garage');
        $this->lawn          = str_contains($d, 'lawn') || str_contains($d, 'garden');
        $this->balcony       = str_contains($d, 'balcon');
        $this->study         = str_contains($d, 'study');
    }

    private static function extractBedCount(string $d): int
    {
        if (preg_match('/(\d+)\s*bed/', $d, $m)) {
            return AiPlanText::clampInt(AiPlanText::tryParseInt($m[1]) ?? 2, 1, 8);
        }
        if (str_contains($d, 'studio')) {
            return 0;
        }
        return 2;
    }

    private static function extractKitchenCount(string $d): int
    {
        if (preg_match('/(\d+)\s*kitchen/', $d, $m)) {
            return AiPlanText::clampInt(AiPlanText::tryParseInt($m[1]) ?? 1, 1, 3);
        }
        return 1;
    }

    /** @return list<array{id:string,name:string,type:string,w:int,h:int}> */
    public function groundFloorRooms(): array
    {
        $list = [];
        $i = 1;
        $id = static function () use (&$i): string {
            return 'gf' . $i++;
        };

        // Car porch: left side of front face (~58% of plot width hint)
        if ($this->carPorch) {
            $list[] = ['id' => $id(), 'name' => 'Car Porch', 'type' => 'car_porch', 'w' => 380, 'h' => 200];
        }
        // Entrance: right side of front face (~42% hint)
        $list[] = ['id' => $id(), 'name' => 'Entrance', 'type' => 'entrance', 'w' => 260, 'h' => 100];
        if ($this->drawing) {
            $list[] = ['id' => $id(), 'name' => 'Drawing Room', 'type' => 'drawing_room', 'w' => 300, 'h' => 240];
        } else {
            $list[] = ['id' => $id(), 'name' => 'Living Room', 'type' => 'living_room', 'w' => 320, 'h' => 240];
        }
        if ($this->tvLounge) {
            $list[] = ['id' => $id(), 'name' => 'TV Lounge', 'type' => 'tv_lounge', 'w' => 280, 'h' => 220];
        }
        if ($this->dining) {
            $list[] = ['id' => $id(), 'name' => 'Dining Room', 'type' => 'dining_room', 'w' => 240, 'h' => 200];
        }
        for ($k = 1; $k <= $this->kitchens; $k++) {
            $list[] = ['id' => $id(), 'name' => $this->kitchens > 1 ? 'Kitchen ' . $k : 'Kitchen',
                'type' => 'kitchen', 'w' => 220, 'h' => 200];
        }
        if ($this->servant) {
            $list[] = ['id' => $id(), 'name' => 'Servant Room', 'type' => 'servant_room', 'w' => 180, 'h' => 180];
            $list[] = ['id' => $id(), 'name' => 'Servant Bath', 'type' => 'bathroom', 'w' => 110, 'h' => 120];
        }
        if ($this->study) {
            $list[] = ['id' => $id(), 'name' => 'Study Room', 'type' => 'study_office', 'w' => 200, 'h' => 180];
        }

        if ($this->floors === 1) {
            for ($b = 1; $b <= $this->bedrooms; $b++) {
                $isMaster = $b === 1 && ($this->hasMaster || $this->bedrooms >= 3);
                $list[] = ['id' => $id(),
                    'name' => $isMaster ? 'Master Bedroom' : 'Bedroom ' . $b,
                    'type' => $isMaster ? 'master_bedroom' : 'bedroom',
                    'w' => $isMaster ? 260 : 230, 'h' => $isMaster ? 240 : 220];
                $list[] = ['id' => $id(),
                    'name' => $isMaster ? 'Master Bath' : 'Bathroom ' . $b,
                    'type' => 'bathroom', 'w' => 130, 'h' => 130];
            }
        } else {
            // Stairs for multi-floor: wide enough for a proper staircase
            $list[] = ['id' => $id(), 'name' => 'Stairs', 'type' => 'stairs', 'w' => 140, 'h' => 240];
        }
        // Lawn goes last → placed at the back of the floor plan
        if ($this->lawn) {
            $list[] = ['id' => $id(), 'name' => 'Lawn', 'type' => 'lawn', 'w' => 300, 'h' => 180];
        }
        return $list;
    }

    /** @return list<array{id:string,name:string,type:string,w:int,h:int}> */
    public function upperFloorRooms(): array
    {
        $list = [];
        $i = 1;
        $id = static function () use (&$i): string {
            return 'uf' . $i++;
        };

        $list[] = ['id' => $id(), 'name' => 'Corridor', 'type' => 'corridor', 'w' => 140, 'h' => 100];
        for ($b = 1; $b <= $this->bedrooms; $b++) {
            $isMaster = $b === 1 && ($this->hasMaster || $this->bedrooms >= 3);
            $list[] = ['id' => $id(),
                'name' => $isMaster ? 'Master Bedroom' : 'Bedroom ' . $b,
                'type' => $isMaster ? 'master_bedroom' : 'bedroom',
                'w' => $isMaster ? 260 : 230, 'h' => $isMaster ? 240 : 220];
            $list[] = ['id' => $id(),
                'name' => $this->attachedBaths ? 'Attached Bath ' . $b : 'Bathroom ' . $b,
                'type' => 'bathroom', 'w' => 130, 'h' => 130];
        }
        if ($this->balcony) {
            // 0 = full width via the layout rule
            $list[] = ['id' => $id(), 'name' => 'Balcony', 'type' => 'balcony', 'w' => 0, 'h' => 120];
        }
        $list[] = ['id' => $id(), 'name' => 'Stairs', 'type' => 'stairs', 'w' => 140, 'h' => 240];
        return $list;
    }
}

/**
 * Stateless text helpers: building-shape detection, plot size, per-floor parsing
 * and the free-text room name → typed room resolver.
 */
final class AiPlanText
{
    /** Upper bound on rooms produced from one "N rooms" phrase (server safety; Dart has none). */
    public const MAX_COUNT_PER_PART = 40;
    /** Upper bound on rooms parsed for one floor (server safety; Dart has none). */
    public const MAX_ROOMS_PER_FLOOR = 150;

    private const FLOOR_KEY = 'ground\s+floor|(?:\d+)(?:st|nd|rd|th)\s+floor|each\s+upper\s+floor|each\s+floor|upper\s+floor|roof(?:\s+floor)?';

    public static function lower(string $s): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    }

    /** Dart int.tryParse for a run of digits (null when it would overflow). */
    public static function tryParseInt(string $digits): ?int
    {
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }
        return strlen($digits) > 18 ? null : (int) $digits;
    }

    public static function clampInt(int $v, int $lo, int $hi): int
    {
        return max($lo, min($hi, $v));
    }

    public static function isLargeBuilding(string $desc): bool
    {
        $d = self::lower($desc);
        if (preg_match('/(\d+)[- ]?(?:floor|story|storey|level)/', $d, $m)
            && (self::tryParseInt($m[1]) ?? 0) >= 4) {
            return true;
        }
        return (str_contains($d, 'apartment') && (str_contains($d, 'building') || str_contains($d, 'complex')))
            || str_contains($d, 'residential building')
            || str_contains($d, 'commercial building')
            || preg_match('/multi[- ]?stor/', $d) === 1;
    }

    public static function hasPerFloorDescriptions(string $desc): bool
    {
        $pf = self::tryParsePerFloorDesc($desc);
        if ($pf === null || $pf === []) {
            return false;
        }
        return !self::isLargeBuilding($desc);
    }

    public static function detectFloorCount(string $desc): int
    {
        $d = self::lower($desc);
        if (preg_match('/(\d+)[- ]?(?:floor|story|storey|level)/', $d, $m)) {
            return self::clampInt(self::tryParseInt($m[1]) ?? 1, 1, 25);
        }
        if (str_contains($d, 'double stor') || str_contains($d, 'two stor')
            || preg_match('/\b2\s*stor/', $d) === 1 || str_contains($d, 'first floor')) {
            return 2;
        }
        return 1;
    }

    public static function hasRoofFloor(string $desc): bool
    {
        $d = self::lower($desc);
        return str_contains($d, 'roof') || str_contains($d, 'terrace') || str_contains($d, 'rooftop');
    }

    /**
     * Plot size inference (Marla/Kanal/sqft/commercial keywords), in cm.
     *
     * @return array{0: float, 1: float} [plotW, plotH]
     */
    public static function detectPlotSize(string $desc): array
    {
        $d = self::lower($desc);
        if (str_contains($d, 'school') || str_contains($d, 'college') || str_contains($d, 'university')) {
            return [1200.0, 900.0];
        }
        if (str_contains($d, 'hospital') || str_contains($d, 'clinic') || str_contains($d, 'medical')) {
            return [1100.0, 900.0];
        }
        if (str_contains($d, 'plaza') || str_contains($d, 'mall') || str_contains($d, 'shopping')) {
            return [1000.0, 800.0];
        }
        if (str_contains($d, 'office') && (str_contains($d, 'building') || str_contains($d, 'block'))) {
            return [900.0, 800.0];
        }
        if (str_contains($d, '2 kanal')) return [1200.0, 2000.0];
        if (str_contains($d, '1 kanal') || str_contains($d, 'one kanal')) return [1000.0, 1800.0];
        if (str_contains($d, '10 marla')) return [700.0, 1300.0];
        if (str_contains($d, '8 marla')) return [660.0, 1200.0];
        if (str_contains($d, '7 marla')) return [620.0, 1150.0];
        if (str_contains($d, '6 marla')) return [600.0, 1100.0];
        if (str_contains($d, '5 marla')) return [500.0, 1000.0];
        if (str_contains($d, '4 marla')) return [460.0, 920.0];
        if (str_contains($d, '3 marla')) return [400.0, 800.0];
        if (preg_match('/(\d+)\s*sqft/', $d, $m)) {
            $side = sqrt((float) $m[1]) * 20;
            return [$side * 0.5, $side];
        }
        return [600.0, 1200.0];
    }

    /** True for residential descriptions; false for explicit non-residential keywords. */
    public static function isResidentialBuilding(string $desc): bool
    {
        $d = self::lower($desc);
        foreach (['hospital', 'clinic', 'medical center', 'school', 'college', 'university', 'hotel',
                     'motel', 'guest house', 'mall', 'plaza', 'shopping', 'restaurant', 'cafe', 'cafeteria',
                     'factory', 'warehouse', 'workshop', 'gym', 'fitness', 'sports complex', 'library',
                     'museum', 'gallery', 'bank', 'commercial building'] as $kw) {
            if (str_contains($d, $kw)) {
                return false;
            }
        }
        if (str_contains($d, 'office')
            && (str_contains($d, 'building') || str_contains($d, 'block') || str_contains($d, 'complex'))) {
            return false;
        }
        // Dart returns true for both the explicit residential keywords and the default.
        return true;
    }

    /**
     * Split "Ground floor: X. 1st floor: Y. 2nd floor: Z" into a key → description map
     * (keys lower-cased, whitespace collapsed; insertion order kept).
     *
     * @return array<string, string>|null
     */
    public static function tryParsePerFloorDesc(string $description): ?array
    {
        $k = self::FLOOR_KEY;
        if (preg_match('/(?:' . $k . ')\s*:/i', $description) !== 1) {
            return null;
        }
        $re = '/(' . $k . ')\s*:\s*([^.]+?)(?=(?:ground\s+floor|(?:\d+)(?:st|nd|rd|th)\s+floor|'
            . 'each\s+upper\s+floor|each\s+floor|upper\s+floor|roof(?:\s+floor)?\s*:)|\.|$)/i';
        $result = [];
        if (preg_match_all($re, $description, $all, PREG_SET_ORDER)) {
            foreach ($all as $m) {
                $key = (string) preg_replace('/\s+/', ' ', self::lower(trim($m[1])));
                $result[$key] = trim($m[2]);
            }
        }
        return $result !== [] ? $result : null;
    }

    /**
     * Parse "6 shops, lobby, stairs, bathroom" into a flat list of room specs.
     *
     * @return list<array{id:string,name:string,type:string,x:float,y:float,w:float,h:float}>
     */
    public static function parseFloorDescription(string $floorDesc, string $prefix): array
    {
        $specs = [];
        $idx = 1;
        $parts = array_values(array_filter(
            array_map('trim', preg_split('/[,;]|\band\b/', $floorDesc) ?: []),
            static fn (string $p): bool => $p !== ''
        ));
        foreach ($parts as $part) {
            $r = self::resolveRoomType($part);
            $count = min($r['count'], self::MAX_COUNT_PER_PART);
            for ($c = 1; $c <= $count; $c++) {
                if (count($specs) >= self::MAX_ROOMS_PER_FLOOR) {
                    break 2;
                }
                $name = $count > 1 ? $r['baseName'] . ' ' . $c : $r['baseName'];
                $specs[] = AiPlanLayout::spec($prefix . '_r' . $idx++, $name, $r['type'], 0, 0, $r['w'], $r['h']);
            }
        }
        return $specs;
    }

    /** @return array{count:int,type:string,baseName:string,w:int,h:int} */
    public static function resolveRoomType(string $name): array
    {
        $n = trim(self::lower($name));
        if (preg_match('/^(\d+)\s+(.+)$/s', $n, $m)) {
            return ['count' => self::tryParseInt($m[1]) ?? PHP_INT_MAX] + self::singleRoomType($m[2]);
        }
        return ['count' => 1] + self::singleRoomType($n);
    }

    /** @return array{type:string,baseName:string,w:int,h:int} */
    private static function singleRoomType(string $n): array
    {
        $has = static function (string ...$needles) use ($n): bool {
            foreach ($needles as $needle) {
                if (str_contains($n, $needle)) {
                    return true;
                }
            }
            return false;
        };
        $t = static fn (string $type, string $base, int $w, int $h): array =>
            ['type' => $type, 'baseName' => $base, 'w' => $w, 'h' => $h];

        // Multi-word phrases that must beat single-word matches below.
        if ($has('walk-in wardrobe', 'walk in wardrobe', 'walk-in closet', 'walk in closet', 'wardrobe closet'))
            return $t('wardrobe', 'Wardrobe', 130, 160);
        if ($has('breakfast nook', 'breakfast')) return $t('dining_room', 'Breakfast Nook', 180, 160);
        if ($has('guest suite')) return $t('guest_room', 'Guest Suite', 240, 220);
        if ($has('sitting area', 'sitting room')) return $t('family_area', 'Sitting Area', 240, 200);
        if ($has('gallery')) return $t('corridor', 'Gallery', 160, 200);
        if ($has('laundry')) return $t('laundry', 'Laundry', 160, 160);
        if ($has('mudroom', 'mud room')) return $t('utility_room', 'Mud Room', 140, 140);
        if ($has('foyer', 'vestibule')) return $t('entrance', 'Foyer', 180, 120);
        if ($has('patio')) return $t('courtyard', 'Patio', 300, 200);
        if ($has('veranda', 'verandah')) return $t('balcony', 'Veranda', 280, 120);
        if ($has('wardrobe') || ($has('closet') && !$has('water closet')))
            return $t('wardrobe', 'Wardrobe', 120, 140);
        if ($has('study')) return $t('study_office', 'Study', 200, 180);
        if ($has('shop') || ($has('store') && !$has('store room'))) return $t('shop', 'Shop', 200, 180);
        if ($has('private office')) return $t('private_office', 'Office', 200, 180);
        if ($has('open office')) return $t('open_office', 'Open Office', 300, 220);
        if ($has('office')) return $t('office', 'Office', 200, 180);
        if ($has('classroom', 'class room')) return $t('classroom', 'Classroom', 260, 200);
        if ($has('large hall')) return $t('large_hall', 'Hall', 350, 260);
        if ($has('hall')) return $t('hall', 'Hall', 300, 240);
        if ($has('meeting')) return $t('meeting_room', 'Meeting Room', 240, 200);
        if ($has('reception')) return $t('reception', 'Reception', 200, 160);
        if ($has('lobby')) return $t('lobby', 'Lobby', 280, 200);
        if ($has('bathroom', 'washroom', 'wc', 'toilet')) return $t('bathroom', 'Bathroom', 120, 120);
        if ($has('kitchen')) return $t('kitchen', 'Kitchen', 200, 180);
        if ($has('pantry')) return $t('pantry', 'Pantry', 160, 140);
        if ($has('stair')) return $t('stairs', 'Stairs', 120, 220);
        if ($has('elevator', 'lift')) return $t('elevator', 'Elevator', 100, 100);
        // Car porch before entrance so "car porch" is not caught by the "porch" rule.
        if ($has('car porch', 'carport', 'garage')) return $t('car_porch', 'Car Porch', 380, 200);
        if ($has('main entrance', 'entrance', 'entry', 'porch')) return $t('entrance', 'Entrance', 220, 100);
        if ($has('car park', 'parking')) return $t('car_parking', 'Car Parking', 500, 200);
        if ($has('security')) return $t('security_room', 'Security Room', 160, 160);
        if ($has('utility')) return $t('utility_room', 'Utility Room', 160, 160);
        if ($has('store room', 'storage')) return $t('storage', 'Storage', 140, 140);
        if ($has('library')) return $t('library', 'Library', 280, 220);
        if ($has('computer lab')) return $t('computer_lab', 'Computer Lab', 260, 200);
        if ($has('lab')) return $t('lab', 'Lab', 240, 200);
        if ($has('teacher', 'staff room')) return $t('office', 'Teachers Room', 200, 180);
        if ($has('principal', 'director')) return $t('private_office', 'Principal Office', 200, 180);
        if ($has('playground')) return $t('courtyard', 'Playground', 400, 300);
        if ($has('prayer')) return $t('prayer_room', 'Prayer Room', 160, 180);
        if ($has('roof terrace', 'rooftop')) return $t('roof_terrace', 'Roof Terrace', 400, 300);
        if ($has('terrace')) return $t('terrace', 'Terrace', 350, 260);
        if ($has('corridor', 'hallway')) return $t('corridor', 'Corridor', 140, 180);
        if ($has('master bedroom')) return $t('master_bedroom', 'Master Bedroom', 240, 220);
        if ($has('bedroom')) return $t('bedroom', 'Bedroom', 220, 200);
        if ($has('dining')) return $t('dining_room', 'Dining Room', 220, 180);
        if ($has('living')) return $t('living_room', 'Living Room', 280, 220);
        if ($has('waiting')) return $t('waiting_area', 'Waiting Area', 240, 180);
        if ($has('pharmacy')) return $t('pharmacy', 'Pharmacy', 180, 160);
        if ($has('consultation', 'doctor')) return $t('consultation', 'Consultation', 200, 180);
        if ($has('emergency')) return $t('emergency_room', 'Emergency Room', 240, 200);
        if ($has('restaurant')) return $t('restaurant', 'Restaurant', 400, 280);
        if ($has('generator')) return $t('generator_room', 'Generator Room', 160, 160);
        if ($has('water tank')) return $t('water_tank', 'Water Tank', 140, 140);
        if ($has('servant')) return $t('servant_room', 'Servant Room', 160, 160);
        if ($has('balcony')) return $t('balcony', 'Balcony', 200, 100);
        if ($has('lawn', 'garden')) return $t('lawn', 'Lawn', 300, 180);
        if ($has('drawing')) return $t('drawing_room', 'Drawing Room', 280, 220);
        if ($has('tv lounge', 'tv_lounge')) return $t('tv_lounge', 'TV Lounge', 260, 200);
        if ($has('family', 'lounge')) return $t('family_area', 'Family Lounge', 280, 220);

        $base = 'Room';
        if ($n !== '') {
            $first = function_exists('mb_substr') ? mb_substr($n, 0, 1, 'UTF-8') : substr($n, 0, 1);
            $rest = function_exists('mb_substr') ? mb_substr($n, 1, null, 'UTF-8') : substr($n, 1);
            $base = (function_exists('mb_strtoupper') ? mb_strtoupper($first, 'UTF-8') : strtoupper($first)) . $rest;
            if (strlen($base) > 60) {
                $base = function_exists('mb_substr') ? mb_substr($base, 0, 60, 'UTF-8') : substr($base, 0, 60);
            }
        }
        return $t('other_space', $base, 200, 180);
    }
}
