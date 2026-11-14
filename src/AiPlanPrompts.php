<?php
declare(strict_types=1);

/**
 * Prompt builders for the AI floor plan creator.
 * Port of lib/services/ai/floor_plan_prompts.dart (text kept verbatim).
 */
final class AiPlanPrompts
{
    /** System prompt used for every call (floor_plan_generator.dart _groqCall). */
    public const SYSTEM = 'You are a JSON-only API. ONLY output raw JSON. No markdown, no backticks, no prose. First character MUST be { last MUST be }. Never truncate.';

    /** @param list<array{id:string,name:string,type:string,w:int,h:int}> $rooms */
    private static function roomsJson(array $rooms): string
    {
        return implode(',', array_map(static fn (array $r): string =>
            '{"id":"' . $r['id'] . '","name":"' . $r['name'] . '","type":"' . $r['type'] . '","w":' . $r['w'] . ',"h":' . $r['h'] . '}',
            $rooms));
    }

    /** Standard residential prompt — sends a fixed room list and asks the model to echo it back. */
    public static function standard(string $description, AiPlanRequest $req): string
    {
        $pw = (int) AiPlanText::detectPlotSize($description)[0];
        $gfd = self::roomsJson($req->groundFloorRooms());
        $ufs = '';
        if ($req->floors > 1) {
            $ufs = ',{"name":"1st Floor","rooms":[' . self::roomsJson($req->upperFloorRooms()) . ']}';
        }
        return "You are a JSON-only floor plan API.\n"
            . "TASK: Return EXACTLY the rooms listed. Preserve order. Do NOT change ids, names, types, w, or h.\n"
            . "ARCHITECTURAL PLACEMENT RULES (top of JSON = front/street side):\n"
            . "  • car_porch / garage: ROW 1 LEFT — street-facing, left portion of front face\n"
            . "  • entrance: ROW 1 RIGHT — adjacent to car porch, right portion of front face\n"
            . "  • drawing_room / living_room: ROW 2 LEFT — first room visible from entrance\n"
            . "  • dining_room: ROW 2 RIGHT — adjacent to kitchen below\n"
            . "  • tv_lounge / family_area: ROW 2 or 3 — between formal and private zone\n"
            . "  • kitchen: CORNER position, last in its row — service access from back\n"
            . "  • corridor: links public zone to private bedrooms vertically\n"
            . "  • master_bedroom: first bedroom row, most private, attached bath to its right\n"
            . "  • bedroom: rows after master, each with attached bath to its right\n"
            . "  • servant_room: utility row, near kitchen (same back-right corner)\n"
            . "  • stairs: utility row, near corridor for vertical access\n"
            . "  • lawn / garden: LAST ROW — always spans full plot width at the back\n"
            . 'Return ONLY: {"description":"' . str_replace('"', "'", $description) . '","plotWidth":' . $pw
            . ',"floors":[{"name":"Ground Floor","rooms":[' . $gfd . ']}' . $ufs . "]}\n"
            . 'No markdown, no explanation.';
    }

    /** Open-ended prompt for non-residential descriptions: the model generates the room list. */
    public static function aiGenerated(string $description, float $plotW, float $plotH, int $floors): string
    {
        $safeDesc = str_replace('"', "'", $description);
        $pw = (int) $plotW;
        $ph = (int) $plotH;
        $floorInstr = $floors > 1
            ? "Include $floors floors in the floors array (Ground Floor, 1st Floor, etc.)."
            : 'Include only 1 floor (Ground Floor).';
        return "You are a JSON-only architectural floor plan API.\n"
            . "TASK: Generate a realistic, architecturally correct room layout for:\n"
            . "\"$safeDesc\"\n\n"
            . "Plot: $pw cm wide × $ph cm deep. $floorInstr\n\n"
            . "STRICT PLACEMENT RULES (rooms ordered top→bottom = front→back):\n"
            . "  1. FRONT ROW: entrance + car_porch/car_parking side-by-side, spanning full width\n"
            . "     - car_porch LEFT (~58% of width), entrance RIGHT (~42%)\n"
            . "  2. PUBLIC ROW: lobby/reception/drawing_room LEFT, dining_room RIGHT (near kitchen)\n"
            . "  3. SERVICE ROW: corridor CENTER (links all zones), kitchen LAST/RIGHT (back corner)\n"
            . "     - prayer_room / pantry / study before kitchen\n"
            . "  4. PRIVATE ROWS: each bedroom with attached bathroom to its RIGHT in same row\n"
            . "     - master_bedroom first (largest), then other bedrooms\n"
            . "  5. UTILITY ROW: stairs near corridor, servant_room near kitchen corner\n"
            . "  6. BACK ROW: lawn / garden span FULL width (one per row, last rows)\n\n"
            . "ROOM TYPES (use exact strings):\n"
            . "  Residential: car_porch, entrance, drawing_room, living_room, dining_room, tv_lounge,\n"
            . "    kitchen, corridor, master_bedroom, bedroom, bathroom, stairs, servant_room, lawn, balcony\n"
            . "  Hospital: waiting_area, reception, consultation, emergency_room, pharmacy, bathroom, corridor, stairs, utility_room, lobby, entrance\n"
            . "  School: classroom, library, computer_lab, lab, private_office, bathroom, corridor, stairs, entrance, lobby, prayer_room\n"
            . "  Office: reception, lobby, private_office, open_office, meeting_room, bathroom, corridor, stairs, pantry, entrance\n"
            . "  Hotel: master_bedroom, bedroom, bathroom, lobby, reception, dining_room, kitchen, corridor, stairs, elevator\n"
            . "  Restaurant: restaurant, kitchen, bathroom, entrance, storage, pantry\n"
            . "  Mall/plaza: shop, lobby, corridor, stairs, elevator, bathroom, storage, security_room, entrance, car_parking\n\n"
            . "ROOM SIZES (cm):\n"
            . "  car_porch (2 cars): w:360-500, h:200-220\n"
            . "  entrance: w:200-280, h:100-120\n"
            . "  master_bedroom: w:240-300, h:220-260\n"
            . "  bedroom: w:200-260, h:200-240\n"
            . "  bathroom (attached): w:120-140, h:120-140\n"
            . "  drawing/living: w:280-360, h:220-260\n"
            . "  dining_room: w:220-280, h:180-220\n"
            . "  kitchen: w:200-260, h:180-220 (back corner)\n"
            . "  tv_lounge: w:260-320, h:200-240\n"
            . "  corridor: w:120-180, h:100-140\n"
            . "  stairs: w:140-180, h:220-260\n"
            . "  lawn/garden: w:FULL_PLOT_WIDTH, h:180-260\n"
            . "  Large commercial (lobby, hall): w:300-600, h:200-300\n\n"
            . "RETURN ONLY valid JSON:\n"
            . '{"description":"brief summary","plotWidth":' . $pw
            . ',"floors":[{"name":"Ground Floor","rooms":[{"id":"r1","name":"Room Name","type":"room_type","w":200,"h":180},...]},...]}' . "\n"
            . 'Be thorough and realistic. Every residential house MUST have an entrance and car_porch in first position.';
    }

