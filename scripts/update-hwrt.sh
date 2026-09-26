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
RUNTIME_CURRENT="$RUNTIME_MAP/current"

mkdir -p "$BACKUP"

echo "HWRT update: $(cat VERSION 2>/dev/null || echo unknown)"
echo "Application: $APP"

if [[ -f "$APP/database/database.sqlite" ]]; then
  cp -p "$APP/database/database.sqlite" "$BACKUP/database-$STAMP.sqlite"
  echo "SQLite backup: $BACKUP/database-$STAMP.sqlite"
else
  echo "Database is not SQLite; confirm your normal cPanel/MySQL backup exists before major upgrades."
fi

# Runtime map versions are intentionally outside Git. `current` is the atomic package pointer.
mkdir -p "$RUNTIME_MAP/versions"
if [[ ! -f "$RUNTIME_CURRENT/manifest.json" ]]; then
  SEED="$RUNTIME_MAP/versions/seed-$STAMP"
  mkdir -p "$SEED"
  if [[ -f "$RUNTIME_MAP/manifest.json" ]]; then
    find "$RUNTIME_MAP" -mindepth 1 -maxdepth 1 ! -name versions ! -name current -exec cp -a {} "$SEED/" \;
    echo "Migrated the existing runtime map into a versioned package."
  else
    cp -a "$APP/sitemap/." "$SEED/"
    echo "Seeded runtime map from Git package."
  fi
  LINK_TEMP="$RUNTIME_MAP/.current-$STAMP"
  ln -s "versions/seed-$STAMP" "$LINK_TEMP"
  mv -Tf "$LINK_TEMP" "$RUNTIME_CURRENT"
fi
if [[ -L "$APP/public/sitemap" ]]; then
  ln -sfn ../storage/app/hwrt-sitemap/current "$APP/public/sitemap"
elif [[ ! -e "$APP/public/sitemap" ]]; then
  ln -s ../storage/app/hwrt-sitemap/current "$APP/public/sitemap"
else
  echo "public/sitemap exists as a real path; convert it to the runtime-map symlink after review." >&2
  exit 2
fi

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
"$PHP" artisan hwrt:bootstrap-admin
"$PHP" artisan sitemap:import --path="$RUNTIME_CURRENT"
"$PHP" artisan optimize
chmod -R u+rwX,g+rwX storage bootstrap/cache

echo "HWRT updated to v$("$PHP" artisan tinker --execute="echo config('hwrt.version');" 2>/dev/null | tail -1 || cat VERSION)"
echo "Run: $PHP artisan test"
echo "Check: /board, /map, /pending, /reports, /settings and /error"
