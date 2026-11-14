<?php
declare(strict_types=1);

/**
 * Admin panel (/admin/*): router and page handlers. Views live in views/admin/,
 * the layout in views/layout/admin.php, styles in public/assets/admin/.
 */
final class AdminApp
{
    private const PER_PAGE = 20;
    private const LOGIN_MAX_FAILS = 5;       // per IP …
    private const LOGIN_WINDOW_MIN = 15;     // … per 15 minutes

    public static function dispatch(string $sub): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store, private');
        $sub = '/' . trim($sub, '/');

        // Old file-based URLs from the first admin version.
        $legacy = ['/index.php' => '/admin', '/login.php' => '/admin/login', '/settings.php' => '/admin/versions', '/message.php' => '/admin/messages'];
        if (isset($legacy[$sub])) {
            redirect($legacy[$sub] . ($sub === '/message.php' && isset($_GET['id']) ? '/' . (int) $_GET['id'] : ''));
        }
        if ($sub === '/setup.php') {
            redirect('/admin/setup?token=' . rawurlencode(input('token', 'get')));
        }

        // Pages that work without signing in.
        if ($sub === '/login') {
            self::login();
            return;
        }
        if ($sub === '/setup') {
            self::setup();
            return;
        }
        if ($sub === '/logout') {
            if (is_post()) {
                csrf_verify();
                if (Auth::check()) {
                    AdminRepo::log('sign-out');
                }
                Auth::logout();
                flash('success', 'You have been signed out.');
            }
            redirect('/admin/login');
        }

        Auth::require();

