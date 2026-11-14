<?php
declare(strict_types=1);

/**
 * AI floor plan analyzer: port of the Flutter app's analyzer
 * (lib/screens/ai_generator/floor_plan_analyzer_screen.dart, live code).
 *
 * The user uploads a photo or scan of a floor plan. A Groq vision model reads
 * it in up to three passes (same model, system prompt, prompts and token
 * budgets as the app):
 *   Pass 1  find the floor-plan boundary and every enclosed space (approximate ranges)
 *   Pass 2  tight wall-hugging bounding boxes, sizes, door/window flags per space
 *   Pass 3  only when pass 2 returned fewer rooms than pass 1 found: retry the missing ones
 * The rooms are then validated exactly like the app (bbox clamped to 0..100 and to
 * the floor-plan boundary, rooms under 1.5 % wide or tall dropped) and converted
 * into a web plan for the browser editor.
 *
 * PlanAnalyzer::analyze() returns ['result' => array, 'plan' => array].
 *
 * ── result (JSON-friendly, for the analyzer UI) ─────────────────────────────
 *   summary               string  One-line description, e.g.
 *                                 "6 rooms detected · residential · approx 120 sq m. 2 bedrooms, 1 bathroom, 1 kitchen."
 *   building_type         string  From the model, e.g. "residential", "commercial" (default "residential").
 *   overall_size          string  Model's own size text, e.g. "approx 200 sq m" (default "N/A").
 *   has_scale             bool    Model saw a scale bar / dimensions on the drawing.
 *   room_count            int     Number of rooms in `rooms` (app: "Total Rooms").
 *   total_walls           int     outer_walls + inner_walls (app: "Total Walls").
 *   outer_walls           int     Model's outer wall count (default 4).
 *   inner_walls           int     Model's inner wall count (default max(total_walls_reported − 4, 0)).
 *   counts                object  Grouped room counts: {bedrooms, bathrooms, kitchens, living_rooms,
 *                                 dining_rooms, outdoor, circulation, other} (ints; together = room_count).
 *                                 bedrooms = bedroom+master_bedroom+guest_room+servant_room;
 *                                 bathrooms = bathroom+toilet; living_rooms = living_room+drawing_room;
 *                                 outdoor = balcony, porch, garden, courtyard, roof_terrace, swimming_pool;
 *                                 circulation = hallway, corridor, stairs, entrance, lobby, reception.
 *   type_counts           list    The app's "Detected Spaces" legend, first-seen order:
 *                                 [{type, label, icon, color, count}] (same fields as on a room).
 *   unit                  string  "m" or "ft": unit of every room width/length below (majority of the
 *                                 units the model used; "m" when it gave none).
 *   area_unit             string  "m²" or "ft²": unit of every area below.
 *   total_area            float   Sum of room areas in area_unit (1 decimal).
 *   total_area_estimated  bool    True when at least one room area had to be estimated from the image.
 *   scale_calibrated      bool    True when the plan scale was derived from the model's room sizes;
 *                                 false = default scale (longer image side = 20 m), so estimates are rough.
 *   cm_per_px             float   Plan scale used: centimetres per image pixel.
 *   image                 object  {width, height}: image size in pixels as sent to the analyzer.
 *   floor_plan_box        object  {x, y, w, h}: building outline, normalised 0..1 of the image.
 *   passes                int     AI calls made (2 or 3).
 *   recovered_rooms       int     Rooms in `rooms` that came from the pass-3 retry (0 when it did not run).
 *   rooms                 list    One entry per detected room, in display order (number = index + 1):
 *     number        int      1-based badge number drawn on the overlay (as in the app).
 *     id            ?string  Id of the matching room in `plan.rooms` (null if too small for the editor).
 *     name          string   Room name as read from the plan, e.g. "Master Bedroom".
 *     type          string   Normalised type key (snake_case), e.g. "master_bedroom"; one of the 42
 *                            app classes, or whatever the model returned when unknown.
 *     label         string   Readable type name without emoji, e.g. "Master Bedroom".
 *     icon          string   Emoji the app shows next to the type, e.g. "🛏️" ("" when unknown).
 *     color         string   App overlay colour for the type (#rrggbb, vivid; draw boxes with ~28 % fill
 *                            opacity and a dashed ~85 % border as the app does).
 *     fill          string   Pastel colour the room gets in the editor plan (#rrggbb).
 *     width         float    Horizontal size in `unit` (2 decimals).
 *     length        float    Vertical size in `unit` (2 decimals).
 *     area          float    Area in `area_unit` (2 decimals).
 *     unit          string   Same as result.unit.
 *     area_unit     string   Same as result.area_unit.
 *     estimated     bool     True when width/length were measured from the image (model said N/A).
 *     width_text    string   Model's raw width text, e.g. "4.5m" or "N/A".
 *     length_text   string   Model's raw length text.
 *     area_text     string   Model's raw area text, e.g. "22.5 sq m".
 *     walls         int      Wall count the model reported for the room (default 4).
 *     has_door      bool     Model says the room has a door.
 *     has_window    bool     Model says the room has a window.
 *     box           object   {x, y, w, h}: room bounding box normalised 0..1 of the IMAGE (top-left
 *                            origin), ready to overlay: left = x*imgW, top = y*imgH, …
 *
 * ── plan (web editor format, see public/assets/app/editor.js) ──────────────
 *   {version: 1, name: "Analyzed plan", walls[], rooms[], openings[], items: [], labels: [], dims: []}
 *   centimetres, y grows downward, origin at the top-left of the building.
 */
final class PlanAnalyzer
{
    public const MODEL = 'meta-llama/llama-4-scout-17b-16e-instruct';

    private const SYSTEM_PROMPT = 'You are an expert architectural floor plan analyst. '
        . 'You output ONLY valid JSON — no markdown, no backticks, no comments. '
        . 'Bounding boxes are percentage coordinates of the IMAGE (0=left/top, 100=right/bottom). '
        . 'RULE: bbox must fit EXACTLY inside the room walls — never extend past them. '
        . 'Adjacent rooms share a wall so their bboxes nearly touch but do NOT overlap. '
        . 'All four bbox values (x1,y1,x2,y2) must be between 0 and 100. '
        . 'Your JSON must start with { and end with }.';

    private const TEMPERATURE = 0.05;
    private const MAX_SPACES = 60;

    /** Editor conversion (app: 2000-unit canvas, 15 cm room walls). */
    private const CANVAS_CM = 2000.0;
    private const WALL_T = 15;
    /** Edges of different rooms closer than this (% of the image) are snapped onto one wall line. */
    private const SNAP_PCT = 1.2;

