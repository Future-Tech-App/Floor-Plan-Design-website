<?php
declare(strict_types=1);

/**
 * Web app sign-in (Sign in with Google).
 *
 * GET                                    → {"ok": true, "user": {...} | null, "google": bool}
 * POST {"action": "google", "credential": "<Google ID token>"} → {"ok": true, "user": {...}}
 * POST {"action": "signout"}             → {"ok": true}
 *
 * Same-origin only. The ID token is verified on the server (src/GoogleAuth.php).
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';

header('Cache-Control: no-store');

function auth_error(string $message, int $status): never
{
    json_response(['ok' => false, 'error' => $message], $status);
}

try {
    GroqClient::assertSameOrigin();
} catch (GroqException $e) {
    auth_error($e->getMessage(), $e->status);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    json_response(['ok' => true, 'user' => UserAuth::publicUser(UserAuth::current()), 'google' => GoogleAuth::configured()]);
}
if ($method !== 'POST') {
    header('Allow: GET, POST');
    auth_error('Method not allowed', 405);
}

$body = json_decode((string) file_get_contents('php://input', false, null, 0, 16384), true);
if (!is_array($body)) {
    auth_error('Send a JSON object.', 400);
}

$action = (string) ($body['action'] ?? '');
if ($action === 'signout') {
    UserAuth::logout();
    json_response(['ok' => true]);
}
if ($action !== 'google') {
    auth_error('Unknown action.', 400);
}
if (!GoogleAuth::configured()) {
    auth_error('Google sign-in is not set up on this site yet.', 503);
}

// Per-IP limit on sign-in attempts.
$ip = client_ip();
$pdo = Database::pdo();
$st = $pdo->prepare("SELECT COUNT(*) FROM api_hits WHERE bucket = 'auth' AND ip = :ip AND created_at >= datetime('now', '-1 hour')");
$st->execute(['ip' => $ip]);
if ((int) $st->fetchColumn() >= 60) {
    header('Retry-After: 600');
    auth_error('Too many sign-in attempts. Please try again later.', 429);
}
$pdo->prepare("INSERT INTO api_hits (ip, bucket) VALUES (:ip, 'auth')")->execute(['ip' => $ip]);

try {
    $claims = GoogleAuth::verify((string) ($body['credential'] ?? ''));
} catch (Throwable $e) {
    error_log('auth.php: ' . $e->getMessage());
    auth_error('Google sign-in could not be confirmed. Please try again.', 401);
}
$user = UserAuth::loginWithGoogle($claims);
json_response(['ok' => true, 'user' => UserAuth::publicUser($user)]);
