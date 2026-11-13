<?php
declare(strict_types=1);

/**
 * POST /api/v1/ai.php — server-side proxy to Groq's OpenAI-compatible
 * chat-completions API, used by the web app (/app/).
 *
 * Why: the browser must never hold the Groq API key. The web app sends the same
 * JSON body it would send to Groq, minus the Authorization header; this endpoint
 * adds the key, enforces limits and returns Groq's response unchanged.
 *
 * Limits: same-origin only, model allow-list, max_tokens cap, body-size cap and a
 * per-IP rate limit (config: ai_models, ai_max_tokens, ai_max_body_mb, ai_rate_limit).
 * Errors use Groq's shape {"error":{"message":...}} so the app's existing error
 * handling shows them.
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';

function ai_error(string $message, int $status): never
{
    json_response(['error' => ['message' => $message]], $status);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    ai_error('Method not allowed', 405);
}

// Same-origin only: browsers always send Origin on cross-origin POSTs.
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '') {
    $originHost = (string) parse_url($origin, PHP_URL_HOST);
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $siteHost = strtolower((string) parse_url((string) config('site_url'), PHP_URL_HOST));
    $allowed = array_unique(array_filter([$host, $siteHost, $siteHost ? 'www.' . $siteHost : '']));
    if (!in_array(strtolower($originHost), $allowed, true)) {
        ai_error('Cross-origin requests are not allowed.', 403);
    }
}

$apiKey = (string) config('groq_api_key', '');
if ($apiKey === '') {
    ai_error('AI features are not configured on this server yet.', 503);
}

$maxBytes = (int) config('ai_max_body_mb', 8) * 1024 * 1024;
$raw = (string) file_get_contents('php://input', false, null, 0, $maxBytes + 1);
if (strlen($raw) > $maxBytes) {
    ai_error('Request is too large. Try a smaller image.', 413);
}

$body = json_decode($raw, true);
if (!is_array($body) || !isset($body['messages']) || !is_array($body['messages']) || $body['messages'] === []) {
    ai_error('Request body must be a JSON object with a "messages" array.', 400);
}

$models = (array) config('ai_models', []);
$model = (string) ($body['model'] ?? ($models[0] ?? ''));
if (!in_array($model, $models, true)) {
    ai_error('Model is not allowed.', 400);
}

$limit = (array) config('ai_rate_limit', ['max' => 30, 'window_minutes' => 60]);
$ip = client_ip();
if (Repository::recentAiRequestCount($ip, (int) $limit['window_minutes']) >= (int) $limit['max']) {
    header('Retry-After: 600');
    ai_error('You have used the AI tools a lot recently. Please try again in a little while.', 429);
}

// Forward only the fields the app uses.
$payload = [
    'model'      => $model,
    'messages'   => $body['messages'],
    'max_tokens' => min((int) ($body['max_tokens'] ?? 4000), (int) config('ai_max_tokens', 8000)),
];
if (isset($body['temperature']) && is_numeric($body['temperature'])) {
    $payload['temperature'] = max(0.0, min(2.0, (float) $body['temperature']));
}

$ch = curl_init((string) config('ai_upstream_url', 'https://api.groq.com/openai/v1/chat/completions'));
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT        => 120,
]);
$response = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$curlError = curl_error($ch);
unset($ch); // curl_close() is a deprecated no-op since PHP 8.0

Repository::logAiRequest($ip, $response === false ? 0 : $status);

if ($response === false) {
    ai_error('Could not reach the AI service: ' . $curlError, 502);
}

http_response_code($status ?: 502);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo $response;
