<?php
declare(strict_types=1);

/**
 * POST /api/v1/notes.php — back up a device's notes.
 *
 * Body: {"client": "<hex>", "source": "web|android|ios", "notes": [{title, content, ...}, ...]}
 * The list replaces everything stored for that device (send [] after deleting all notes).
 * Same private client id and limits model as /api/v1/plans.php.
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';

function notes_error(string $message, int $status): never
{
    json_response(['ok' => false, 'error' => $message], $status);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    notes_error('Method not allowed', 405);
}
try {
    GroqClient::assertSameOrigin();
} catch (GroqException $e) {
    notes_error($e->getMessage(), $e->status);
}

$max = 2 * 1024 * 1024;
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $max) {
    notes_error('Your notes are too large to back up (2 MB maximum).', 413);
}
$raw = (string) file_get_contents('php://input', false, null, 0, $max + 1);
if (strlen($raw) > $max) {
    notes_error('Your notes are too large to back up (2 MB maximum).', 413);
}
$body = json_decode($raw, true);
if (!is_array($body)) {
    notes_error('Send a JSON object.', 400);
}
$client = (string) ($body['client'] ?? '');
if (!preg_match('/^[a-f0-9]{32,64}$/', $client)) {
    notes_error('Missing or invalid client id.', 400);
}
if (!isset($body['notes']) || !is_array($body['notes']) || !array_is_list($body['notes'])) {
    notes_error('Send "notes" as a list.', 400);
}

$ip = client_ip();
$pdo = Database::pdo();
$st = $pdo->prepare("SELECT COUNT(*) FROM api_hits WHERE bucket = 'notes' AND ip = :ip AND created_at >= datetime('now', '-1 hour')");
$st->execute(['ip' => $ip]);
if ((int) $st->fetchColumn() >= (int) config('notes_rate_per_hour', 300)) {
    header('Retry-After: 600');
    notes_error('Too many saves from this network. Please try again later.', 429);
}
$pdo->prepare("INSERT INTO api_hits (ip, bucket) VALUES (:ip, 'notes')")->execute(['ip' => $ip]);

$source = (string) ($body['source'] ?? 'web');
if (!isset(PlanStore::SOURCES[$source])) {
    $source = 'web';
}
try {
    $n = NoteStore::replaceForOwner(PlanStore::ownerHash($client), $source, $body['notes']);
} catch (Throwable $e) {
    error_log('notes.php: ' . $e->getMessage());
    notes_error('Your notes could not be saved. Please try again.', 500);
}
json_response(['ok' => true, 'stored' => $n]);
