<?php
declare(strict_types=1);

/**
 * Front controller for the public website.
 * Apache: all non-file requests are rewritten here (see .htaccess).
 */
// PHP built-in dev server: let it serve real files (assets, api/*.php, admin/*.php) itself.
if (PHP_SAPI === 'cli-server') {
    // Decode once, like Apache does: Flutter web asset folders are literally named
    // "onBoarding%20screen" and the browser requests them as "onBoarding%2520screen".
    $file = realpath(__DIR__ . rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)));
    $inside = $file !== false && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR);
    if ($inside && (is_file($file) || is_file($file . '/index.html'))) {
        return false;
    }
}

require dirname(__DIR__) . '/src/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = rtrim((string) config('base_path', ''), '/');
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base)) ?: '/';
}
$path = '/' . trim($path, '/');
if ($path !== '/') {
    $path = rtrim($path, '/');
}

// Admin panel.
if ($path === '/admin' || str_starts_with($path, '/admin/')) {
    AdminApp::dispatch(substr($path, 6));
    exit;
}

// Count successful page views for Admin → Analytics (no visitor data is stored).
register_shutdown_function(static function () use ($path): void {
    if (http_response_code() === 200 && $path !== '/sitemap.xml') {
        Analytics::hit($path);
    }
});

// Web app (PHP pages + JavaScript editor) lives under /app.
if ($path === '/app' || str_starts_with($path, '/app/')) {
    WebApp::dispatch(substr($path, 4));
    exit;
}

switch ($path) {
    case '/':
        render('pages/home', [
            'title'       => config('app_name') . ' — Floor Plans in Your Browser & on Your Phone',
            'description' => 'Draw walls, add doors and furniture, preview in 3D and export 300 DPI PDFs — in your browser or with the Android and iOS apps.',
            'canonical'   => '/',
            'page'        => 'home',
        ]);
        break;

    case '/features':
        render('pages/features', [
            'title'       => 'Features — ' . config('app_name'),
            'description' => 'Editor tools, furniture library, 3D preview, measuring, construction calculator, multi-floor layers and export.',
            'canonical'   => '/features',
            'page'        => 'features',
        ]);
        break;

    case '/download':
        render('pages/download', [
            'title'       => 'Download — ' . config('app_name'),
            'description' => 'Get Floor Plan Designer for Android and iOS.',
            'canonical'   => '/download',
            'page'        => 'download',
        ]);
        break;

    case '/support':
        render('pages/support', [
            'title'       => 'Support & FAQ — ' . config('app_name'),
            'description' => 'Answers to common questions about Floor Plan Designer.',
            'canonical'   => '/support',
            'page'        => 'support',
        ]);
        break;

    case '/contact':
        handle_contact_page();
        break;

    case '/privacy-policy':
        render('pages/privacy', [
            'title'       => 'Privacy Policy — ' . config('app_name'),
            'description' => 'How Floor Plan Designer handles your data.',
            'canonical'   => '/privacy-policy',
            'page'        => 'privacy',
        ]);
        break;

    case '/terms-of-use':
        render('pages/terms', [
            'title'       => 'Terms of Use — ' . config('app_name'),
            'description' => 'Terms of use for Floor Plan Designer.',
            'canonical'   => '/terms-of-use',
            'page'        => 'terms',
        ]);
        break;

    case '/premium':
        render('pages/premium', [
            'title'       => 'Premium Plans — ' . config('app_name'),
            'description' => 'Floor Plan Premium: no ads, unlimited plans, all export formats and full AI tools. Weekly, monthly and yearly plans.',
            'canonical'   => '/premium',
            'page'        => 'premium',
        ]);
        break;

    case '/pricing':
        header('Location: ' . url('/premium'), true, 301);
        exit;

    case '/sitemap.xml':
        output_sitemap();
        break;

    default:
        abort(404, 'The page you are looking for does not exist.');
}

/** XML sitemap of the public pages (referenced from robots.txt). */
function output_sitemap(): void
{
    $base = rtrim((string) config('site_url'), '/');
    $pages = [
        '/'               => ['weekly', '1.0'],
        '/app'            => ['weekly', '0.9'],
        '/app/editor'     => ['monthly', '0.8'],
        '/app/templates'  => ['monthly', '0.7'],
        '/app/calculators' => ['monthly', '0.7'],
        '/features'       => ['monthly', '0.8'],
        '/download'       => ['monthly', '0.8'],
        '/premium'        => ['monthly', '0.7'],
        '/support'        => ['monthly', '0.7'],
        '/contact'        => ['yearly', '0.5'],
        '/privacy-policy' => ['yearly', '0.3'],
        '/terms-of-use'   => ['yearly', '0.3'],
    ];

    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
    foreach ($pages as $path => [$freq, $priority]) {
        $loc = $base . url($path);
        echo "  <url><loc>", e($loc), "</loc><changefreq>$freq</changefreq><priority>$priority</priority></url>\n";
    }
    echo "</urlset>\n";
}

/** GET shows the form, POST validates and stores the message (PRG pattern). */
function handle_contact_page(): void
{
    $errors = [];
    $old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
    $subject = (string) ($_GET['subject'] ?? '');

    if (is_post()) {
        csrf_verify();
        $result = ContactService::submit($_POST);
        if ($result['ok']) {
            flash('success', $result['message']);
            redirect('/contact');
        }
        $errors = $result['errors'] ?? [];
        flash('error', $result['message']);
        $old = [
            'name'    => input('name'),
            'email'   => input('email'),
            'subject' => input('subject'),
            'message' => input('message'),
        ];
    } elseif (in_array($subject, ['Support', 'Bug report', 'Feature request', 'Business enquiry', 'Other'], true)) {
        $old['subject'] = $subject;
    }

    render('pages/contact', [
        'title'       => 'Contact — ' . config('app_name'),
        'description' => 'Get in touch with the Floor Plan Designer team.',
        'canonical'   => '/contact',
        'page'        => 'contact',
        'errors'      => $errors,
        'old'         => $old,
    ]);
}
