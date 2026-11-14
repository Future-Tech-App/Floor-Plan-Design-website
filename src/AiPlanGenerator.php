<?php
declare(strict_types=1);

require_once __DIR__ . '/AiPlanRequest.php';
require_once __DIR__ . '/AiPlanLayout.php';
require_once __DIR__ . '/AiPlanPrompts.php';

/**
 * AI floor plan creator for the website (/api/v1/generate-plan.php).
 *
 * Port of lib/services/ai/floor_plan_generator.dart. Three strategies:
 *   1. custom multi-floor ("Ground floor: …. 1st floor: …") — parsed locally, no AI call;
 *   2. large building (4+ floors / apartment building) — per-floor templates sent to
 *      Groq, with hard-coded local defaults;
 *   3. standard building — one Groq call, local layout fallback.
 *
 * Needs GroqClient (src/GroqClient.php). When Groq is not configured, or a call
 * fails with anything but a rate limit, the local layout is used (source 'local').
 * A 429 GroqException is re-thrown so the endpoint can report it.
 */
final class AiPlanGenerator
{
    private const FLOOR_LABELS = ['Ground Floor', '1st Floor', '2nd Floor', '3rd Floor',
        '4th Floor', '5th Floor', '6th Floor', '7th Floor', '8th Floor', '9th Floor'];

    /** Large buildings use a fixed plot. */
    private const LARGE_PLOT_W = 1400;
    private const LARGE_PLOT_H = 1000;

    /**
     * @return array{description: string, source: string, floors: list<array{name: string, plan: array}>}
     * @throws GroqException status 429 when the AI rate limit is hit
     */
    public static function generate(string $description): array
    {
        if (AiPlanText::hasPerFloorDescriptions($description)) {
            return self::generateCustomMultiFloor($description);
        }
        if (AiPlanText::isLargeBuilding($description)) {
            return self::generateLargeBuilding($description);
        }
        return self::generateStandardBuilding($description);
    }

    /* ---------- Strategy 1: custom multi-floor (local parser only) ---------- */

    private static function generateCustomMultiFloor(string $description): array
    {
        $plotW = AiPlanText::detectPlotSize($description)[0];
        $pf = AiPlanText::tryParsePerFloorDesc($description) ?? [];
        $totalFloors = AiPlanText::detectFloorCount($description);
        $withRoof = AiPlanText::hasRoofFloor($description);

        // One orientation for the whole building so stairs / services stack.
        $houseMirror = mt_rand(0, 1) === 1;
        $labels = self::FLOOR_LABELS;

        $allFloors = [];
        $li = 0;
        foreach ($pf as $key => $desc) {
            $key = (string) $key;
            if (str_contains($key, 'each')) {
                // "each upper floor" — replicate for the remaining upper floors
                $specs = AiPlanText::parseFloorDescription($desc, 'each');
                if ($specs === []) {
                    continue; // Dart would throw here (specs.first) and fail the whole request
                }
                $plan = AiPlanLayout::buildFloorElements($specs, $plotW, $houseMirror);
                $uc = $totalFloors - 1 - ($withRoof ? 1 : 0);
                $si = $li;
                for ($i = 0; $i < $uc; $i++) {
                    $lbl = ($si + $i) < count($labels) ? $labels[$si + $i] : 'Floor ' . ($si + $i);
                    $allFloors[] = $i === 0
                        ? ['name' => $lbl, 'plan' => $plan]
                        : AiPlanLayout::replicateFloor(['name' => $lbl, 'plan' => $plan], $si + $i);
                    $li++;
                }
            } else {
                $isRoof = str_contains($key, 'roof');
                $lbl = $isRoof ? 'Roof' : ($li < count($labels) ? $labels[$li] : 'Floor ' . $li);
                $specs = AiPlanText::parseFloorDescription($desc, 'f' . $li);
                if ($specs !== []) {
                    $allFloors[] = ['name' => $lbl, 'plan' => AiPlanLayout::buildFloorElements($specs, $plotW, $houseMirror)];
                }
                $li++;
            }
        }

        if ($allFloors === []) {
            return self::generateStandardBuilding($description);
        }
        return ['description' => $description, 'source' => 'local', 'floors' => $allFloors];
    }

    /* ---------- Strategy 2: standard building (1 AI call + local fallback) ---------- */

