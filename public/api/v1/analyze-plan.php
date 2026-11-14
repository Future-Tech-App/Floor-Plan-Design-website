<?php
declare(strict_types=1);

/**
 * POST /api/v1/analyze-plan.php — AI floor plan analyzer for the web app.
 *
 * Body (JSON): {"image": "data:image/jpeg;base64,..."} (jpeg, png or webp; the
 * browser downscales to ≤ 1600 px before sending). The image is read by a Groq
 * vision model on the server (see src/PlanAnalyzer.php); the key never reaches
 * the browser.
 *
 * 200 {"ok": true, "result": {...}, "plan": {...}}   result/plan: see PlanAnalyzer docblock
 * 4xx/5xx {"ok": false, "error": "..."}
 *   400 bad body / not an image, 403 cross-origin, 405 not POST, 413 image > 6 MB,
 *   422 no rooms found, 429 rate limit, 502 AI service error, 503 AI not configured,
 *   500 unexpected error (details only in the server log).
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once ROOT_DIR . '/src/PlanAnalyzer.php';

const ANALYZE_MAX_IMAGE_BYTES = 6 * 1024 * 1024;

function analyze_error(string $message, int $status): never
{
    json_response(['ok' => false, 'error' => $message], $status);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    analyze_error('Method not allowed', 405);
}

try {
    GroqClient::assertSameOrigin();

    // base64 grows the image by 4/3; allow some room for the JSON wrapper.
    $maxBody = (int) ceil(ANALYZE_MAX_IMAGE_BYTES * 4 / 3) + 4096;
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBody) {
        analyze_error('The image is too large. Please use an image under 6 MB.', 413);
    }
    $raw = (string) file_get_contents('php://input', false, null, 0, $maxBody + 1);
    if (strlen($raw) > $maxBody) {
        analyze_error('The image is too large. Please use an image under 6 MB.', 413);
    }

    $body = json_decode($raw, true);
    $image = is_array($body) ? ($body['image'] ?? null) : null;
    if (!is_string($image) || $image === '') {
        analyze_error('Request body must be a JSON object with an "image" data URL.', 400);
    }
    if (!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $image, $m)) {
        analyze_error('The image must be a JPEG, PNG or WebP data URL.', 400);
    }
    $b64 = preg_replace('/\s+/', '', substr($image, strlen($m[0])));
    $bytes = base64_decode((string) $b64, true);
    if ($bytes === false || $bytes === '') {
        analyze_error('The image data is not valid base64.', 400);
    }
    if (strlen($bytes) > ANALYZE_MAX_IMAGE_BYTES) {
        analyze_error('The image is too large. Please use an image under 6 MB.', 413);
    }

    $info = @getimagesizefromstring($bytes);
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    if (!is_array($info) || (int) $info[0] <= 0 || (int) $info[1] <= 0
        || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        analyze_error('That file could not be read as an image. Please upload a JPEG, PNG or WebP photo of the plan.', 400);
    }

    @set_time_limit(400); // up to three AI calls of up to 120 s each
    $out = PlanAnalyzer::analyze($bytes, (int) $info[0], (int) $info[1], $mime);
    json_response(['ok' => true, 'result' => $out['result'], 'plan' => $out['plan']]);
} catch (GroqException $e) {
    analyze_error($e->getMessage(), $e->status);
} catch (Throwable $e) {
    error_log('analyze-plan: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    analyze_error('Something went wrong while analyzing the plan. Please try again.', 500);
}
