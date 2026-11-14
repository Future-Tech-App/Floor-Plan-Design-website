<?php
declare(strict_types=1);

/**
 * Global helper functions used across the site, API and admin area.
 */

/** Read a configuration value (see config/app.php). */
function config(string $key, mixed $default = null): mixed
{
    static $items = null;
    if ($items === null) {
        $items = require ROOT_DIR . '/config/app.php';
        // local.php: per-server settings. secrets.php: API keys (written by the
        // deploy workflow from GitHub secrets). Both are git-ignored.
        foreach (['local.php', 'secrets.php'] as $override) {
            $file = ROOT_DIR . '/config/' . $override;
            if (is_file($file)) {
                $items = array_replace($items, require $file);
            }
        }
    }
    return $items[$key] ?? $default;
}

/** HTML-escape a value. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build a site URL for a path such as "/support" (respects base_path). */
function url(string $path = '/'): string
{
    $base = rtrim((string) config('base_path', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/** Is the current request a POST? */
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Read and trim a POST/GET input value. */
function input(string $key, string $source = 'post'): string
{
    $bag = $source === 'get' ? $_GET : $_POST;
    $value = $bag[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** Redirect to an internal path and stop. */
function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

/** Send a JSON response and stop. */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/** Stop with an HTTP error status and a plain message. */
function abort(int $status, string $message = ''): never
{
    http_response_code($status);
    if (str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/')) {
        json_response(['error' => $message ?: 'Error'], $status);
    }
    render('pages/error', ['status' => $status, 'message' => $message], 'site');
    exit;
}

/** Client IP. Proxy headers are ignored unless you add trusted-proxy handling here. */
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Verify the CSRF token on a POST request. Aborts with 419 on failure. */
function csrf_verify(): void
{
    $sent = $_POST['_csrf'] ?? '';
    $known = $_SESSION['_csrf'] ?? '';
    if (!is_string($sent) || $known === '' || !hash_equals($known, $sent)) {
        abort(419, 'Your session expired. Please go back, refresh the page and try again.');
    }
}

/* ---------- Flash messages ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return array<int, array{type: string, message: string}> */
function flash_pull(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $items;
}

/* ---------- Views ---------- */

/**
 * Render a view inside a layout.
 *
 * @param string $view   Path under views/ without extension, e.g. "pages/home".
 * @param array  $data   Variables made available to the view.
 * @param string $layout "site" (public pages) or "admin".
 */
function render(string $view, array $data = [], string $layout = 'site'): void
{
    $data['site'] = site_context();
    $data['flashes'] = flash_pull();
    extract($data, EXTR_SKIP);

    ob_start();
    require ROOT_DIR . '/views/' . $view . '.php';
    $content = ob_get_clean();

    require ROOT_DIR . '/views/layout/' . $layout . '.php';
}

/** Values every page can use (name, store links, latest version...). */
function site_context(): array
{
    static $context = null;
    if ($context === null) {
        // Admin → Site settings override the config defaults.
        $s = Repository::allSettings();
        $pick = static fn (string $key, string $default): string => ($s['site_' . $key] ?? '') !== '' ? $s['site_' . $key] : $default;
        $context = [
            'name'          => config('app_name'),
            'tagline'       => $pick('tagline', (string) config('tagline')),
            'company'       => config('company'),
            'support_email' => $pick('support_email', (string) config('support_email')),
            'site_url'      => rtrim((string) config('site_url'), '/'),
            'android_url'   => $pick('android_url', (string) config('android_url')),
            'ios_url'       => $pick('ios_url', (string) config('ios_url')),
            'version'       => $s['latest_version'] ?? (string) config('app_version'),
            'year'          => date('Y'),
            'announcement'  => ($s['announce_enabled'] ?? '0') === '1' && trim($s['announce_text'] ?? '') !== ''
                ? ['text' => $s['announce_text'], 'link' => $s['announce_link'] ?? '', 'id' => substr(md5($s['announce_text']), 0, 8)]
                : null,
        ];
    }
    return $context;
}

/** Short, readable date for admin tables. */
function fmt_date(?string $utc): string
{
    if (!$utc) {
        return '';
    }
    $ts = strtotime($utc . ' UTC');
    return $ts === false ? $utc : date('M j, Y g:i A', $ts);
}

/** Small banner set in Admin → Site settings (shown on the website and web app). */
function announcement_bar(array $site): string
{
    $a = $site['announcement'] ?? null;
    if (!$a) {
        return '';
    }
    $text = e($a['text']);
    $link = (string) $a['link'];
    if ($link !== '' && preg_match('#^(https?://|/)#', $link)) {
        $text .= ' <a href="' . e($link) . '">Learn more →</a>';
    }
    return '<div class="announce" data-announce="' . e($a['id']) . '" role="status"><span>' . $text . '</span>'
        . '<button type="button" aria-label="Dismiss" onclick="try{localStorage.setItem(\'fpd_announce\',this.parentNode.dataset.announce)}catch(e){};this.parentNode.remove()">×</button></div>'
        . '<script>try{if(localStorage.getItem("fpd_announce")==="' . e($a['id']) . '")document.currentScript.previousElementSibling.remove()}catch(e){}</script>';
}

/** URL of a CSS/JS file with a version stamp, so browsers reload it after every change. */
function asset(string $path): string
{
    $file = ROOT_DIR . '/public' . $path;
    $v = is_file($file) ? filemtime($file) : false;
    return url($path) . ($v ? '?v=' . $v : '');
}