    private static function generateStandardBuilding(string $description): array
    {
        $req = new AiPlanRequest($description);
        [$plotW, $plotH] = AiPlanText::detectPlotSize($description);
        $isRes = AiPlanText::isResidentialBuilding($description);
        $houseMirror = mt_rand(0, 1) === 1;

        if (GroqClient::configured()) {
            try {
                $prompt = $isRes
                    ? AiPlanPrompts::standard($description, $req)
                    : AiPlanPrompts::aiGenerated($description, $plotW, $plotH, $req->floors);
                $json = self::groqCall($prompt, 6000);

                $aiPlotW = $json['plotWidth'] ?? null;
                if ($aiPlotW !== null && !is_int($aiPlotW) && !is_float($aiPlotW)) {
                    throw new AiPlanLayoutException('plotWidth is not a number');
                }
                $aiPlotW = $aiPlotW === null ? $plotW : (float) $aiPlotW;
                $aiDesc = $json['description'] ?? null;
                if ($aiDesc !== null && !is_string($aiDesc)) {
                    throw new AiPlanLayoutException('description is not a string');
                }
                $aiDesc = ($aiDesc !== null && $aiDesc !== '') ? $aiDesc : $description;

                $aiFloors = AiPlanLayout::floorsFromJson($json, $aiPlotW, $houseMirror);
                $aiTotal = array_sum(array_map([AiPlanLayout::class, 'roomCount'], $aiFloors));
                // Accept the AI output when it has at least 2 rooms in total.
                if ($aiFloors !== [] && $aiTotal >= 2) {
                    return ['description' => $aiDesc, 'source' => 'ai', 'floors' => $aiFloors];
                }
            } catch (Throwable $e) {
                self::handleAiFailure($e);
            }
        }

        return ['description' => $description, 'source' => 'local',
            'floors' => self::localFallbackFloors($req, $plotW, $houseMirror)];
    }

    /* ---------- Strategy 3: large building (per-floor templates) ---------- */

    private static function generateLargeBuilding(string $description): array
    {
        $totalFloors = AiPlanText::detectFloorCount($description);
        $withRoof = AiPlanText::hasRoofFloor($description);
        $plotW = (float) self::LARGE_PLOT_W;
        $houseMirror = mt_rand(0, 1) === 1;
        $useAi = GroqClient::configured();
        $usedAi = false;
        $allFloors = [];

        if ($useAi) {
            // Fail fast with 429 before spending calls that cannot all complete.
            GroqClient::assertWithinRateLimit($withRoof ? 3 : 2);
        }

        $ground = null;
        if ($useAi) {
            try {
                $gf = AiPlanLayout::floorDataFromJson(self::groqCall(AiPlanPrompts::groundFloor(), 3000),
                    'Ground Floor', $plotW, $houseMirror);
                if (AiPlanLayout::roomCount($gf) >= 4) {
                    $ground = $gf;
                    $usedAi = true;
                }
            } catch (Throwable $e) {
                self::handleAiFailure($e);
            }
        }
        $allFloors[] = $ground ?? self::defaultLargeGroundFloor($plotW, $houseMirror);

        $upperCount = $totalFloors - ($withRoof ? 1 : 0);
        $typical = null;
        if ($useAi) {
            try {
                $tf = AiPlanLayout::floorDataFromJson(self::groqCall(AiPlanPrompts::typicalFloor(), 4000),
                    '1st Floor', $plotW, $houseMirror);
                if (AiPlanLayout::roomCount($tf) >= 8) {
                    $typical = $tf;
                    $usedAi = true;
                }
            } catch (Throwable $e) {
                self::handleAiFailure($e);
            }
        }
        $typical ??= self::defaultLargeTypicalFloor($plotW, $houseMirror);

        for ($i = 1; $i <= $upperCount; $i++) {
            $allFloors[] = $i === 1
                ? ['name' => '1st Floor', 'plan' => $typical['plan']]
                : AiPlanLayout::replicateFloor($typical, $i);
        }

        // Roof: from the AI when available. Unlike the Dart code, fall back to the roof
        // template (it is already a room list) so a roof is never silently dropped.
        if ($withRoof) {
            $roof = null;
            if ($useAi) {
                try {
                    $rf = AiPlanLayout::floorDataFromJson(
                        self::groqCall(AiPlanPrompts::roofFloor($description), 2000), 'Roof', $plotW, $houseMirror);
                    if (AiPlanLayout::roomCount($rf) >= 1) {
                        $roof = $rf;
                        $usedAi = true;
                    }
                } catch (Throwable $e) {
                    self::handleAiFailure($e);
                }
            }
            $allFloors[] = $roof ?? AiPlanLayout::floorDataFromJson(
                json_decode(AiPlanPrompts::roofFloor($description), true), 'Roof', $plotW, $houseMirror);
        }

        $kind = AiPlanText::isLargeBuilding($description) ? 'apartment building' : 'building';
        return ['description' => $totalFloors . '-floor ' . $kind, 'source' => $usedAi ? 'ai' : 'local',
            'floors' => $allFloors];
    }

    /* ---------- Helpers ---------- */