    private const NO_ROOMS = "Couldn't find rooms in this image. Try a clearer, straight-on photo of the plan.";

    /** Editor palette (same as editor.js COLORS). */
    private const PLAN_COLORS = ['#e3f2fd', '#e8f5e9', '#fff3e0', '#fce4ec', '#ede7f6', '#e0f7fa', '#f1f8e9', '#eceff1'];

    /** Type → index into PLAN_COLORS (unknown types: crc32 of the type). */
    private const PLAN_COLOR_BY_TYPE = [
        'bedroom' => 0, 'master_bedroom' => 0, 'guest_room' => 0, 'servant_room' => 0,
        'balcony' => 1, 'porch' => 1, 'garden' => 1, 'courtyard' => 1, 'roof_terrace' => 1,
        'kitchen' => 2, 'dining_room' => 2, 'cafeteria' => 2, 'break_room' => 2,
        'living_room' => 3, 'drawing_room' => 3, 'gym' => 3, 'prayer_room' => 3,
        'hallway' => 4, 'corridor' => 4, 'stairs' => 4, 'entrance' => 4, 'lobby' => 4, 'reception' => 4,
        'bathroom' => 5, 'toilet' => 5, 'swimming_pool' => 5,
        'study_office' => 6, 'open_office' => 6, 'private_office' => 6, 'meeting_room' => 6, 'conference_room' => 6,
        'garage' => 7, 'car_parking' => 7, 'storage' => 7, 'storage_laundry' => 7, 'closet' => 7,
        'utility_room' => 7, 'basement' => 7, 'server_room' => 7, 'security_room' => 7, 'print_room' => 7,
        'other_space' => 7,
    ];

    /** App overlay colours and labels (_kRoomColors / _kRoomLabels): type => [colour, icon, label]. */
    private const TYPES = [
        'living_room'     => ['#FFB347', '🛋️', 'Living Room'],
        'drawing_room'    => ['#FF9B32', '🛋️', 'Drawing Room'],
        'bedroom'         => ['#77DD77', '🛏️', 'Bedroom'],
        'master_bedroom'  => ['#46BE46', '🛏️', 'Master Bedroom'],
        'guest_room'      => ['#A0E6A0', '🛏️', 'Guest Room'],
        'servant_room'    => ['#AAC8AA', '🛏️', 'Servant Room'],
        'bathroom'        => ['#87CEEB', '🚿', 'Bathroom'],
        'toilet'          => ['#5AAAFF', '🚽', 'Toilet'],
        'kitchen'         => ['#FF6961', '🍳', 'Kitchen'],
        'dining_room'     => ['#FFDEB9', '🍽️', 'Dining Room'],
        'hallway'         => ['#C8A2C8', '🚶', 'Hallway'],
        'corridor'        => ['#DCB4DC', '🔀', 'Corridor'],
        'stairs'          => ['#FFF03C', '🪜', 'Stairs'],
        'garage'          => ['#969696', '🚗', 'Garage'],
        'car_parking'     => ['#646464', '🅿️', 'Car Parking'],
        'balcony'         => ['#90EE90', '🌿', 'Balcony'],
        'porch'           => ['#A0F5A0', '🏡', 'Porch'],
        'entrance'        => ['#FFC832', '🚪', 'Entrance'],
        'garden'          => ['#46AA46', '🌳', 'Garden'],
        'courtyard'       => ['#55B955', '🏛️', 'Courtyard'],
        'swimming_pool'   => ['#32B4FF', '🏊', 'Swimming Pool'],
        'gym'             => ['#FF8C50', '💪', 'Gym'],
        'prayer_room'     => ['#D2B4FF', '🕌', 'Prayer Room'],
        'basement'        => ['#9B877D', '⬇️', 'Basement'],
        'roof_terrace'    => ['#AFD7AF', '🏙️', 'Roof Terrace'],
        'study_office'    => ['#ADD8E6', '💼', 'Study/Office'],
        'storage_laundry' => ['#D2B48C', '🧺', 'Storage/Laundry'],
        'closet'          => ['#E6C8AA', '🗄️', 'Closet'],
        'utility_room'    => ['#C8DCC8', '🔧', 'Utility Room'],
        'other_space'     => ['#D2D2D2', '📦', 'Other Space'],
        'open_office'     => ['#FFC864', '🖥️', 'Open Office'],
        'private_office'  => ['#64C896', '🚪', 'Private Office'],
        'meeting_room'    => ['#64B4E6', '👥', 'Meeting Room'],
        'reception'       => ['#FFC896', '🛎️', 'Reception'],
        'storage'         => ['#C8AA82', '📦', 'Storage'],
        'server_room'     => ['#B4D2B4', '🖥️', 'Server Room'],
        'lobby'           => ['#BE9BC8', '🏛️', 'Lobby'],
        'conference_room' => ['#9BD2E6', '📋', 'Conference Room'],
        'cafeteria'       => ['#FFA050', '☕', 'Cafeteria'],
        'break_room'      => ['#FFDC78', '🍵', 'Break Room'],
        'security_room'   => ['#C86464', '🔒', 'Security Room'],
        'print_room'      => ['#B4C8DC', '🖨️', 'Print Room'],
    ];

    private const COUNT_GROUPS = [
        'bedrooms'     => ['bedroom', 'master_bedroom', 'guest_room', 'servant_room'],
        'bathrooms'    => ['bathroom', 'toilet'],
        'kitchens'     => ['kitchen'],
        'living_rooms' => ['living_room', 'drawing_room'],
        'dining_rooms' => ['dining_room'],
        'outdoor'      => ['balcony', 'porch', 'garden', 'courtyard', 'roof_terrace', 'swimming_pool'],
        'circulation'  => ['hallway', 'corridor', 'stairs', 'entrance', 'lobby', 'reception'],
    ];

    /** Rooms a door preferably opens onto. */
    private const DOOR_TARGETS = ['hallway', 'corridor', 'living_room', 'drawing_room', 'lobby', 'entrance', 'reception', 'dining_room'];

    // ─────────────────────────────────────────────────────────── prompts (verbatim from the app)

