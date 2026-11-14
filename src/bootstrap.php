<?php
declare(strict_types=1);

/**
 * Shared bootstrap: included by every entry point (public pages, API, admin, CLI).
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    http_response_code(500);
    exit('PHP 8.1 or newer is required.');
}

require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/icons.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/Repository.php';
require_once ROOT_DIR . '/src/VersionGate.php';
require_once ROOT_DIR . '/src/ContactService.php';
require_once ROOT_DIR . '/src/Auth.php';
require_once ROOT_DIR . '/src/Calculators.php';
require_once ROOT_DIR . '/src/GroqClient.php';
require_once ROOT_DIR . '/src/AiPlanGenerator.php';
require_once ROOT_DIR . '/src/PlanAnalyzer.php';
require_once ROOT_DIR . '/src/WebApp.php';
require_once ROOT_DIR . '/src/Analytics.php';
require_once ROOT_DIR . '/src/AdminRepo.php';
require_once ROOT_DIR . '/src/AdminApp.php';
require_once ROOT_DIR . '/src/PlanStore.php';
require_once ROOT_DIR . '/src/NoteStore.php';
require_once ROOT_DIR . '/src/Pricing.php';

date_default_timezone_set((string) config('timezone', 'UTC'));

// Session (web requests only; the CLI does not need one)
if (PHP_SAPI !== 'cli') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');

    session_name('fpd_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Basic security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
}