        match (true) {
            $sub === '/'                                  => self::dashboard(),
            $sub === '/messages'                          => self::messages(),
            $sub === '/messages/export'                   => self::exportMessages(),
            (bool) preg_match('#^/messages/(\d+)$#', $sub, $m) => self::message((int) $m[1]),
            $sub === '/plans'                             => self::plans(),
            (bool) preg_match('#^/plans/(\d+)$#', $sub, $m)    => self::plan((int) $m[1]),
            (bool) preg_match('#^/plans/(\d+)/download$#', $sub, $m) => self::planDownload((int) $m[1]),
            $sub === '/analytics'                         => self::analytics(),
            $sub === '/ai'                                => self::ai(),
            $sub === '/templates'                         => self::templates(),
            $sub === '/versions'                          => self::versions(),
            $sub === '/settings'                          => self::settings(),
            $sub === '/users'                             => self::users(),
            $sub === '/account'                           => self::account(),
            $sub === '/activity'                          => self::activity(),
            default                                       => abort(404, 'That admin page does not exist.'),
        };
    }

    private static function page(string $view, string $title, string $nav, array $data = []): void
    {
        render('admin/' . $view, $data + ['title' => $title . ' — Admin', 'pageTitle' => $title, 'nav' => $nav], 'admin');
    }

    /* ================================================================ sign in */

    private static function login(): void
    {
        if (Auth::check()) {
            redirect('/admin');
        }
        if (Repository::adminCount() === 0) {
            flash('error', 'No admin account exists yet. Create one with bin/create-admin.php or the setup link.');
        }
        $username = '';
        if (is_post()) {
            csrf_verify();
            $ip = client_ip();
            $username = input('username');
            if (AdminRepo::failedLogins($ip, self::LOGIN_WINDOW_MIN) >= self::LOGIN_MAX_FAILS) {
                flash('error', 'Too many failed attempts. Please wait ' . self::LOGIN_WINDOW_MIN . ' minutes and try again.');
            } elseif (Auth::attempt($username, (string) ($_POST['password'] ?? ''))) {
                AdminRepo::clearFailedLogins($ip);
                AdminRepo::touchLogin(Auth::id());
                AdminRepo::log('sign-in');
                redirect('/admin');
            } else {
                AdminRepo::recordFailedLogin($ip, $username);
                usleep(400000);
                flash('error', 'The username or password is incorrect.');
            }
        }
        render('admin/login', ['title' => 'Sign in — Admin', 'username' => $username], 'admin_auth');
    }

    private static function setup(): void
    {
        $tokenFile = ROOT_DIR . '/config/setup_token.txt';
        $expected = is_file($tokenFile) ? trim((string) file_get_contents($tokenFile)) : '';
        $token = is_post() ? input('token') : input('token', 'get');
        if (Repository::adminCount() > 0 || strlen($expected) < 32 || !hash_equals($expected, $token)) {
            abort(404, 'The page you are looking for does not exist.');
        }
        $errors = [];
        $username = '';
        if (is_post()) {
            csrf_verify();
            $username = input('username');
            $errors = self::validateNewAdmin($username, (string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));
            if ($errors === []) {
                Repository::saveAdmin($username, password_hash((string) $_POST['password'], PASSWORD_DEFAULT));
                @unlink($tokenFile);
                flash('success', 'Admin account created. Please sign in.');
                redirect('/admin/login');
            }
        }
        render('admin/setup', ['title' => 'Create admin account', 'token' => $token, 'username' => $username, 'errors' => $errors], 'admin_auth');
    }

    private static function validateNewAdmin(string $username, string $password, string $confirm): array
    {
        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $errors['username'] = 'Use 3–50 letters, digits, dots, dashes or underscores.';
        } elseif (Repository::findAdmin($username) !== null) {
            $errors['username'] = 'That username is already taken.';
        }
        if (strlen($password) < 10) {
            $errors['password'] = 'Password must be at least 10 characters.';
        } elseif (!hash_equals($password, $confirm)) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }
        return $errors;
    }

    /* ================================================================ dashboard */

    private static function dashboard(): void
    {
        $views7 = AdminRepo::viewsTotal(6, 0);
        $viewsPrev = AdminRepo::viewsTotal(13, 7);
        self::page('dashboard', 'Dashboard', 'dashboard', [
            'messages'  => AdminRepo::messageCounts(),
            'plans'     => PlanStore::stats(),
            'recentPlans' => PlanStore::search(1, 5, '', '')['items'],
            'latest'    => AdminRepo::latestMessages(6),
            'views7'    => $views7,
            'viewsDiff' => $viewsPrev > 0 ? (int) round(($views7 - $viewsPrev) / $viewsPrev * 100) : null,
            'today'     => AdminRepo::viewsTotal(0, 0),
            'viewsDays' => AdminRepo::viewsByDay(14),
            'ai'        => AdminRepo::aiTotals(7),
            'aiDays'    => AdminRepo::aiByDay(14),
            'topPages'  => AdminRepo::topPages(7, 6),
            'system'    => self::systemStatus(),
        ]);
    }

    private static function systemStatus(): array
    {
        $tplDir = ROOT_DIR . '/public/assets/app/templates';
        return [
            ['PHP version', PHP_VERSION, version_compare(PHP_VERSION, '8.1', '>=')],
            ['Database', self::bytes(AdminRepo::dbSize()) . ' SQLite', true],
            ['AI key', (string) config('groq_api_key', '') !== '' ? 'Configured' : 'Not set', (string) config('groq_api_key', '') !== ''],
            ['AI tools', GroqClient::enabled() ? 'On' : 'Switched off', GroqClient::enabled()],
            ['Template uploads', is_writable($tplDir) ? 'Writable' : 'Folder not writable', is_writable($tplDir)],
            ['App version', (string) site_context()['version'], true],
        ];
    }

    /* ================================================================ messages */

    private static function messages(): void
    {
        $filter = input('filter', 'get');
        if (!in_array($filter, ['all', 'unread', 'read'], true)) {
            $filter = 'all';
        }
        $search = mb_substr(input('q', 'get'), 0, 100);
        $back = '/admin/messages?' . http_build_query(array_filter(['filter' => $filter !== 'all' ? $filter : '', 'q' => $search]));

        if (is_post()) {
            csrf_verify();
            $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), static fn ($i) => $i > 0));
            $action = input('action');
            if (!$ids) {
                flash('error', 'Select at least one message first.');
            } elseif (in_array($action, ['read', 'unread', 'delete'], true)) {
                foreach ($ids as $id) {
                    if ($action === 'delete') {
                        Repository::deleteMessage($id);
                    } else {
                        Repository::setMessageRead($id, $action === 'read');
                    }
                }
                AdminRepo::log('messages-' . $action, count($ids) . ' message(s)');
                flash('success', count($ids) . ' message' . (count($ids) > 1 ? 's' : '') . ($action === 'delete' ? ' deleted.' : ' marked as ' . $action . '.'));
            }
            redirect($back);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $list = AdminRepo::searchMessages($page, self::PER_PAGE, $filter, $search);
        self::page('messages', 'Messages', 'messages', [
            'filter' => $filter, 'search' => $search, 'page' => $page,
            'pages'  => max(1, (int) ceil($list['total'] / self::PER_PAGE)),
            'total'  => $list['total'], 'messages' => $list['items'], 'counts' => AdminRepo::messageCounts(),
        ]);
    }

    private static function message(int $id): void
    {
        $msg = Repository::findMessage($id);
        if ($msg === null) {
            flash('error', 'That message no longer exists.');
            redirect('/admin/messages');
        }
        if (is_post()) {
            csrf_verify();
            $action = input('action');
            if ($action === 'delete') {
                Repository::deleteMessage($id);
                AdminRepo::log('message-delete', '#' . $id . ' from ' . $msg['email']);
                flash('success', 'Message deleted.');
                redirect('/admin/messages');
            }
            if ($action === 'unread') {
                Repository::setMessageRead($id, false);
                flash('success', 'Marked as unread.');
                redirect('/admin/messages');
            }
            redirect('/admin/messages/' . $id);
        }
        if (!(int) $msg['is_read']) {
            Repository::setMessageRead($id, true);
            $msg['is_read'] = 1;
        }
        self::page('message', 'Message', 'messages', ['msg' => $msg, 'nav2' => AdminRepo::neighbours($id)]);
    }

    private static function exportMessages(): void
    {
        AdminRepo::log('messages-export');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="messages-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
        fputcsv($out, ['ID', 'Date (UTC)', 'Name', 'Email', 'Subject', 'Message', 'Read'], ',', '"', '');
        foreach (AdminRepo::allMessages() as $m) {
            // Prefix values that spreadsheet apps would treat as formulas.
            $row = array_map(static fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : $v, [
                $m['id'], $m['created_at'], $m['name'], $m['email'], $m['subject'], $m['message'], $m['is_read'] ? 'yes' : 'no',
            ]);
            fputcsv($out, $row, ',', '"', '');
        }
        fclose($out);
        exit;
    }

    /* ================================================================ plans */

    private static function plans(): void
    {
        $source = input('source', 'get');
        if (!isset(PlanStore::SOURCES[$source])) {
            $source = '';
        }
        $q = mb_substr(input('q', 'get'), 0, 100);
        if (is_post()) {
            csrf_verify();
            $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), static fn ($i) => $i > 0));
            if ($ids && input('action') === 'delete') {
                foreach ($ids as $id) {
                    PlanStore::delete($id);
                }
                AdminRepo::log('plans-delete', count($ids) . ' plan(s)');
                flash('success', count($ids) . ' plan' . (count($ids) > 1 ? 's' : '') . ' deleted.');
            } else {
                flash('error', 'Select at least one plan first.');
            }
            redirect('/admin/plans?' . http_build_query(array_filter(['source' => $source, 'q' => $q])));
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $list = PlanStore::search($page, self::PER_PAGE, $source, $q);
        self::page('plans', 'Saved Plans', 'plans', [
            'plans' => $list['items'], 'total' => $list['total'], 'page' => $page,
            'pages' => max(1, (int) ceil($list['total'] / self::PER_PAGE)),
            'source' => $source, 'q' => $q, 'stats' => PlanStore::stats(),
        ]);
    }

    private static function plan(int $id): void
    {
        $row = PlanStore::find($id);
        if ($row === null) {
            flash('error', 'That plan no longer exists.');
            redirect('/admin/plans');
        }
        if (is_post()) {
            csrf_verify();
            if (input('action') === 'delete') {
                PlanStore::delete($id);
                AdminRepo::log('plan-delete', '#' . $id . ' ' . $row['name']);
                flash('success', 'Plan "' . $row['name'] . '" deleted.');
                redirect('/admin/plans');
            }
            redirect('/admin/plans/' . $id);
        }
        $data = json_decode((string) $row['data'], true) ?: [];
        $floors = PlanStore::floorNames($data);
        $floor = max(0, min(count($floors) - 1, (int) ($_GET['floor'] ?? 0)));
        self::page('plan', $row['name'], 'plans', [
            'row' => $row, 'svg' => PlanStore::svg($data, $floor), 'floors' => $floors, 'floor' => $floor,
            'deviceCount' => PlanStore::ownerPlanCount((string) $row['owner']),
        ]);
    }

    private static function planDownload(int $id): void
    {
        $row = PlanStore::find($id);
        if ($row === null) {
            abort(404, 'That plan no longer exists.');
        }
        $name = preg_replace('/[^A-Za-z0-9_\- ]+/', '_', (string) $row['name']) ?: 'plan';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '.json"');
        echo json_encode(json_decode((string) $row['data'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ================================================================ analytics */

    private static function analytics(): void
    {
        $days = (int) ($_GET['days'] ?? 30);
        if (!in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }
        $byDay = AdminRepo::viewsByDay($days);
        self::page('analytics', 'Analytics', 'analytics', [
            'days'   => $days,
            'byDay'  => $byDay,
            'appDay' => AdminRepo::viewsByDay($days, '/app'),
            'total'  => array_sum($byDay),
            'split'  => AdminRepo::viewsSplit($days),
            'top'    => AdminRepo::topPages($days, 15),
        ]);
    }

    /* ================================================================ AI usage */

    private static function ai(): void
    {
        if (is_post()) {
            csrf_verify();
            $action = input('action');
            if ($action === 'save') {
                $enabled = isset($_POST['ai_enabled']) ? '1' : '0';
                $max = (int) ($_POST['ai_rate_max'] ?? 0);
                if ($max < 1 || $max > 1000) {
                    flash('error', 'The hourly limit must be between 1 and 1000.');
                    redirect('/admin/ai');
                }
                Repository::setSetting('ai_enabled', $enabled);
                Repository::setSetting('ai_rate_max', (string) $max);
                AdminRepo::log('ai-settings', 'enabled=' . $enabled . ', limit=' . $max . '/hour');
                flash('success', 'AI settings saved.');
            } elseif ($action === 'purge') {
                $n = AdminRepo::aiPurge(30);
                AdminRepo::log('ai-purge', $n . ' row(s)');
                flash('success', 'Removed ' . $n . ' log entr' . ($n === 1 ? 'y' : 'ies') . ' older than 30 days.');
            }
            redirect('/admin/ai');
        }
        self::page('ai', 'AI Usage', 'ai', [
            'byDay'    => AdminRepo::aiByDay(30),
            'totals7'  => AdminRepo::aiTotals(7),
            'totals30' => AdminRepo::aiTotals(30),
            'statuses' => AdminRepo::aiStatuses(30),
            'ips'      => AdminRepo::aiTopIps(7),
            'enabled'  => GroqClient::enabled(),
            'limit'    => GroqClient::hourlyLimit(),
            'hasKey'   => (string) config('groq_api_key', '') !== '',
        ]);
    }

    /* ================================================================ templates */

    private const TPL_CATS = ['1bed' => '1 Bed Apartment', '2bed' => '2 Bed Apartment', '3bed' => '3 Bed Apartment'];

    private static function templates(): void
    {
        $dir = ROOT_DIR . '/public/assets/app/templates';
        if (is_post()) {
            csrf_verify();
            $action = input('action');
            if ($action === 'upload') {
                self::uploadTemplate($dir);
            } elseif ($action === 'delete') {
                $file = basename(input('file'));
                if (preg_match('/^\dbed-\d+\.(jpe?g|png|webp)$/', $file) && is_file($dir . '/' . $file)) {
                    @unlink($dir . '/' . $file);
                    AdminRepo::log('template-delete', $file);
                    flash('success', 'Template ' . $file . ' deleted.');
                } else {
                    flash('error', 'That template could not be found.');
                }
            }
            redirect('/admin/templates');
        }
        self::page('templates', 'Templates', 'templates', [
            'templates' => WebApp::templates(), 'cats' => self::TPL_CATS, 'writable' => is_writable($dir),
        ]);
    }

    private static function uploadTemplate(string $dir): void
    {
        $cat = input('cat');
        $f = $_FILES['image'] ?? null;
        if (!isset(self::TPL_CATS[$cat])) {
            flash('error', 'Choose a category.');
            return;
        }
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            flash('error', ($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'That file is larger than the server allows.' : 'Choose an image to upload.');
            return;
        }
        if ($f['size'] > 5 * 1024 * 1024) {
            flash('error', 'Images must be 5 MB or smaller.');
            return;
        }
        $info = @getimagesize((string) $f['tmp_name']);
        $ext = match ($info[2] ?? 0) { IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', default => null };
        if ($ext === null) {
            flash('error', 'Upload a JPG, PNG or WebP image.');
            return;
        }
        if (!is_writable($dir)) {
            flash('error', 'The templates folder is not writable on this server.');
            return;
        }
        $next = 1;
        foreach (WebApp::templates() as $t) {
            if ($t['cat'] === $cat) {
                $next = max($next, $t['num'] + 1);
            }
        }
        $name = $cat . '-' . $next . '.' . $ext;
        if (!move_uploaded_file((string) $f['tmp_name'], $dir . '/' . $name)) {
            flash('error', 'The image could not be saved.');
            return;
        }
        @chmod($dir . '/' . $name, 0644);
        AdminRepo::log('template-upload', $name);
        flash('success', 'Template added as ' . self::TPL_CATS[$cat] . ' — Plan ' . $next . '.');
    }

    /* ================================================================ app versions */

    private static function versions(): void
    {
        $valid = static fn (string $v): bool => $v === '' || (bool) preg_match('/^\d+(\.\d+){0,2}$/', $v);
        $errors = [];
        if (is_post()) {
            csrf_verify();
            $values = [
                'latest_version'                => input('latest_version'),
                'force_update_enabled'          => isset($_POST['force_update_enabled']) ? '1' : '0',
                'force_update_min_version'      => input('force_update_min_version'),
                'optional_update_version'       => input('optional_update_version'),
                'force_update_blocked_versions' => implode(',', array_filter(array_map('trim', explode(',', input('force_update_blocked_versions'))))),
            ];
            foreach (['latest_version', 'force_update_min_version', 'optional_update_version'] as $key) {
                if (!$valid($values[$key])) {
                    $errors[$key] = 'Use a version like 3.0.2 (numbers and dots only).';
                }
            }
            foreach (explode(',', $values['force_update_blocked_versions']) as $v) {
                if ($v !== '' && !preg_match('/^\d+(\.\d+){0,2}(\+\d+)?$/', $v)) {
                    $errors['force_update_blocked_versions'] = 'Blocked versions must be comma-separated, e.g. 3.0.0,3.0.1+31';
                }
            }
            if ($errors === []) {
                foreach ($values as $key => $value) {
                    Repository::setSetting($key, $value);
                }
                AdminRepo::log('versions-save', 'latest=' . $values['latest_version'] . ', min=' . $values['force_update_min_version']);
                flash('success', 'Saved. The version API uses the new rules immediately.');
                redirect('/admin/versions');
            }
            flash('error', 'Not saved. Please fix the highlighted fields.');
        }
        $s = Repository::allSettings();
        $values = [
            'latest_version'                => $s['latest_version'] ?? (string) config('app_version'),
            'force_update_enabled'          => $s['force_update_enabled'] ?? '1',
            'force_update_min_version'      => $s['force_update_min_version'] ?? '0.0.0',
            'optional_update_version'       => $s['optional_update_version'] ?? '0.0.0',
            'force_update_blocked_versions' => $s['force_update_blocked_versions'] ?? '',
        ];
        if (is_post()) {
            foreach (array_keys($values) as $k) {
                $values[$k] = $k === 'force_update_enabled' ? (isset($_POST[$k]) ? '1' : '0') : input($k);
            }
        }
        self::page('versions', 'App Versions', 'versions', ['values' => $values, 'errors' => $errors]);
    }

    /* ================================================================ site settings */

    private static function settings(): void
    {
        $errors = [];
        $s = Repository::allSettings();
        $values = [
            'site_support_email' => $s['site_support_email'] ?? '',
            'site_android_url'   => $s['site_android_url'] ?? '',
            'site_ios_url'       => $s['site_ios_url'] ?? '',
            'site_tagline'       => $s['site_tagline'] ?? '',
            'announce_enabled'   => $s['announce_enabled'] ?? '0',
            'announce_text'      => $s['announce_text'] ?? '',
            'announce_link'      => $s['announce_link'] ?? '',
        ];
        if (is_post()) {
            csrf_verify();
            foreach (array_keys($values) as $k) {
                $values[$k] = $k === 'announce_enabled' ? (isset($_POST[$k]) ? '1' : '0') : mb_substr(input($k), 0, $k === 'announce_text' ? 200 : 300);
            }
            if ($values['site_support_email'] !== '' && !filter_var($values['site_support_email'], FILTER_VALIDATE_EMAIL)) {
                $errors['site_support_email'] = 'Enter a valid email address.';
            }
            foreach (['site_android_url', 'site_ios_url'] as $k) {
                if ($values[$k] !== '' && !preg_match('#^https://#', $values[$k])) {
                    $errors[$k] = 'Use a full https:// link.';
                }
            }
            if ($values['announce_link'] !== '' && !preg_match('#^(https://|/)#', $values['announce_link'])) {
                $errors['announce_link'] = 'Use a full https:// link or a site path such as /app/ai.';
            }
            if ($values['announce_enabled'] === '1' && $values['announce_text'] === '') {
                $errors['announce_text'] = 'Write the announcement text, or switch the banner off.';
            }
            if ($errors === []) {
                foreach ($values as $k => $v) {
                    Repository::setSetting($k, $v);
                }
                AdminRepo::log('site-settings', 'banner=' . $values['announce_enabled']);
                flash('success', 'Site settings saved.');
                redirect('/admin/settings');
            }
            flash('error', 'Not saved. Please fix the highlighted fields.');
        }
        self::page('settings', 'Site Settings', 'settings', ['values' => $values, 'errors' => $errors, 'defaults' => [
            'site_support_email' => config('support_email'), 'site_android_url' => config('android_url'),
            'site_ios_url' => config('ios_url'), 'site_tagline' => config('tagline'),
        ]]);
    }

    /* ================================================================ admins */

    private static function users(): void
    {
        $errors = [];
        $username = '';
        if (is_post()) {
            csrf_verify();
            $action = input('action');
            if ($action === 'add') {
                $username = input('username');
                $errors = self::validateNewAdmin($username, (string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));
                if ($errors === []) {
                    Repository::saveAdmin($username, password_hash((string) $_POST['password'], PASSWORD_DEFAULT));
                    AdminRepo::log('admin-add', $username);
                    flash('success', 'Admin "' . $username . '" added.');
                    redirect('/admin/users');
                }
                flash('error', 'Not added. Please fix the highlighted fields.');
            } elseif ($action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                $target = AdminRepo::adminById($id);
                if ($target === null) {
                    flash('error', 'That admin no longer exists.');
                } elseif ($id === Auth::id()) {
                    flash('error', 'You cannot remove your own account.');
                } elseif (Repository::adminCount() <= 1) {
                    flash('error', 'At least one admin must remain.');
                } else {
                    AdminRepo::deleteAdmin($id);
                    AdminRepo::log('admin-delete', (string) $target['username']);
                    flash('success', 'Admin "' . $target['username'] . '" removed.');
                }
                redirect('/admin/users');
            }
        }
        self::page('users', 'Admins', 'users', ['admins' => AdminRepo::admins(), 'errors' => $errors, 'username' => $username, 'me' => Auth::id()]);
    }

    private static function account(): void
    {
        $errors = [];
        if (is_post()) {
            csrf_verify();
            $me = AdminRepo::adminById(Auth::id());
            $current = (string) ($_POST['current'] ?? '');
            $new = (string) ($_POST['password'] ?? '');
            if ($me === null || !password_verify($current, (string) $me['password_hash'])) {
                $errors['current'] = 'Your current password is incorrect.';
            }
            if (strlen($new) < 10) {
                $errors['password'] = 'Password must be at least 10 characters.';
            } elseif (!hash_equals($new, (string) ($_POST['password_confirm'] ?? ''))) {
                $errors['password_confirm'] = 'Passwords do not match.';
            }
            if ($errors === []) {
                AdminRepo::setPassword(Auth::id(), password_hash($new, PASSWORD_DEFAULT));
                session_regenerate_id(true);
                AdminRepo::log('password-change');
                flash('success', 'Password changed.');
                redirect('/admin/account');
            }
            flash('error', 'Password not changed. Please fix the highlighted fields.');
        }
        self::page('account', 'My Account', 'account', ['errors' => $errors]);
    }

    /* ================================================================ activity */

    private static function activity(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $list = AdminRepo::activity($page, 30);
        self::page('activity', 'Activity Log', 'activity', [
            'items' => $list['items'], 'page' => $page, 'pages' => max(1, (int) ceil($list['total'] / 30)), 'total' => $list['total'],
        ]);
    }

    /* ================================================================ view helpers */

    public static function bytes(int $n): string
    {
        return $n >= 1048576 ? round($n / 1048576, 1) . ' MB' : ($n >= 1024 ? round($n / 1024) . ' KB' : $n . ' B');
    }

    /**
     * Inline SVG bar chart. $series: list of ['values' => [day => n], 'class' => css class];
     * several series are stacked.
     */
    public static function chart(array $series, int $height = 200, int $w = 760): string
    {
        $keys = array_keys($series[0]['values']);
        $n = count($keys);
        $totals = array_fill(0, $n, 0);
        foreach ($series as $s) {
            foreach (array_values($s['values']) as $i => $v) {
                $totals[$i] += $v;
            }
        }
        $max = max(1, ...$totals);
        $nice = (int) (10 ** floor(log10($max)));
        $max = (int) (ceil($max / $nice) * $nice);
        $padL = 38;
        $padB = 24;
        $plotH = $height - $padB - 8;
        $bw = ($w - $padL) / $n;
        $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $height . '" role="img" aria-label="Bar chart">';
        for ($g = 0; $g <= 4; $g++) {
            $y = 8 + $plotH - $plotH * $g / 4;
            $svg .= '<line class="grid" x1="' . $padL . '" x2="' . $w . '" y1="' . $y . '" y2="' . $y . '"/>'
                . '<text class="axis" x="' . ($padL - 8) . '" y="' . ($y + 4) . '" text-anchor="end">' . (int) round($max * $g / 4) . '</text>';
        }
        $base = array_fill(0, $n, 0);
        foreach ($series as $s) {
            foreach (array_values($s['values']) as $i => $v) {
                if ($v <= 0) {
                    continue;
                }
                $h = $plotH * $v / $max;
                $y = 8 + $plotH - $plotH * $base[$i] / $max - $h;
                $svg .= '<rect class="' . e($s['class']) . '" x="' . round($padL + $i * $bw + $bw * 0.18, 1) . '" y="' . round($y, 1) . '" width="' . round($bw * 0.64, 1) . '" height="' . round($h, 1) . '" rx="3"><title>' . e($keys[$i]) . ': ' . $v . '</title></rect>';
                $base[$i] += $v;
            }
        }
        $step = max(1, (int) ceil($n / 10));
        foreach ($keys as $i => $k) {
            if ($i % $step === 0 || $i === $n - 1) {
                $svg .= '<text class="axis" x="' . round($padL + $i * $bw + $bw / 2, 1) . '" y="' . ($height - 6) . '" text-anchor="middle">' . e(date('M j', strtotime((string) $k))) . '</text>';
            }
        }
        return $svg . '</svg>';
    }

    public static function pager(int $page, int $pages, array $query = []): string
    {
        if ($pages <= 1) {
            return '';
        }
        $link = static fn (int $p): string => '?' . http_build_query(array_filter($query + ['page' => $p > 1 ? $p : null]));
        $html = '<nav class="ad-pager" aria-label="Pages">';
        $html .= $page > 1 ? '<a href="' . e($link($page - 1)) . '">← Newer</a>' : '<span></span>';
        $html .= '<span>Page ' . $page . ' of ' . $pages . '</span>';
        $html .= $page < $pages ? '<a href="' . e($link($page + 1)) . '">Older →</a>' : '<span></span>';
        return $html . '</nav>';
    }
}