    private const PASS1_PROMPT = <<<'TXT'

Analyze this architectural floor plan image carefully.

STEP 1 — Find the EXACT floor plan boundary:
  Identify the outermost walls of the building.
  Report them as floor_plan_bbox: {x1, y1, x2, y2} in % of the FULL image.
  Everything outside this boundary is title block, dimensions, north arrow — NOT rooms.

STEP 2 — Detect ALL enclosed spaces inside the floor plan boundary:
  Scan every enclosed area bounded by walls. Read every text label.
  Specifically check for:
  • Stairs (parallel-line hatch pattern in rectangle)
  • Corridors / hallways (narrow connecting spaces)
  • Bathrooms / WC / toilets (small rooms, plumbing symbols)
  • Closets / wardrobes (small rectangles against bedroom walls)
  • Garage / car parking (large rectangle, may show car outline)
  • Balcony / porch / verandah (attached to outer wall, different fill)
  • Utility / laundry / store (small corner rooms)
  • Any labeled or unlabeled enclosed space

STEP 3 — For each space give an APPROXIMATE position:
  x_range: "leftPct-rightPct"  (integers, relative to full image)
  y_range: "topPct-bottomPct"  (integers, relative to full image)
  These need only be ±5% accurate — Pass 2 will refine them.

Available type values:
living_room, drawing_room, bedroom, master_bedroom, guest_room, servant_room,
bathroom, toilet, kitchen, dining_room, hallway, corridor, stairs, garage,
car_parking, balcony, porch, entrance, garden, courtyard, swimming_pool,
gym, prayer_room, basement, roof_terrace, study_office, storage_laundry,
closet, utility_room, other_space, open_office, private_office, meeting_room,
reception, storage, server_room, lobby, conference_room, cafeteria, break_room,
security_room, print_room

Return ONLY valid JSON:
{
  "building_type": "residential",
  "has_scale": false,
  "overall_size": "approx 200 sq m",
  "total_walls": 28,
  "wall_analysis": {"outer_walls": 8, "inner_walls": 20},
  "floor_plan_bbox": {"x1": 5, "y1": 8, "x2": 92, "y2": 90},
  "spaces": [
    {"name": "Living Room", "type": "living_room",  "x_range": "5-48",  "y_range": "10-55"},
    {"name": "Kitchen",     "type": "kitchen",      "x_range": "48-70", "y_range": "10-38"},
    {"name": "Staircase",   "type": "stairs",       "x_range": "48-58", "y_range": "38-55"}
  ]
}

TXT;

    private const PASS2_PROMPT = <<<'TXT'

You are a precision floor plan analyst.

COORDINATE SYSTEM: (0,0)=top-left of full image, (100,100)=bottom-right.
The floor plan itself occupies x:{bx1}–{bx2}%, y:{by1}–{by2}% of the image.
ALL bbox values must be WITHIN the floor plan area (x: {bx1}–{bx2}, y: {by1}–{by2}).

I have {n} spaces. Give a TIGHT bounding box for each — edges must sit ON the wall lines.

CRITICAL RULES:
1. bbox edge = the center of the wall line (not outside it)
2. Adjacent rooms share a wall → their edges differ by only ~0.5–1%
3. No bbox may extend outside the floor plan boundary ({bx1},{by1})→({bx2},{by2})
4. All values 0–100 (image percentages)
5. x2 > x1, y2 > y1 always

SPACES TO LOCATE:
{lines}

Return exactly {n} objects in "rooms" array (same order). ONLY valid JSON:
{
  "rooms": [
    {
      "name": "Living Room",
      "type": "living_room",
      "width": "4.5m",
      "length": "5.0m",
      "area": "22.5 sq m",
      "walls": 4,
      "has_window": true,
      "has_door": true,
      "bbox": {"x1": 12.5, "y1": 18.0, "x2": 38.0, "y2": 47.5}
    }
  ]
}

TXT;

    private const RETRY_PROMPT = <<<'TXT'

Floor plan boundary: x:{bx1}–{bx2}%, y:{by1}–{by2}%.
Find TIGHT bounding boxes for these {n} missed spaces:

{lines}

Rules: edges on wall lines, within floor plan boundary, no overlaps.
Return ONLY valid JSON:
{"rooms": [{"name":"...","type":"...","width":"N/A","length":"N/A",
"area":"N/A","walls":4,"has_window":false,"has_door":true,
"bbox":{"x1":0,"y1":0,"x2":10,"y2":10}}]}

TXT;

    // ─────────────────────────────────────────────────────────── analysis

