#!/usr/bin/env bash
#
# Deploy the latest commit to Hostinger shared hosting.
# Usage (on the server):  cd ~/domains/rpxmenujufreedom.my.id/finance && ./deploy.sh
#
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/domains/rpxmenujufreedom.my.id/finance}"
WEB_DIR="${WEB_DIR:-$HOME/domains/rpxmenujufreedom.my.id/public_html}"
PHP_BIN="${PHP_BIN:-/opt/alt/php83/usr/bin/php}"

cd "$APP_DIR"

echo "==> Pulling latest"
git pull --ff-only

# Dependencies change only when composer.json / composer.lock change.
if command -v composer >/dev/null 2>&1; then
    echo "==> Composer install"
    composer install --no-dev --optimize-autoloader --no-interaction
else
    echo "==> composer not found — keeping the existing vendor/"
fi

echo "==> Migrating"
"$PHP_BIN" artisan migrate --force

echo "==> Clearing caches"
"$PHP_BIN" artisan optimize:clear

echo "==> Rebuilding caches"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

# The web root lives outside the repo, so compiled assets are copied across.
echo "==> Publishing assets to public_html/build"
mkdir -p "$WEB_DIR/build"
rm -rf "$WEB_DIR"/build/*
cp -R "$APP_DIR/public/build/." "$WEB_DIR/build/"

echo "==> Done: $(git log -1 --pretty='%h %s')"