    /** Fixed ground-floor template for large (4+ floor) buildings. */
    public static function groundFloor(): string
    {
        return '{"name":"Ground Floor","rooms":['
            . '{"id":"gf1","name":"Entrance","type":"entrance","w":200,"h":100},'
            . '{"id":"gf2","name":"Lobby","type":"lobby","w":400,"h":200},'
            . '{"id":"gf3","name":"Reception","type":"reception","w":200,"h":160},'
            . '{"id":"gf4","name":"Security Room","type":"security_room","w":160,"h":160},'
            . '{"id":"gf5","name":"Car Parking","type":"car_parking","w":500,"h":200},'
            . '{"id":"gf6","name":"Stairs 1","type":"stairs","w":120,"h":220},'
            . '{"id":"gf7","name":"Stairs 2","type":"stairs","w":120,"h":220},'
            . '{"id":"gf8","name":"Utility Room","type":"utility_room","w":160,"h":160}]}';
    }

    /** Fixed typical-floor template for large buildings. */
    public static function typicalFloor(): string
    {
        return '{"name":"Typical Floor","rooms":['
            . '{"id":"t1","name":"Master Bedroom A","type":"master_bedroom","w":240,"h":220},'
            . '{"id":"t2","name":"Bathroom A1","type":"bathroom","w":120,"h":120},'
            . '{"id":"t3","name":"Bedroom A2","type":"bedroom","w":220,"h":200},'
            . '{"id":"t4","name":"Bathroom A2","type":"bathroom","w":120,"h":120},'
            . '{"id":"t5","name":"Living Room A","type":"living_room","w":280,"h":200},'
            . '{"id":"t6","name":"Kitchen A","type":"kitchen","w":200,"h":180},'
            . '{"id":"t7","name":"Balcony A","type":"balcony","w":200,"h":100},'
            . '{"id":"t8","name":"Corridor","type":"corridor","w":140,"h":180},'
            . '{"id":"t9","name":"Lobby","type":"lobby","w":200,"h":180},'
            . '{"id":"tA","name":"Stairs 1","type":"stairs","w":120,"h":220},'
            . '{"id":"tB","name":"Stairs 2","type":"stairs","w":120,"h":220},'
            . '{"id":"tC","name":"Master Bedroom B","type":"master_bedroom","w":240,"h":220},'
            . '{"id":"tD","name":"Bathroom B1","type":"bathroom","w":120,"h":120},'
            . '{"id":"tE","name":"Bedroom B2","type":"bedroom","w":220,"h":200},'
            . '{"id":"tF","name":"Bedroom B3","type":"bedroom","w":220,"h":200},'
            . '{"id":"tG","name":"Bathroom B2","type":"bathroom","w":120,"h":120},'
            . '{"id":"tH","name":"Living Room B","type":"living_room","w":280,"h":200},'
            . '{"id":"tI","name":"Kitchen B","type":"kitchen","w":200,"h":180},'
            . '{"id":"tJ","name":"Balcony B","type":"balcony","w":200,"h":100}]}';
    }

    /** Fixed roof template (adds a prayer room when the description mentions one). */
    public static function roofFloor(string $description): string
    {
        $ex = str_contains(AiPlanText::lower($description), 'prayer')
            ? ',{"id":"rf4","name":"Prayer Room","type":"prayer_room","w":160,"h":180}' : '';
        return '{"name":"Roof","rooms":['
            . '{"id":"rf1","name":"Roof Terrace","type":"roof_terrace","w":600,"h":300},'
            . '{"id":"rf2","name":"Utility Room","type":"utility_room","w":160,"h":160},'
            . '{"id":"rf3","name":"Stairs","type":"stairs","w":120,"h":220}' . $ex . ']}';
    }
}