    /**
     * Analyze a floor plan image.
     *
     * @param string $jpegBytes Raw image bytes (JPEG; PNG/WebP also work when $mime says so).
     * @return array{result: array<string, mixed>, plan: array<string, mixed>}
     * @throws GroqException 503 not configured, 429 rate limit, 502 upstream error,
     *                       422 when no rooms could be found.
     */
    public static function analyze(string $jpegBytes, int $imgWidth, int $imgHeight, string $mime = 'image/jpeg'): array
    {
        if ($imgWidth <= 0 || $imgHeight <= 0 || $jpegBytes === '') {
            throw new GroqException('The image could not be read.', 400);
        }
        if (!GroqClient::configured()) {
            throw new GroqException('AI features are not configured on this server yet.', 503);
        }
        GroqClient::assertWithinRateLimit(2); // passes 1 and 2 always run

        $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($jpegBytes);
        $passes = 0;

        // ── Pass 1: all spaces + floor plan boundary ──
        $pass1 = self::call(self::PASS1_PROMPT, $dataUrl, 2500);
        $passes++;
        $spaces = array_values(array_filter((array) ($pass1['spaces'] ?? []), 'is_array'));
        $spaces = array_slice($spaces, 0, self::MAX_SPACES);
        if ($spaces === []) {
            throw new GroqException(self::NO_ROOMS, 422);
        }
        $fp = self::floorPlanBox($pass1['floor_plan_bbox'] ?? null);

        // ── Pass 2: precise bounding boxes ──
        $pass2 = self::call(self::pass2Prompt($spaces, $fp), $dataUrl, 5000);
        $passes++;
        $rawRooms = array_values(array_filter((array) ($pass2['rooms'] ?? []), 'is_array'));

        // ── Pass 3: retry rooms pass 2 missed ──
        $retryError = null;
        if (count($rawRooms) < count($spaces)) {
            $detected = [];
            foreach ($rawRooms as $r) {
                $w = self::firstWord($r['name'] ?? '');
                if ($w !== '') {
                    $detected[$w] = true;
                }
            }
            $missing = [];
            foreach ($spaces as $s) {
                $n = self::firstWord($s['name'] ?? '');
                $found = false;
                foreach (array_keys($detected) as $d) {
                    $d = (string) $d;
                    if (str_contains($d, $n) || str_contains($n, $d)) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $missing[] = $s;
                }
            }
            if ($missing !== []) {
                try {
                    $p3 = self::call(self::retryPrompt($missing, $fp), $dataUrl, 2500);
                    $passes++;
                    foreach ((array) ($p3['rooms'] ?? []) as $extra) {
                        if (is_array($extra)) {
                            $rawRooms[] = ['_retry' => true] + $extra;
                        }
                    }
                } catch (GroqException $e) {
                    $passes++;
                    $retryError = $e; // proceed with what we have (as the app does)
                }
            }
        }

        // ── Build & validate rooms ──
        $rooms = [];
        foreach ($rawRooms as $r) {
            $room = self::roomFromJson($r);
            $room['x1'] = self::clamp($room['x1'], $fp['x1'], $fp['x2']);
            $room['x2'] = self::clamp($room['x2'], $fp['x1'], $fp['x2']);
            $room['y1'] = self::clamp($room['y1'], $fp['y1'], $fp['y2']);
            $room['y2'] = self::clamp($room['y2'], $fp['y1'], $fp['y2']);
            if ($room['x2'] - $room['x1'] >= 1.5 && $room['y2'] - $room['y1'] >= 1.5) {
                $rooms[] = $room;
            }
        }
        $rooms = self::dedupe($rooms);
        if ($rooms === []) {
            if ($retryError !== null && $retryError->status !== 502) {
                throw $retryError; // e.g. rate limit hit during the retry
            }
            throw new GroqException(self::NO_ROOMS, 422);
        }
        $recovered = count(array_filter($rooms, static fn (array $r): bool => $r['retry']));

        // Wall statistics (app logic)
        $wa = is_array($pass1['wall_analysis'] ?? null) ? $pass1['wall_analysis'] : [];
        $tw = self::intOr($pass1['total_walls'] ?? null, count($rooms) * 4);
        $outer = self::intOr($wa['outer_walls'] ?? null, 4);
        $inner = self::intOr($wa['inner_walls'] ?? null, max(0, $tw - 4));

        $meta = [
            'building_type' => self::str($pass1['building_type'] ?? null, 'residential'),
            'overall_size'  => self::str($pass1['overall_size'] ?? null, 'N/A'),
            'has_scale'     => ($pass1['has_scale'] ?? false) === true,
            'outer_walls'   => $outer,
            'inner_walls'   => $inner,
            'passes'        => $passes,
            'recovered'     => $recovered,
        ];
        return self::build($rooms, $fp, $imgWidth, $imgHeight, $meta);
    }

