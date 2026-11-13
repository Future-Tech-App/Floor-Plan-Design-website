<?php
declare(strict_types=1);

/**
 * Front controller for the public website.
 * Apache: all non-file requests are rewritten here (see .htaccess).
 */
// PHP built-in dev server: let it serve real files (assets, api/*.php, admin/*.php) itself.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
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

switch ($path) {
    case '/':
        render('pages/home', [
            'title'       => config('app_name') . ' — Floor Plan Designer for Mobile',
            'description' => 'Draw walls, add doors and furniture, preview in 3D and export 300 DPI PDFs. Floor Plan Designer runs fully on your phone.',
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
        '/features'       => ['monthly', '0.8'],
        '/download'       => ['monthly', '0.8'],
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
