#!/usr/bin/env bash
# Build a cPanel-ready zip of the website.
#
#   bin/build-deploy.sh
#
# Output: dist/floorplandesigner-deploy.zip, laid out for a cPanel home folder:
#
#   public_html/        <- contents of public/ (incl. .htaccess and .well-known/)
#   src/ views/ config/ bin/ data/   <- private app folders, next to public_html
#
# Upload the zip to your HOME folder in cPanel File Manager (the folder that
# contains public_html, not public_html itself) and choose Extract.
#
# A fresh one-time setup token is written to config/setup_token.txt inside the
# zip, and the admin setup URL is printed below.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
STAGE="$DIST/stage"
ZIP="$DIST/floorplandesigner-deploy.zip"
SITE_URL="${SITE_URL:-https://floorplandesigner.app}"

rm -rf "$STAGE" "$ZIP"
mkdir -p "$STAGE/public_html" "$STAGE/data"

# Public web root (cp -R with trailing /. copies dotfiles too)
cp -R "$ROOT/public/." "$STAGE/public_html/"

# Private folders
for dir in src views config bin; do
    cp -R "$ROOT/$dir" "$STAGE/$dir"
done
touch "$STAGE/data/.gitkeep"

# Never ship a local dev config or a dev database
rm -f "$STAGE/config/local.php" "$STAGE/data/"*.sqlite*

# One-time admin setup token (64 hex chars)
TOKEN="$(openssl rand -hex 32)"
printf '%s\n' "$TOKEN" > "$STAGE/config/setup_token.txt"
printf '%s\n' "$SITE_URL/admin/setup?token=$TOKEN" > "$DIST/ADMIN_SETUP_URL.txt"

# Strip macOS metadata
find "$STAGE" \( -name '.DS_Store' -o -name '._*' \) -delete

(cd "$STAGE" && zip -qr -X "$ZIP" .)
rm -rf "$STAGE"

echo "Built: $ZIP ($(du -h "$ZIP" | cut -f1))"
echo "After extracting on the server, create the first admin at the URL in:"
echo "  $DIST/ADMIN_SETUP_URL.txt"
