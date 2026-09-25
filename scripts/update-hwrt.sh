#!/usr/bin/env bash
# Safe HWRT Git update helper for cPanel/SSH.
# Usage:
#   ./scripts/update-hwrt.sh              # update current branch from origin
#   ./scripts/update-hwrt.sh v2.0.1       # checkout/update a release tag or branch
set -euo pipefail

APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP"

PHP="${PHP_BIN:-$(command -v php)}"
COMPOSER="${COMPOSER_BIN:-$(command -v composer)}"
TARGET="${1:-}"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$APP/storage/app/backups"
RUNTIME_MAP="$APP/storage/app/hwrt-sitemap"

mkdir -p "$BACKUP"

echo "HWRT update: $(cat VERSION 2>/dev/null || echo unknown)"
echo "Application: $APP"

if [[ -f "$APP/database/database.sqlite" ]]; then
  cp -p "$APP/database/database.sqlite" "$BACKUP/database-$STAMP.sqlite"
  echo "SQLite backup: $BACKUP/database-$STAMP.sqlite"
else
  echo "Database is not SQLite; confirm your normal cPanel/MySQL backup exists before major upgrades."
fi

# Runtime map is intentionally outside Git so automatic refreshes never dirty the repository.
if [[ ! -f "$RUNTIME_MAP/manifest.json" ]]; then
  mkdir -p "$RUNTIME_MAP"
  cp -a "$APP/sitemap/." "$RUNTIME_MAP/"
  echo "Seeded runtime map from Git package."
fi
ln -sfn ../storage/app/hwrt-sitemap "$APP/public/sitemap"

"$PHP" artisan down --retry=60 || true
up() { "$PHP" artisan up >/dev/null 2>&1 || true; }
trap up EXIT

git fetch --tags origin
if [[ -n "$TARGET" ]]; then
  git checkout "$TARGET"
else
  BRANCH="$(git branch --show-current)"
  if [[ -z "$BRANCH" ]]; then
    echo "Detached HEAD: supply a target tag/branch explicitly." >&2
    exit 2
  fi
  git pull --ff-only origin "$BRANCH"
fi

"$COMPOSER" install --no-dev --optimize-autoloader --no-interaction
"$PHP" artisan migrate --force
"$PHP" artisan sitemap:import --path="$RUNTIME_MAP"
"$PHP" artisan optimize
chmod -R u+rwX,g+rwX storage bootstrap/cache

echo "HWRT updated to v$("$PHP" artisan tinker --execute="echo config('hwrt.version');" 2>/dev/null | tail -1 || cat VERSION)"
echo "Run: $PHP artisan test"
echo "Check: /board, /map, /pending, /reports, /settings and /error"
