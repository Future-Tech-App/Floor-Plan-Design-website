<?php
declare(strict_types=1);

/**
 * POST /api/v1/generate-plan.php — AI floor plan creator for the web app.
 *
 * Body: {"description": "3 bedroom house with car porch and lawn"} (3–2000 chars).
 * Success: {"ok":true,"description":...,"source":"ai"|"local","floors":[{"name":...,"plan":{...}}]}
 * Error:   {"ok":false,"error":"message"} with an HTTP status (400, 403, 405, 429, 500).
 *
 * The Groq key stays on the server (see GroqClient). Without a key, or when the
 * AI call fails, the plan is laid out locally (source "local").
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once ROOT_DIR . '/src/AiPlanRequest.php';
require_once ROOT_DIR . '/src/AiPlanLayout.php';
require_once ROOT_DIR . '/src/AiPlanPrompts.php';
require_once ROOT_DIR . '/src/AiPlanGenerator.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

try {
    GroqClient::assertSameOrigin();
} catch (GroqException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], $e->status);
}

$raw = (string) file_get_contents('php://input', false, null, 0, 65537);
if (strlen($raw) > 65536) {
    json_response(['ok' => false, 'error' => 'Request is too large.'], 413);
}
$body = json_decode($raw, true);
$description = is_array($body) && is_string($body['description'] ?? null) ? trim($body['description']) : null;
if ($description === null) {
    json_response(['ok' => false, 'error' => 'Request body must be a JSON object with a "description" string.'], 400);
}
$length = function_exists('mb_strlen') ? mb_strlen($description, 'UTF-8') : strlen($description);
if ($length < 3) {
    json_response(['ok' => false, 'error' => 'Please describe the building you want (at least 3 characters).'], 400);
}
if ($length > 2000) {
    json_response(['ok' => false, 'error' => 'The description is too long (2000 characters max).'], 400);
}

try {
    $result = AiPlanGenerator::generate($description);
} catch (GroqException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], $e->status);
} catch (Throwable $e) {
    error_log('[generate-plan] ' . $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    json_response(['ok' => false, 'error' => 'Could not generate a floor plan. Please try again.'], 500);
}

json_response([
    'ok'          => true,
    'description' => $result['description'],
    'source'      => $result['source'],
    'floors'      => $result['floors'],
], 200);
