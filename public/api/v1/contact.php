<?php
declare(strict_types=1);

/**
 * POST /api/v1/contact
 *
 * Accepts JSON or form-encoded bodies:
 *   name, email, subject, message   (required)
 *   website                         (honeypot — must stay empty)
 *
 * 201 on success, 422 with per-field "errors" on validation failure,
 * 429 when the IP exceeds the rate limit.
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    json_response(['error' => 'Method not allowed'], 405);
}

// Accept JSON bodies as well as classic form posts.
$payload = $_POST;
$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
if (str_contains($contentType, 'application/json')) {
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($decoded)) {
        json_response(['error' => 'Request body must be a JSON object.'], 400);
    }
    $payload = $decoded;
}

$result = ContactService::submit($payload);

if ($result['ok']) {
    json_response(['message' => $result['message'], 'id' => $result['id'] ?? null], 201);
}

$body = ['error' => $result['message']];
if (!empty($result['errors'])) {
    $body['errors'] = $result['errors'];
}
json_response($body, (int) $result['status']);
