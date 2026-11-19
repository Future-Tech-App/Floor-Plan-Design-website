<?php
declare(strict_types=1);

/**
 * /api/v1/plans.php — save, delete and (web app only) download plans.
 *
 * Android/iOS apps: each phone sends a random client id (32–64 hex characters) that it keeps
 * private. Only a hash of it is stored, and only that device can update or delete its plans.
 *
 * Web app (source "web"): the user must be signed in (Sign in with Google, /api/v1/auth.php).
 * Plans are stored under the account, not the browser, so the same plans appear in every
 * browser the user signs in to and show with their email in Admin → Saved Plans.
 *
 * POST save:   {"client": "<hex>", "source": "web|android|ios", "plan": {... id, name, ...}}
 * POST delete: {"client": "<hex>", "source": "web|android|ios", "delete": "<plan id>"}
 * GET  list:   ?list=1        → {"ok": true, "plans": [{"id", "name", "modifiedAt"}]}   (web, signed in)
 * GET  one:    ?id=<plan id>  → {"ok": true, "plan": {...}}                              (web, signed in)
 * Reply:  {"ok": true, ...}  or  {"ok": false, "error": "..."}
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';

function plans_error(string $message, int $status): never
{
    json_response(['ok' => false, 'error' => $message], $status);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST' && $method !== 'GET') {
    header('Allow: GET, POST');
    plans_error('Method not allowed', 405);
}

// Browsers must be same-origin; the mobile apps send no Origin header.
try {
    GroqClient::assertSameOrigin();
} catch (GroqException $e) {
    plans_error($e->getMessage(), $e->status);
}

$userId = UserAuth::id();

// Download (web app, signed-in user's own plans only).
if ($method === 'GET') {
    header('Cache-Control: no-store');
    if ($userId === null) {
        plans_error('Sign in to see your plans.', 401);
    }
    $owner = PlanStore::userOwner($userId);
    $planId = (string) ($_GET['id'] ?? '');
    if ($planId !== '') {
        $plan = PlanStore::findForOwner($owner, $planId);
        $plan === null ? plans_error('Plan not found.', 404) : json_response(['ok' => true, 'plan' => $plan]);
    }
    json_response(['ok' => true, 'plans' => PlanStore::listForOwner($owner)]);
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > PlanStore::MAX_BYTES + 4096) {
    plans_error('This plan is too large to back up (2 MB maximum).', 413);
}
$raw = (string) file_get_contents('php://input', false, null, 0, PlanStore::MAX_BYTES + 4097);
if (strlen($raw) > PlanStore::MAX_BYTES + 4096) {
    plans_error('This plan is too large to back up (2 MB maximum).', 413);
}
$body = json_decode($raw, true);
if (!is_array($body)) {
    plans_error('Send a JSON object.', 400);
}

$client = (string) ($body['client'] ?? '');
if (!preg_match('/^[a-f0-9]{32,64}$/', $client)) {
    plans_error('Missing or invalid client id.', 400);
}
$source = (string) ($body['source'] ?? '');
$isWeb = $source === 'web' || ($source === '' && isset($body['plan']));
if (!isset(PlanStore::SOURCES[$source])) {
    $source = 'web';
}
if ($isWeb && $userId === null) {
    plans_error('Sign in to save your plans.', 401);
}
$owner = $isWeb ? PlanStore::userOwner((int) $userId) : PlanStore::ownerHash($client);

// Per-IP write limit.
$ip = client_ip();
$pdo = Database::pdo();
$st = $pdo->prepare("SELECT COUNT(*) FROM api_hits WHERE bucket = 'plans' AND ip = :ip AND created_at >= datetime('now', '-1 hour')");
$st->execute(['ip' => $ip]);
if ((int) $st->fetchColumn() >= (int) config('plans_rate_per_hour', 600)) {
    header('Retry-After: 600');
    plans_error('Too many saves from this network. Please try again later.', 429);
}
$pdo->prepare("INSERT INTO api_hits (ip, bucket) VALUES (:ip, 'plans')")->execute(['ip' => $ip]);
if (random_int(1, 50) === 1) {
    $pdo->exec("DELETE FROM api_hits WHERE created_at < datetime('now', '-1 day')");
}

// Delete.
if (isset($body['delete'])) {
    $planId = (string) $body['delete'];
    if (!preg_match('/^[A-Za-z0-9_.:-]{1,80}$/', $planId)) {
        plans_error('Invalid plan id.', 400);
    }
    json_response(['ok' => true, 'deleted' => PlanStore::deleteForOwner($owner, $planId)]);
}

// Save.
$plan = $body['plan'] ?? null;
if (!is_array($plan)) {
    plans_error('Missing plan.', 400);
}
$planId = (string) ($plan['id'] ?? '');
if (!preg_match('/^[A-Za-z0-9_.:-]{1,80}$/', $planId)) {
    plans_error('The plan needs an id.', 400);
}
if (!isset($plan['walls']) && !isset($plan['floors'])) {
    plans_error('This does not look like a floor plan.', 422);
}
if (!PlanStore::exists($owner, $planId) && PlanStore::countForOwner($owner) >= PlanStore::MAX_PER_OWNER) {
    plans_error(($isWeb ? 'Your account' : 'This device') . ' has reached the limit of ' . PlanStore::MAX_PER_OWNER . ' saved plans.', 409);
}

$json = json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false || strlen($json) > PlanStore::MAX_BYTES) {
    plans_error('This plan is too large to back up (2 MB maximum).', 413);
}

try {
    $id = PlanStore::save($owner, $planId, $source, $plan, $json, $isWeb ? $userId : null);
} catch (Throwable $e) {
    error_log('plans.php: ' . $e->getMessage());
    plans_error('The plan could not be saved. Please try again.', 500);
}
json_response(['ok' => true, 'id' => $id]);
