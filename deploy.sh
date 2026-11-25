#!/usr/bin/env bash
#
# Deploy the latest commit to Hostinger shared hosting.
#
#   ./deploy.sh              pull, then migrate / cache / publish assets
#   ./deploy.sh --no-pull    run the steps only (used by the post-merge hook)
#
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/domains/rpxmenujufreedom.my.id/finance}"
WEB_DIR="${WEB_DIR:-$HOME/domains/rpxmenujufreedom.my.id/public_html}"
PHP_BIN="${PHP_BIN:-/opt/alt/php83/usr/bin/php}"

cd "$APP_DIR"

# Versioned hooks: makes a plain `git pull origin main` deploy by itself.
git config core.hooksPath .githooks

run_steps() {
    if command -v composer >/dev/null 2>&1; then
        echo "==> Composer install"
        composer install --no-dev --optimize-autoloader --no-interaction \
            || echo "    composer failed — keeping the existing vendor/"
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
}

if [ "${1:-}" != "--no-pull" ]; then
    before="$(git rev-parse HEAD)"
    echo "==> Pulling latest"
    git pull --ff-only
    after="$(git rev-parse HEAD)"

    if [ "$before" = "$after" ]; then
        echo "==> Already at $(git log -1 --pretty=%h); running the steps anyway"
        run_steps
    else
        # post-merge already executed run_steps for this update.
        echo "==> Updated to $(git log -1 --pretty='%h %s')"
    fi

    exit 0
fi

run_steps

echo "==> Done: $(git log -1 --pretty='%h %s')"
