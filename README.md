# Floor Plan Designer — PHP website

A plain-PHP (no framework, no Composer) website and backend for the Floor Plan Designer app.

- **Public site:** Home, Features, Download, Support/FAQ (live search), Contact, Privacy Policy, Terms of Use. Its URLs match the current site (`/support`, `/contact`, `/privacy-policy`, `/terms-of-use`).
- **Backend:** stores contact messages in SQLite, applies rate limiting and a honeypot to block spam, and uses CSRF protection, prepared statements and security headers.
- **JSON API:** `/api/v1/version.php` (version gate) and `/api/v1/contact.php` (contact form).
- **Admin panel:** `/admin/` lets you read, mark and delete messages, and manage the latest version and the force/optional update rules.

## Requirements

- PHP **8.1+** with the `pdo_sqlite` and `mbstring` extensions
- Apache with `mod_rewrite` (or Nginx; see below)

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

1. **Back up `public_html/` first.** It contains `.well-known/assetlinks.json`, which isn't in this repo; a copy is included in `public/.well-known/`.
2. Upload everything **except** `public/` to your home folder, so `src/`, `views/`, `config/`, `data/` and `bin/` sit **next to** `public_html/` and not inside it.
3. Replace the contents of `public_html/` with the contents of `public/`, including the hidden `.htaccess` and `.well-known/`. The scripts look for `src/` one level above the web root, so this layout works without code changes.
4. Make `data/` writable by PHP (`chmod 750 data`, or 770 if PHP runs as a different user).
5. In cPanel → *Select PHP Version*, choose PHP 8.1 or newer with `pdo_sqlite` and `mbstring` enabled.
6. Over SSH, run `php bin/create-admin.php <username>` from your home folder. If you don't have SSH, upload `bin/create-admin.php`, run it once with the cPanel Terminal, then delete it.
7. Check these URLs after the switch:
   - `https://floorplandesigner.app/app-ads.txt` shows the AdMob line. **AdMob needs this file.**
   - `https://floorplandesigner.app/.well-known/assetlinks.json` returns JSON with a 200 status and no redirect, on both the apex domain and `www.`. **Android App Links need this.**
   - `/privacy-policy` and `/terms-of-use` load. The app and the store listings link to them.
   - `/sitemap.xml` returns XML. Submit it in Google Search Console.

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
