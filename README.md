# Floor Plan Designer — PHP website

A plain-PHP (no framework, no Composer) website and backend for the Floor Plan Designer app.

- **Web app (`/app`):** written in PHP, HTML, CSS and JavaScript. PHP renders the pages and runs the construction calculators; a JavaScript canvas editor draws plans in the browser. See [Web app](#web-app-app) below.
- **Classic web app (`/classic/`):** the Flutter app compiled for browsers, kept as a fallback. Every feature now also exists in the PHP web app.
- **Public site:** Home, Features, Download, Support/FAQ (live search), Contact, Privacy Policy, Terms of Use. Its URLs match the current site (`/support`, `/contact`, `/privacy-policy`, `/terms-of-use`).
- **Backend:** stores contact messages in SQLite, applies rate limiting and a honeypot to block spam, and uses CSRF protection, prepared statements and security headers.
- **JSON API:** `/api/v1/version.php` (version gate), `/api/v1/contact.php` (contact form), `/api/v1/generate-plan.php` and `/api/v1/analyze-plan.php` (AI tools), and `/api/v1/ai.php` (AI proxy for the classic app).
- **Admin panel:** `/admin/` lets you read, mark and delete messages, and manage the latest version and the force/optional update rules.

## Requirements

- PHP **8.1+** with the `pdo_sqlite`, `mbstring` and `curl` extensions
- Apache with `mod_rewrite` (or Nginx; see below)

## Web app (`/app`)

The web app is plain PHP plus vanilla JavaScript; nothing to build.

| URL | What it is | Where the code is |
|---|---|---|
| `/app` | Dashboard: recent plans, tools, templates | `views/app/dashboard.php` |
| `/app/editor` | 2D editor: walls, rooms, doors, windows, 418 library symbols, labels, measurements, undo/redo, PNG/PDF/JSON export | `views/app/editor.php`, `public/assets/app/editor.js` |
| `/app/plans` | Saved plans: open, rename, duplicate, download, import, delete | `views/app/plans.php`, `public/assets/app/app.js` |
| `/app/templates` | Plan templates with a lightbox viewer | `views/app/templates.php` |
| `/app/3d?plan=<id>` | 3D view (three.js, self-hosted in `public/assets/app/vendor/three/`): orbit and walk modes, low/cut-away/full walls, wall height and colour, floor finishes, doors open/closed, realistic 3D furniture, PNG snapshot | `views/app/viewer3d.php`, `public/assets/app/viewer3d.js`, `public/assets/app/furniture3d.js` |
| `/app/ai` | AI floor plan generator: description → one plan per floor | `src/AiPlanGenerator.php`, `views/app/ai_generator.php`, `public/assets/app/ai.js` |
| `/app/ai/analyzer` | AI analyzer: photo of a plan → rooms, sizes and an editable plan | `src/PlanAnalyzer.php`, `views/app/ai_analyzer.php`, `public/assets/app/ai.js` |
| `/app/calculators` | Construction, metal, area and unit calculators, computed by PHP | `src/Calculators.php`, `views/app/calculators/` |
| `/app/notes` | Notes | `views/app/notes.php` |
| `/app/about` | About, privacy and photo credits (units, grid, snapping and wall thickness are set in the editor's Display panel) | `views/app/about.php` |

Routing: `public/index.php` sends every `/app…` URL to `WebApp::dispatch()` (`src/WebApp.php`).

Plans and notes are saved in the browser's local storage (`fpd_plans`, `fpd_plan_<id>`, `floor_plan_notes`), so clearing browser data deletes them. Users can download a plan as `.json` and import it again.

Editor shortcuts: V select, W wall, R room, D door, N window, F library, T label, M measure, H pan, Shift for straight lines, Del delete, Ctrl+Z / Ctrl+Y undo/redo, Ctrl+S save, `[` `]` rotate an item, Space-drag or right-drag to pan, scroll or pinch to zoom.

## Classic web app (`/classic/`)

`public/classic/` holds the release web build of the Flutter app, kept as a fallback. Don't edit it by hand. Rebuild it from the Flutter project:

```bash
tool/build_web.sh      # run in the Flutter app repo; writes php-website/public/classic/
```

The script runs `flutter build web --base-href /classic/`, refuses to continue if any API key appears in the output, leaves out the local CanvasKit copy (browsers load it from Google's CDN), and writes an `.htaccess` that tells browsers to check for a new version on every load.

How the Flutter web build differs from the mobile apps (all changes are guarded by `kIsWeb`, so the mobile apps behave exactly as before):

| Area | Web behavior |
|---|---|
| Saved plans | Stored in the browser's local storage. Clearing browser data deletes them. |
| Export, Share and 3D snapshot | The file downloads to the browser's Downloads folder. |
| AI generator and analyzer | Sent to `/api/v1/ai.php`, which adds the Groq key on the server. The key never reaches the browser. |
| Pro, purchases and ads | There are no ads or purchases on the web. Pro buttons explain this and link to the apps. |
| Bubble level | Works on phones and tablets (iOS asks for motion permission). On computers it explains that no sensor is available. |
| Compass | Browsers don't provide magnetometer data, so it points users to the mobile app. |

### AI endpoints

`POST /api/v1/generate-plan.php` (`{"description": "…"}`) and `POST /api/v1/analyze-plan.php` (`{"image": "data:image/jpeg;base64,…"}`) run the AI tools on the server with `src/GroqClient.php`. Both reject cross-origin requests, count every upstream call against `ai_rate_limit`, and return `{"ok": false, "error": "…"}` on failure. Without a key, the generator falls back to the built-in layout engine; the analyzer returns 503.

### AI proxy (classic app)

`POST /api/v1/ai.php` accepts the same JSON as Groq's chat-completions API, without the `Authorization` header. It:

- rejects cross-origin requests;
- only allows models listed in `ai_models`;
- caps `max_tokens` (default 8,000) and the request size (default 8 MB, enough for the analyzer's photo);
- limits each IP to 30 requests per hour (`ai_rate_limit`).

Set the key either with the `GROQ_API_KEY` GitHub secret, which the deploy workflow writes to `config/secrets.php`, or by creating that file yourself:

```php
<?php return ['groq_api_key' => 'gsk_…'];
```

Without a key, the generator falls back to its built-in layouts and the analyzer shows "AI features are not configured on this server yet".

## Quick start (local)

```bash
cd php-website
php bin/create-admin.php admin        # prompts for a password (min 10 chars)
php -S localhost:8000 -t public public/index.php
```

Open http://localhost:8000 for the site and http://localhost:8000/admin/login.php for the admin panel.

The SQLite database is created automatically at `data/floorplan.sqlite` the first time a page is loaded.

## Deploying

1. Upload the whole `php-website/` folder.
2. Point the domain's **document root at `php-website/public`**. `config/`, `src/`, `views/` and `data/` must stay outside the web root.
3. Give the web server user write access to `data/`.
4. Copy `config/local.example.php` to `config/local.php` and set your values (iOS App Store link, support email, `base_path` if the site lives in a sub-folder).
5. Create an admin account: `php bin/create-admin.php <username>`.

### Switching floorplandesigner.app from the React site

The domain is hosted on Thundercloud: its nameservers are `ns0/ns1.thundercloud.uk` and the server sends an `nginx` header. If the account uses cPanel, the site files live in `public_html/`.

SSH is closed on this server, so deploy through cPanel's File Manager with the prebuilt zip:

1. **Build the zip** on your computer: run `bin/build-deploy.sh`. This creates `dist/floorplandesigner-deploy.zip`, plus `dist/ADMIN_SETUP_URL.txt` containing a one-time admin setup link.
2. **Back up the current site.** In cPanel → File Manager, open `public_html/`, select all (turn on *Show Hidden Files* in Settings), then use *Compress* to make a zip and download it. This backup includes the live `.well-known/assetlinks.json`; the new zip contains a copy too.
3. **Clear out the React build.** Delete everything inside `public_html/` except `.well-known/`.
4. **Upload and extract.** Go to your **home folder**, the one that *contains* `public_html`, not `public_html` itself. Upload `floorplandesigner-deploy.zip`, right-click it, choose *Extract*, and confirm overwriting. This fills `public_html/` and creates `src/`, `views/`, `config/`, `bin/` and `data/` next to it. Delete the zip afterwards.
5. **Choose the PHP version.** In cPanel → *Select PHP Version* (or *MultiPHP Manager*), choose PHP 8.1 or newer and make sure `pdo_sqlite` and `mbstring` are enabled.
6. **Create the admin account.** Open the link from `dist/ADMIN_SETUP_URL.txt`. The page only works while no admin exists and the token matches, and it disables itself once the first admin is created.
   - If you have SSH elsewhere, you can instead run `php bin/create-admin.php <username>` from the home folder.
7. **Check these URLs after the switch:**
   - `https://floorplandesigner.app/app-ads.txt` shows the AdMob line. **AdMob needs this file.**
   - `https://floorplandesigner.app/.well-known/assetlinks.json` returns JSON with a 200 status and no redirect, on both the apex domain and `www.`. **Android App Links need this.**
   - `/privacy-policy` and `/terms-of-use` load. The app and the store listings link to them.
   - `/sitemap.xml` returns XML. Submit it in Google Search Console.

### Automatic deploys (GitHub Actions)

`.github/workflows/deploy.yml` runs on every push to `main` and can also be started by hand from the *Actions* tab via *Run workflow*. It runs three stages:

1. **Check:** lints every PHP file and smoke-tests all pages and APIs with PHP's built-in server. If anything fails, nothing is deployed.
2. **Deploy:** uploads to the cPanel home folder over FTPS, using the same layout as the zip (`public_html/` plus the private folders). Only changed files are uploaded. The workflow never deletes or overwrites files it didn't upload itself, so `data/` (the database), `config/local.php`, mail and everything else on the account are left alone.
3. **Verify:** checks the live `app-ads.txt`, `assetlinks.json` (on both the apex domain and `www.`), `sitemap.xml`, the privacy policy and the version API.

**One-time setup:** in GitHub → *Settings → Secrets and variables → Actions*, add three repository secrets:

| Secret | Value |
|---|---|
| `FTP_SERVER` | `ftp.floorplandesigner.app` (or `cloud526.thundercloud.uk`) |
| `FTP_USERNAME` | your **main cPanel FTP user**, whose root is the home folder that contains `public_html`. Don't use an FTP account restricted to `public_html`. |
| `FTP_PASSWORD` | that user's password |
| `GROQ_API_KEY` | *(optional)* the Groq key for the web app's AI tools. It's written to `config/secrets.php`, outside the web root. |

If your FTP root isn't the home folder, also add a repository **variable** `FTP_SERVER_DIR` with the remote path of the home folder, for example `/home/username/`.

Before the first automatic deploy, back up `public_html/` and remove the React files (deployment steps 2 and 3 above), and set the PHP version (step 5).

**First admin without SSH:**

1. Generate a token on your computer with `openssl rand -hex 32`.
2. Add it as the secret `ADMIN_SETUP_TOKEN`, then deploy.
3. Open `https://floorplandesigner.app/admin/setup.php?token=<token>` and create your admin.
4. Delete the secret. The next deploy then removes the token file from the server.

You can skip this if you already created an admin with the zip's setup link.

**Troubleshooting**

- **"unable to open database file":** PHP can't write to `data/`. Set its permissions to 755 in File Manager, or 775 if PHP runs as a different user.
- **Too many redirects:** the host's proxy isn't passing an HTTPS signal. Comment out the second redirect block in `public_html/.htaccess` and enable *Force HTTPS Redirect* in cPanel → *Domains* instead.
- **The old React page still shows:** a leftover `index.html` is cached or still present. Delete it from `public_html/`.

`.htaccess` 301-redirects `http://` and `www.` to `https://floorplandesigner.app`. `/.well-known/` is excluded, because Android won't follow redirects when it verifies App Links. If the server runs Nginx only, without Apache behind it, `.htaccess` is ignored and the host must apply the Nginx rules below.

### Nginx

```nginx
root /var/www/php-website/public;
index index.php;
location ^~ /.well-known/ { try_files $uri =404; default_type application/json; }
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php-fpm.sock; }
```

## API

### `GET /api/v1/version.php?platform=android&version=3.0.2+32`

This follows the same rules as the app's `VersionGateService`. If the version gate is disabled or the version can't be parsed, the API returns `ok` (it fails open).

```json
{
  "status": "ok | optional_update | update_required | blocked",
  "installed_version": "3.0.2+32",
  "minimum_version": "3.0.0",
  "optional_version": "3.0.2",
  "latest_version": "3.0.2",
  "message": "",
  "store_url": "https://play.google.com/store/apps/details?id=com.buildsmart.homedesigns",
  "checked_at": "2026-11-13T10:00:00+00:00"
}
```

Set these rules in **Admin → Version & settings**.

> The Flutter app currently reads these rules from Firebase Remote Config. This endpoint is a self-hosted alternative. The app won't use it until `VersionGateService` is changed to call it.

### `POST /api/v1/contact.php`

Accepts JSON or form data with `name`, `email`, `subject` and `message`.

- `201` the message was saved
- `422` validation failed; the response includes an `errors` object with one entry per field
- `429` too many messages from this IP (the default limit is 5 per 15 minutes)

```bash
curl -X POST http://localhost:8000/api/v1/contact.php \
  -H 'Content-Type: application/json' \
  -d '{"name":"Ali","email":"ali@example.com","subject":"Support","message":"Export button does nothing on my phone."}'
```

## Structure

```
php-website/
├── bin/create-admin.php     CLI: create or reset an admin user
├── config/app.php           defaults (override in config/local.php)
├── data/                    SQLite database (git-ignored)
├── public/                  web root
│   ├── index.php            front controller for public pages
│   ├── api/v1/              JSON endpoints
│   ├── admin/               admin panel
│   └── assets/              css, js, images
├── src/                     bootstrap, helpers, Database, Repository, Auth, VersionGate, ContactService
└── views/                   layouts, public pages, admin pages
```

## Notes

- The Privacy Policy and Terms of Use are drafts based on the app's data-safety audit (`PLAY_DATA_SAFETY.txt`). Have them reviewed before you publish.
- The admin login adds a fixed delay after each failed attempt. For stronger brute-force protection, add IP lockout or restrict `/admin` by IP at the web server.