    /** One vision call; returns the parsed JSON object ([] when the reply is not JSON). */
    private static function call(string $prompt, string $dataUrl, int $maxTokens): array
    {
        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => ['url' => $dataUrl, 'detail' => 'high']],
            ]],
        ];
        $text = GroqClient::chat($messages, $maxTokens, self::TEMPERATURE, self::MODEL);
        return GroqClient::parseJsonObject($text);
    }

    /**
     * Pure post-processing (no AI): validated rooms → result + plan. Public for tests.
     *
     * @param list<array<string, mixed>> $rooms  rooms as returned by roomFromJson() (bbox in %)
     * @param array{x1: float, y1: float, x2: float, y2: float} $fp floor plan bbox in %
     * @param array<string, mixed> $meta building_type, overall_size, has_scale, outer_walls, inner_walls, passes, recovered
     * @return array{result: array<string, mixed>, plan: array<string, mixed>}
     */
    public static function build(array $rooms, array $fp, int $imgW, int $imgH, array $meta): array
    {
        // Sizes the model reported, in metres.
        $families = ['m' => 0, 'ft' => 0];
        foreach ($rooms as &$room) {
            $w = self::parseLength($room['width']);
            $l = self::parseLength($room['length']);
            $a = self::parseArea($room['area']);
            foreach ([$w, $l, $a] as $p) {
                if ($p !== null) {
                    $families[$p[1]]++;
                }
            }
            $room['w_m'] = $w[0] ?? null;
            $room['l_m'] = $l[0] ?? null;
            $room['a_m2'] = $a[0] ?? null;
        }
        unset($room);
        $imperial = $families['ft'] > $families['m'];

        [$cmPerPx, $calibrated] = self::scale($rooms, $imgW, $imgH);
        $plan = self::toPlan($rooms, $imgW, $imgH, $cmPerPx);

        $lenF = $imperial ? 1 / 0.3048 : 1.0;
        $areaF = $imperial ? 1 / 0.09290304 : 1.0;
        $unit = $imperial ? 'ft' : 'm';
        $areaUnit = $imperial ? 'ft²' : 'm²';

        $out = [];
        $typeCounts = [];
        $counts = array_fill_keys(array_keys(self::COUNT_GROUPS), 0) + ['other' => 0];
        $total = 0.0;
        $anyEstimated = false;
        foreach ($rooms as $i => $r) {
            $geoW = ($r['x2'] - $r['x1']) / 100 * $imgW * $cmPerPx / 100; // metres
            $geoL = ($r['y2'] - $r['y1']) / 100 * $imgH * $cmPerPx / 100;
            $estimated = $r['w_m'] === null || $r['l_m'] === null;
            $wM = $r['w_m'] ?? $geoW;
            $lM = $r['l_m'] ?? $geoL;
            $aM = $r['a_m2'] ?? $wM * $lM;
            $anyEstimated = $anyEstimated || ($r['a_m2'] === null && $estimated);
            $area = round($aM * $areaF, 2);
            $total += $area;

            $info = self::typeInfo($r['type']);
            if (!isset($typeCounts[$r['type']])) {
                $typeCounts[$r['type']] = $info + ['type' => $r['type'], 'count' => 0];
            }
            $typeCounts[$r['type']]['count']++;
            $group = 'other';
            foreach (self::COUNT_GROUPS as $g => $types) {
                if (in_array($r['type'], $types, true)) {
                    $group = $g;
                    break;
                }
            }
            $counts[$group]++;

            $out[] = [
                'number'      => $i + 1,
                'id'          => $plan['room_ids'][$i] ?? null,
                'name'        => $r['name'],
                'type'        => $r['type'],
                'label'       => $info['label'],
                'icon'        => $info['icon'],
                'color'       => $info['color'],
                'fill'        => self::planColor($r['type']),
                'width'       => round($wM * $lenF, 2),
                'length'      => round($lM * $lenF, 2),
                'area'        => $area,
                'unit'        => $unit,
                'area_unit'   => $areaUnit,
                'estimated'   => $estimated,
                'width_text'  => $r['width'],
                'length_text' => $r['length'],
                'area_text'   => $r['area'],
                'walls'       => $r['walls'],
                'has_door'    => $r['has_door'],
                'has_window'  => $r['has_window'],
                'box'         => self::normBox($r['x1'], $r['y1'], $r['x2'], $r['y2']),
            ];
        }
        $typeList = [];
        foreach ($typeCounts as $t) {
            $typeList[] = ['type' => $t['type'], 'label' => $t['label'], 'icon' => $t['icon'], 'color' => $t['color'], 'count' => $t['count']];
        }

        $n = count($out);
        $sizeText = $meta['overall_size'] !== 'N/A' && $meta['overall_size'] !== ''
            ? $meta['overall_size']
            : '~' . self::fmtNum($total) . ' ' . $areaUnit;
        $parts = [];
        foreach (['bedrooms' => ['bedroom', 'bedrooms'], 'bathrooms' => ['bathroom', 'bathrooms'], 'kitchens' => ['kitchen', 'kitchens']] as $k => [$one, $many]) {
            if ($counts[$k] > 0) {
                $parts[] = $counts[$k] . ' ' . ($counts[$k] === 1 ? $one : $many);
            }
        }
        $summary = $n . ' room' . ($n === 1 ? '' : 's') . ' detected · ' . $meta['building_type'] . ' · ' . $sizeText . '.'
            . ($parts !== [] ? ' ' . ucfirst(implode(', ', $parts)) . '.' : '');

        $result = [
            'summary'              => $summary,
            'building_type'        => $meta['building_type'],
            'overall_size'         => $meta['overall_size'],
            'has_scale'            => (bool) $meta['has_scale'],
            'room_count'           => $n,
            'total_walls'          => (int) $meta['outer_walls'] + (int) $meta['inner_walls'],
            'outer_walls'          => (int) $meta['outer_walls'],
            'inner_walls'          => (int) $meta['inner_walls'],
            'counts'               => $counts,
            'type_counts'          => $typeList,
            'unit'                 => $unit,
            'area_unit'            => $areaUnit,
            'total_area'           => round($total, 1),
            'total_area_estimated' => $anyEstimated,
            'scale_calibrated'     => $calibrated,
            'cm_per_px'            => round($cmPerPx, 4),
            'image'                => ['width' => $imgW, 'height' => $imgH],
            'floor_plan_box'       => self::normBox($fp['x1'], $fp['y1'], $fp['x2'], $fp['y2']),
            'passes'               => (int) $meta['passes'],
            'recovered_rooms'      => (int) $meta['recovered'],
            'rooms'                => $out,
        ];
        unset($plan['room_ids']);
        return ['result' => $result, 'plan' => $plan];
    }

    // ─────────────────────────────────────────────────────────── prompt builders

    /** @param list<array<string, mixed>> $spaces */
    private static function spaceLines(array $spaces): string
    {
        $lines = [];
        foreach ($spaces as $i => $s) {
            $lines[] = '  ' . ($i + 1) . '. name="' . self::str($s['name'] ?? null, 'null', false) . '" type='
                . self::str($s['type'] ?? null, 'null', false)
                . ' approx_x=' . self::str($s['x_range'] ?? null, '0-100', false) . '%'
                . ' approx_y=' . self::str($s['y_range'] ?? null, '0-100', false) . '%';
        }
        return implode("\n", $lines);
    }

    /** @param list<array<string, mixed>> $spaces */
    private static function pass2Prompt(array $spaces, array $fp): string
    {
        return strtr(self::PASS2_PROMPT, [
            '{bx1}' => (string) (int) $fp['x1'], '{by1}' => (string) (int) $fp['y1'],
            '{bx2}' => (string) (int) $fp['x2'], '{by2}' => (string) (int) $fp['y2'],
            '{n}' => (string) count($spaces), '{lines}' => self::spaceLines($spaces),
        ]);
    }

    /** @param list<array<string, mixed>> $missing */
    private static function retryPrompt(array $missing, array $fp): string
    {
        return strtr(self::RETRY_PROMPT, [
            '{bx1}' => (string) (int) $fp['x1'], '{by1}' => (string) (int) $fp['y1'],
            '{bx2}' => (string) (int) $fp['x2'], '{by2}' => (string) (int) $fp['y2'],
            '{n}' => (string) count($missing), '{lines}' => self::spaceLines($missing),
        ]);
    }

    // ─────────────────────────────────────────────────────────── parsing helpers

    /** RoomData.fromJson: bbox clamped to 0..100 and ordered. */
    private static function roomFromJson(array $j): array
    {
        $b = is_array($j['bbox'] ?? null) ? $j['bbox'] : [];
        $d = static fn (string $k): float => self::clamp(self::num($b[$k] ?? null) ?? 0.0, 0.0, 100.0);
        [$x1, $x2] = [min($d('x1'), $d('x2')), max($d('x1'), $d('x2'))];
        [$y1, $y2] = [min($d('y1'), $d('y2')), max($d('y1'), $d('y2'))];
        return [
            'name'       => self::str($j['name'] ?? null, 'Room'),
            'type'       => self::normType(self::str($j['type'] ?? null, 'other_space')),
            'width'      => self::str($j['width'] ?? null, 'N/A'),
            'length'     => self::str($j['length'] ?? null, 'N/A'),
            'area'       => self::str($j['area'] ?? null, 'N/A'),
            'walls'      => self::intOr($j['walls'] ?? null, 4),
            'has_window' => ($j['has_window'] ?? false) === true,
            'has_door'   => ($j['has_door'] ?? false) === true,
            'retry'      => ($j['_retry'] ?? false) === true,
            'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2,
        ];
    }

    /** floor_plan_bbox in %, defaults {2,2,98,98}; falls back to the whole image when degenerate. */
    private static function floorPlanBox(mixed $raw): array
    {
        $b = is_array($raw) ? $raw : ['x1' => 2, 'y1' => 2, 'x2' => 98, 'y2' => 98];
        $x1 = self::clamp(self::num($b['x1'] ?? null) ?? 0.0, 0, 100);
        $y1 = self::clamp(self::num($b['y1'] ?? null) ?? 0.0, 0, 100);
        $x2 = self::clamp(self::num($b['x2'] ?? null) ?? 100.0, 0, 100);
        $y2 = self::clamp(self::num($b['y2'] ?? null) ?? 100.0, 0, 100);
        [$x1, $x2] = [min($x1, $x2), max($x1, $x2)];
        [$y1, $y2] = [min($y1, $y2), max($y1, $y2)];
        if ($x2 - $x1 < 10 || $y2 - $y1 < 10) {
            return ['x1' => 0.0, 'y1' => 0.0, 'x2' => 100.0, 'y2' => 100.0];
        }
        return ['x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2];
    }

    /** Drop near-identical boxes (pass 3 sometimes repeats a room pass 2 already found). */
    private static function dedupe(array $rooms): array
    {
        $keep = [];
        foreach ($rooms as $r) {
            foreach ($keep as $k) {
                $iou = self::iou($r, $k);
                $same = $r['type'] === $k['type'] || strtolower($r['name']) === strtolower($k['name']);
                if ($iou >= 0.9 || ($same && $iou >= 0.75)) {
                    continue 2;
                }
            }
            $keep[] = $r;
        }
        return $keep;
    }

    private static function iou(array $a, array $b): float
    {
        $iw = min($a['x2'], $b['x2']) - max($a['x1'], $b['x1']);
        $ih = min($a['y2'], $b['y2']) - max($a['y1'], $b['y1']);
        if ($iw <= 0 || $ih <= 0) {
            return 0.0;
        }
        $inter = $iw * $ih;
        $union = ($a['x2'] - $a['x1']) * ($a['y2'] - $a['y1']) + ($b['x2'] - $b['x1']) * ($b['y2'] - $b['y1']) - $inter;
        return $union > 0 ? $inter / $union : 0.0;
    }

    private static function firstWord(mixed $name): string
    {
        $s = strtolower(trim(is_scalar($name) ? (string) $name : ''));
        return explode(' ', $s)[0];
    }

    private static function normType(string $t): string
    {
        $t = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($t)), '_');
        return $t === '' ? 'other_space' : $t;
    }

    /** @return array{color: string, icon: string, label: string} */
    private static function typeInfo(string $type): array
    {
        if (isset(self::TYPES[$type])) {
            [$c, $i, $l] = self::TYPES[$type];
            return ['color' => strtolower($c), 'icon' => $i, 'label' => $l];
        }
        return ['color' => '#d2d2d2', 'icon' => '', 'label' => ucwords(str_replace('_', ' ', $type))];
    }

    private static function planColor(string $type): string
    {
        $i = self::PLAN_COLOR_BY_TYPE[$type] ?? (crc32($type) % count(self::PLAN_COLORS));
        return self::PLAN_COLORS[$i];
    }

    /**
     * "4.5m", "450 cm", "15'", "12' 6\"", "15 ft" → [metres, 'm'|'ft'], null when unknown.
     *
     * @return array{0: float, 1: string}|null
     */
    private static function parseLength(string $s): ?array
    {
        $s = strtolower(str_replace(['’', '′', '”', '″'], ["'", "'", '"', '"'], $s));
        $s = (string) preg_replace('/(?<=\d),(?=\d)/', '.', $s);
        $v = null;
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:\'|ft\b\.?|feet|foot)\s*(?:(\d+(?:\.\d+)?)\s*(?:"|in\b|inch))?/', $s, $m)) {
            $v = [(float) $m[1] * 0.3048 + (isset($m[2]) && $m[2] !== '' ? (float) $m[2] * 0.0254 : 0.0), 'ft'];
        } elseif (preg_match('/(\d+(?:\.\d+)?)\s*(?:"|in\b|inch)/', $s, $m)) {
            $v = [(float) $m[1] * 0.0254, 'ft'];
        } elseif (preg_match('/(\d+(?:\.\d+)?)\s*(mm|cm|met(?:er|re)s?|m)\b/', $s, $m)) {
            $f = ['mm' => 0.001, 'cm' => 0.01][$m[2]] ?? 1.0;
            $v = [(float) $m[1] * $f, 'm'];
        }
        return $v !== null && $v[0] > 0.2 && $v[0] < 200 ? $v : null;
    }

    /**
     * "22.5 sq m", "22.5 m²", "240 sq ft", "1,200 sqft" → [m², 'm'|'ft'], null when unknown.
     *
     * @return array{0: float, 1: string}|null
     */
    private static function parseArea(string $s): ?array
    {
        $s = strtolower((string) preg_replace('/(?<=\d),(?=\d{3}\b)/', '', $s));
        $v = null;
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:sq\.?\s*(?:ft|feet|foot)|ft²|ft2|sqft|square\s*f(?:ee|oo)t)/u', $s, $m)) {
            $v = [(float) $m[1] * 0.09290304, 'ft'];
        } elseif (preg_match('/(\d+(?:\.\d+)?)\s*(?:sq\.?\s*m(?:et(?:er|re)s?)?\b|m²|m2\b|sqm\b|square\s*met(?:er|re)s?)/u', $s, $m)) {
            $v = [(float) $m[1], 'm'];
        }
        return $v !== null && $v[0] > 0.05 && $v[0] < 20000 ? $v : null;
    }

    // ─────────────────────────────────────────────────────────── image → plan

    /**
     * Centimetres per image pixel. Default: the app's 2000 cm canvas on the longer image
     * side. Calibrated from the model's room sizes when they agree with each other.
     *
     * @return array{0: float, 1: bool}
     */
    private static function scale(array $rooms, int $imgW, int $imgH): array
    {
        $default = self::CANVAS_CM / max($imgW, $imgH);
        $est = [];
        foreach ($rooms as $r) {
            if ($r['w_m'] === null || $r['l_m'] === null) {
                continue;
            }
            $px = [($r['x2'] - $r['x1']) / 100 * $imgW, ($r['y2'] - $r['y1']) / 100 * $imgH];
            $m = [$r['w_m'], $r['l_m']];
            sort($px);
            sort($m);
            if ($px[0] < 8) {
                continue;
            }
            $est[] = $m[0] * 100 / $px[0];
            $est[] = $m[1] * 100 / $px[1];
        }
        if (count($est) < 2) {
            return [$default, false];
        }
        $med = self::median($est);
        $dev = self::median(array_map(static fn (float $e): float => abs($e - $med) / $med, $est));
        $longest = $med * max($imgW, $imgH);
        if ($dev > 0.35 || $longest < 300 || $longest > 20000) {
            return [$default, false];
        }
        return [$med, true];
    }

    /**
     * Rooms (bbox %) → web editor plan. Edges of neighbouring rooms are snapped onto shared
     * lines, room outlines become walls with overlapping collinear segments merged, and doors /
     * windows are placed from the model's has_door / has_window flags.
     */
    private static function toPlan(array $rooms, int $imgW, int $imgH, float $cmPerPx): array
    {
        $xs = self::snapValues(array_merge(array_column($rooms, 'x1'), array_column($rooms, 'x2')));
        $ys = self::snapValues(array_merge(array_column($rooms, 'y1'), array_column($rooms, 'y2')));
        $kx = $imgW * $cmPerPx / 100; // cm per x-%
        $ky = $imgH * $cmPerPx / 100;
        $minLen = 0.01 * max($imgW, $imgH) * $cmPerPx; // app: skip < 20 units of the 2000 canvas

        $rects = [];
        foreach ($rooms as $i => $r) {
            $sx1 = $xs[self::key($r['x1'])];
            $sx2 = $xs[self::key($r['x2'])];
            $sy1 = $ys[self::key($r['y1'])];
            $sy2 = $ys[self::key($r['y2'])];
            if ($sx2 - $sx1 < 1.0 || $sy2 - $sy1 < 1.0) { // snapping squashed it: keep the raw box
                [$sx1, $sx2, $sy1, $sy2] = [$r['x1'], $r['x2'], $r['y1'], $r['y2']];
            }
            $rect = [
                'x1' => (int) round($sx1 * $kx), 'y1' => (int) round($sy1 * $ky),
                'x2' => (int) round($sx2 * $kx), 'y2' => (int) round($sy2 * $ky),
            ];
            if ($rect['x2'] - $rect['x1'] < max(20, $minLen) || $rect['y2'] - $rect['y1'] < max(20, $minLen)) {
                continue;
            }
            $rects[$i] = $rect;
        }

        $plan = ['version' => 1, 'name' => 'Analyzed plan', 'walls' => [], 'rooms' => [], 'openings' => [],
            'items' => [], 'labels' => [], 'dims' => [], 'room_ids' => []];
        if ($rects === []) {
            return $plan;
        }
        // Origin at the building's top-left corner.
        $ox = min(array_column($rects, 'x1'));
        $oy = min(array_column($rects, 'y1'));
        foreach ($rects as &$rc) {
            $rc['x1'] -= $ox; $rc['x2'] -= $ox; $rc['y1'] -= $oy; $rc['y2'] -= $oy;
        }
        unset($rc);

        $n = 0;
        foreach ($rects as $i => $rc) {
            $id = 'r' . (++$n);
            $plan['room_ids'][$i] = $id;
            $plan['rooms'][] = [
                'id' => $id,
                'pts' => [
                    ['x' => $rc['x1'], 'y' => $rc['y1']], ['x' => $rc['x2'], 'y' => $rc['y1']],
                    ['x' => $rc['x2'], 'y' => $rc['y2']], ['x' => $rc['x1'], 'y' => $rc['y2']],
                ],
                'name' => $rooms[$i]['name'],
                'color' => self::planColor($rooms[$i]['type']),
            ];
        }

        // Walls: union of collinear edge segments per line.
        $lines = ['h' => [], 'v' => []];
        foreach ($rects as $rc) {
            $lines['h'][$rc['y1']][] = [$rc['x1'], $rc['x2']];
            $lines['h'][$rc['y2']][] = [$rc['x1'], $rc['x2']];
            $lines['v'][$rc['x1']][] = [$rc['y1'], $rc['y2']];
            $lines['v'][$rc['x2']][] = [$rc['y1'], $rc['y2']];
        }
        $walls = [];   // id => wall
        $byLine = [];  // "h:y" / "v:x" => list of [a, b, id]
        $wn = 0;
        foreach (['h', 'v'] as $axis) {
            ksort($lines[$axis]);
            foreach ($lines[$axis] as $c => $segs) {
                foreach (self::mergeIntervals($segs) as [$a, $b]) {
                    $id = 'w' . (++$wn);
                    $walls[$id] = $axis === 'h'
                        ? ['id' => $id, 'x1' => $a, 'y1' => (int) $c, 'x2' => $b, 'y2' => (int) $c, 't' => self::WALL_T]
                        : ['id' => $id, 'x1' => (int) $c, 'y1' => $a, 'x2' => (int) $c, 'y2' => $b, 't' => self::WALL_T];
                    $byLine[$axis . ':' . $c][] = [$a, $b, $id];
                }
            }
        }
        $plan['walls'] = array_values($walls);

        // Openings
        $taken = []; // wall id => list of [a, b] occupied along the wall (cm from its start)
        $on = 0;
        $addOpening = static function (string $kind, string $axis, int $line, float $a, float $b, float $want, float $minW, bool $flip)
            use (&$plan, &$taken, &$on, $byLine, $walls): bool {
            foreach ($byLine[$axis . ':' . $line] ?? [] as [$wa, $wb, $wid]) {
                if ($a < $wa - 0.5 || $b > $wb + 0.5) {
                    continue;
                }
                $len = $wb - $wa;
                $span = $b - $a;
                $w = min($want, $span * 0.8, $len - 2 * self::WALL_T);
                if ($w < $minW) {
                    return false;
                }
                $c = ($a + $b) / 2 - $wa;
                foreach ($taken[$wid] ?? [] as [$ta, $tb]) {
                    if ($c + $w / 2 > $ta - 10 && $c - $w / 2 < $tb + 10) {
                        return false; // another opening already there (e.g. the neighbour's door)
                    }
                }
                $t = round($c / $len, 4);
                if ($t <= 0 || $t >= 1) {
                    return false;
                }
                $taken[$wid][] = [$c - $w / 2, $c + $w / 2];
                $plan['openings'][] = ['id' => 'o' . (++$on), 'kind' => $kind, 'wall' => $wid, 't' => $t,
                    'w' => (int) round($w), 'flip' => $flip, 'hinge' => false];
                return true;
            }
            return false;
        };

        foreach ($rects as $i => $rc) {
            $room = $rooms[$i];
            $edges = self::roomEdges($i, $rects, $rooms);
            if ($room['has_door']) {
                $small = in_array($room['type'], ['bathroom', 'toilet', 'closet', 'storage', 'storage_laundry', 'utility_room'], true);
                $want = in_array($room['type'], ['garage', 'car_parking'], true) ? 240.0 : ($small ? 80.0 : 90.0);
                $cands = [];
                foreach ($edges as $e) {
                    foreach ($e['shared'] as [$a, $b, $ntype]) {
                        $cands[] = $e + ['a' => $a, 'b' => $b, 'score' => ($b - $a) + (in_array($ntype, self::DOOR_TARGETS, true) ? 100000 : 0)];
                    }
                }
                if ($cands === []) { // no neighbour: door to the outside
                    foreach ($edges as $e) {
                        foreach ($e['exterior'] as [$a, $b]) {
                            $cands[] = $e + ['a' => $a, 'b' => $b, 'score' => $b - $a];
                        }
                    }
                }
                usort($cands, static fn (array $p, array $q): int => $q['score'] <=> $p['score']);
                foreach ($cands as $cd) {
                    $w = $cd['b'] - $cd['a'] >= 60 ? $want : 0.0;
                    if ($w > 0 && $addOpening('door', $cd['axis'], $cd['line'], $cd['a'], $cd['b'], min($w, ($cd['b'] - $cd['a']) * 0.8), 50, $cd['flip'])) {
                        break;
                    }
                }
            }
            if ($room['has_window']) {
                $want = in_array($room['type'], ['living_room', 'drawing_room', 'master_bedroom', 'dining_room'], true) ? 150.0 : 120.0;
                $cands = [];
                foreach ($edges as $e) {
                    foreach ($e['exterior'] as [$a, $b]) {
                        $cands[] = $e + ['a' => $a, 'b' => $b, 'score' => $b - $a];
                    }
                }
                usort($cands, static fn (array $p, array $q): int => $q['score'] <=> $p['score']);
                foreach ($cands as $cd) {
                    if ($addOpening('window', $cd['axis'], $cd['line'], $cd['a'], $cd['b'], min($want, ($cd['b'] - $cd['a']) * 0.6), 40, false)) {
                        break;
                    }
                }
            }
        }
        return $plan;
    }

    /**
     * The four edges of room $i with the parts shared with neighbours and the exterior parts.
     * `flip` makes a door on that edge swing into room $i (editor normal = (-uy, ux)).
     */
    private static function roomEdges(int $i, array $rects, array $rooms): array
    {
        $r = $rects[$i];
        $defs = [
            // axis, line, range, outward sign, flip
            ['h', $r['y1'], $r['x1'], $r['x2'], -1, false], // top: room below, +y swing
            ['h', $r['y2'], $r['x1'], $r['x2'], +1, true],  // bottom
            ['v', $r['x1'], $r['y1'], $r['y2'], -1, true],  // left: room to the right
            ['v', $r['x2'], $r['y1'], $r['y2'], +1, false], // right
        ];
        $edges = [];
        foreach ($defs as [$axis, $line, $a, $b, $dir, $flip]) {
            $shared = [];
            foreach ($rects as $j => $s) {
                if ($j === $i) {
                    continue;
                }
                // Does $s cover the outside of this edge?
                [$lo, $hi, $sa, $sb] = $axis === 'h'
                    ? [$s['y1'], $s['y2'], $s['x1'], $s['x2']]
                    : [$s['x1'], $s['x2'], $s['y1'], $s['y2']];
                $covers = $dir < 0 ? ($lo < $line && $hi >= $line) : ($hi > $line && $lo <= $line);
                $oa = max($a, $sa);
                $ob = min($b, $sb);
                if ($covers && $ob - $oa > 0) {
                    $shared[] = [$oa, $ob, $rooms[$j]['type']];
                }
            }
            $exterior = [];
            $cur = $a;
            $covered = self::mergeIntervals(array_map(static fn (array $x): array => [$x[0], $x[1]], $shared));
            foreach ($covered as [$ca, $cb]) {
                if ($ca > $cur) {
                    $exterior[] = [$cur, $ca];
                }
                $cur = max($cur, $cb);
            }
            if ($cur < $b) {
                $exterior[] = [$cur, $b];
            }
            $edges[] = ['axis' => $axis, 'line' => $line, 'flip' => $flip, 'shared' => $shared, 'exterior' => $exterior];
        }
        return $edges;
    }

    /** @param list<array{0: int|float, 1: int|float}> $segs @return list<array{0: int|float, 1: int|float}> */
    private static function mergeIntervals(array $segs): array
    {
        usort($segs, static fn (array $p, array $q): int => $p[0] <=> $q[0]);
        $out = [];
        foreach ($segs as [$a, $b]) {
            $k = count($out) - 1;
            if ($k >= 0 && $a <= $out[$k][1] + 1) {
                $out[$k][1] = max($out[$k][1], $b);
            } else {
                $out[] = [$a, $b];
            }
        }
        return $out;
    }

    /**
     * Cluster coordinates (in %) that lie within SNAP_PCT of a cluster's first value and
     * replace each by its cluster mean. Returns key(value) => snapped value.
     *
     * @param list<float> $vals
     * @return array<string, float>
     */
    private static function snapValues(array $vals): array
    {
        sort($vals);
        $map = [];
        $cluster = [];
        $flush = static function () use (&$cluster, &$map): void {
            if ($cluster === []) {
                return;
            }
            $mean = array_sum($cluster) / count($cluster);
            foreach ($cluster as $v) {
                $map[self::key($v)] = $mean;
            }
            $cluster = [];
        };
        foreach ($vals as $v) {
            if ($cluster !== [] && $v - $cluster[0] > self::SNAP_PCT) {
                $flush();
            }
            $cluster[] = $v;
        }
        $flush();
        return $map;
    }

    private static function key(float $v): string
    {
        return sprintf('%.6f', $v);
    }

    // ─────────────────────────────────────────────────────────── small utils

    private static function normBox(float $x1, float $y1, float $x2, float $y2): array
    {
        $x = self::clamp($x1 / 100, 0, 1);
        $y = self::clamp($y1 / 100, 0, 1);
        return [
            'x' => round($x, 4),
            'y' => round($y, 4),
            'w' => round(self::clamp($x2 / 100, 0, 1) - $x, 4),
            'h' => round(self::clamp($y2 / 100, 0, 1) - $y, 4),
        ];
    }

    private static function clamp(float $v, float $lo, float $hi): float
    {
        return max($lo, min($hi, $v));
    }

    private static function num(mixed $v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return is_finite((float) $v) ? (float) $v : null;
        }
        if (is_string($v) && is_numeric(trim($v))) {
            return (float) trim($v);
        }
        return null;
    }

    private static function intOr(mixed $v, int $default): int
    {
        $n = self::num($v);
        return $n === null ? $default : (int) $n;
    }

    private static function str(mixed $v, string $default, bool $trim = true): string
    {
        if (!is_string($v) && !is_int($v) && !is_float($v)) {
            return $default;
        }
        $s = (string) $v;
        $s = $trim ? trim($s) : $s;
        return $trim && $s === '' ? $default : $s;
    }

    private static function median(array $v): float
    {
        sort($v);
        $n = count($v);
        return $n % 2 ? (float) $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
    }

    private static function fmtNum(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }
}