    /** One Groq call with the Dart system prompt; returns the parsed JSON object ([] when unparseable). */
    private static function groqCall(string $prompt, int $maxTokens): array
    {
        $text = GroqClient::chat([
            ['role' => 'system', 'content' => AiPlanPrompts::SYSTEM],
            ['role' => 'user', 'content' => $prompt],
        ], $maxTokens, 0.7);
        return GroqClient::parseJsonObject($text);
    }

    /** Re-throw rate limits; everything else means "use the local layout". */
    private static function handleAiFailure(Throwable $e): void
    {
        if ($e instanceof GroqException) {
            if ($e->status === 429) {
                throw $e;
            }
            return;
        }
        if (!$e instanceof AiPlanLayoutException) {
            error_log('[AiPlanGenerator] AI response could not be used: ' . $e::class . ': ' . $e->getMessage());
        }
    }

    /** Dart _dartFallbackFloors: deterministic room lists from AiPlanRequest. */
    private static function localFallbackFloors(AiPlanRequest $req, float $plotW, bool $mirror): array
    {
        $toSpecs = static fn (array $rooms): array => array_map(static fn (array $r): array =>
            AiPlanLayout::spec($r['id'], $r['name'], $r['type'], 0, 0, (float) $r['w'], (float) $r['h']), $rooms);

        $floors = [['name' => 'Ground Floor',
            'plan' => AiPlanLayout::buildFloorElements($toSpecs($req->groundFloorRooms()), $plotW, $mirror)]];
        if ($req->floors > 1) {
            $upper = ['name' => '1st Floor',
                'plan' => AiPlanLayout::buildFloorElements($toSpecs($req->upperFloorRooms()), $plotW, $mirror)];
            $floors[] = $upper;
            for ($i = 2; $i < $req->floors; $i++) {
                $floors[] = AiPlanLayout::replicateFloor($upper, $i);
            }
        }
        return $floors;
    }

    private static function defaultLargeGroundFloor(float $plotW, bool $mirror): array
    {
        $s = [AiPlanLayout::class, 'spec'];
        return ['name' => 'Ground Floor', 'plan' => AiPlanLayout::buildFloorElements([
            $s('gfd1', 'Entrance', 'entrance', 0, 0, 200, 100),
            $s('gfd2', 'Lobby', 'lobby', 0, 0, 400, 200),
            $s('gfd3', 'Reception', 'reception', 0, 0, 200, 160),
            $s('gfd4', 'Security Room', 'security_room', 0, 0, 160, 160),
            $s('gfd5', 'Car Parking', 'car_parking', 0, 0, 500, 200),
            $s('gfd6', 'Stairs 1', 'stairs', 0, 0, 120, 220),
            $s('gfd7', 'Stairs 2', 'stairs', 0, 0, 120, 220),
            $s('gfd8', 'Utility Room', 'utility_room', 0, 0, 160, 160),
        ], $plotW, $mirror)];
    }

    private static function defaultLargeTypicalFloor(float $plotW, bool $mirror): array
    {
        $s = [AiPlanLayout::class, 'spec'];
        return ['name' => '1st Floor', 'plan' => AiPlanLayout::buildFloorElements([
            $s('tfd1', 'Master Bedroom A', 'master_bedroom', 0, 0, 240, 220),
            $s('tfd2', 'Bathroom A1', 'bathroom', 0, 0, 120, 120),
            $s('tfd3', 'Bedroom A2', 'bedroom', 0, 0, 220, 200),
            $s('tfd4', 'Bathroom A2', 'bathroom', 0, 0, 120, 120),
            $s('tfd5', 'Living Room A', 'living_room', 0, 0, 280, 200),
            $s('tfd6', 'Kitchen A', 'kitchen', 0, 0, 200, 180),
            $s('tfd7', 'Balcony A', 'balcony', 0, 0, 200, 100),
            $s('tfd8', 'Corridor', 'corridor', 0, 0, 140, 180),
            $s('tfd9', 'Lobby', 'lobby', 0, 0, 200, 180),
            $s('tfdA', 'Stairs 1', 'stairs', 0, 0, 120, 220),
            $s('tfdB', 'Stairs 2', 'stairs', 0, 0, 120, 220),
            $s('tfdC', 'Master Bedroom B', 'master_bedroom', 0, 0, 240, 220),
            $s('tfdD', 'Bathroom B1', 'bathroom', 0, 0, 120, 120),
            $s('tfdE', 'Bedroom B2', 'bedroom', 0, 0, 220, 200),
            $s('tfdF', 'Bedroom B3', 'bedroom', 0, 0, 220, 200),
            $s('tfdG', 'Bathroom B2', 'bathroom', 0, 0, 120, 120),
            $s('tfdH', 'Living Room B', 'living_room', 0, 0, 280, 200),
            $s('tfdI', 'Kitchen B', 'kitchen', 0, 0, 200, 180),
            $s('tfdJ', 'Balcony B', 'balcony', 0, 0, 200, 100),
        ], $plotW, $mirror)];
    }
}
