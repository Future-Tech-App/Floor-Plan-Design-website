<?php
declare(strict_types=1);

/**
 * GET /api/v1/version?platform=android|ios&version=3.0.2+32
 *
 * Returns the version-gate decision for an installed app build.
 *
 * Response (200):
 * {
 *   "status": "ok" | "optional_update" | "update_required" | "blocked",
 *   "installed_version": "3.0.2+32",
 *   "minimum_version": "3.0.0",
 *   "optional_version": "3.0.2",
 *   "latest_version": "3.0.2",
 *   "message": "",
 *   "store_url": "https://...",
 *   "checked_at": "2026-11-13T10:00:00+00:00"
 * }
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['error' => 'Method not allowed'], 405);
}

$platform = strtolower((string) ($_GET['platform'] ?? ''));
if (!in_array($platform, ['android', 'ios'], true)) {
    json_response(['error' => 'Query parameter "platform" must be "android" or "ios".'], 400);
}

// A literal "+" in a query string decodes to a space, so "3.0.2+32" arrives as "3.0.2 32".
// Accept both that and the properly encoded "3.0.2%2B32".
$version = str_replace(' ', '+', trim((string) ($_GET['version'] ?? '')));
if ($version === '' || strlen($version) > 32 || !preg_match('/^\d+(\.\d+){0,2}(\+\d+)?$/', $version)) {
    json_response(['error' => 'Query parameter "version" must look like 3.0.2 or 3.0.2+32.'], 400);
}

$settings = Repository::allSettings();
$decision = VersionGate::evaluate($version, $settings);

$storeUrl = $platform === 'ios' ? (string) config('ios_url', '') : (string) config('android_url', '');

json_response($decision + [
    'store_url'  => $storeUrl,
    'checked_at' => gmdate('c'),
]);
