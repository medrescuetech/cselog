#!/usr/bin/env bash
# Safe HWRT update helper for cPanel/SSH.
# Usage:
#   ./scripts/update-hwrt.sh
#   ./scripts/update-hwrt.sh --target v2.0.1
#   ./scripts/update-hwrt.sh --no-git
set -Eeuo pipefail
umask 077

APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP"

usage() {
  cat >&2 <<'EOF'
Usage: scripts/update-hwrt.sh [--no-git | --target REF]

With no options, fast-forward the current branch from origin.
--target REF deploys an exact remote branch or tag (tags are checked out detached).
--no-git runs the deployment steps against the already checked-out exact vN.N.N tag.
EOF
}

NO_GIT=0
TARGET=""
while (($#)); do
  case "$1" in
    --no-git)
      ((NO_GIT == 0)) || { usage; exit 2; }
      NO_GIT=1
      shift
      ;;
    --target)
      (($# >= 2)) || { usage; exit 2; }
      [[ -z "$TARGET" ]] || { usage; exit 2; }
      TARGET="$2"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    -*)
      usage
      exit 2
      ;;
    *)
      # Keep the original positional release-ref form for existing operators.
      [[ -z "$TARGET" ]] || { usage; exit 2; }
      TARGET="$1"
      shift
      ;;
  esac
done

if ((NO_GIT)) && [[ -n "$TARGET" ]]; then
  echo "--no-git cannot be combined with --target." >&2
  exit 2
fi

resolve_binary() {
  local configured="$1"
  local fallback="$2"
  local resolved

  if [[ -n "$configured" ]]; then
    if [[ "$configured" == */* ]]; then
      [[ -x "$configured" ]] || { echo "Configured executable is not executable: $configured" >&2; return 1; }
      printf '%s\n' "$configured"
    else
      command -v "$configured" || { echo "Executable not found: $configured" >&2; return 1; }
    fi
    return
  fi

  resolved="$(command -v "$fallback")" || {
    echo "Required executable not found: $fallback (set ${fallback^^}_BIN to its path)." >&2
    return 1
  }
  printf '%s\n' "$resolved"
}

PHP="$(resolve_binary "${PHP_BIN:-}" php)"
COMPOSER="$(resolve_binary "${COMPOSER_BIN:-}" composer)"
[[ -f artisan && -f composer.json && -f composer.lock ]] || {
  echo "Application files are incomplete; expected artisan, composer.json and composer.lock." >&2
  exit 2
}
"$PHP" -v >/dev/null
"$COMPOSER" --version >/dev/null
if ! "$PHP" -r 'exit(function_exists("proc_open") ? 0 : 1);' >/dev/null; then
  echo "PHP proc_open is required for secure MySQL/MariaDB dump and restore; refusing update." >&2
  exit 2
fi

git rev-parse --is-inside-work-tree >/dev/null 2>&1 || {
  echo "Application directory is not a Git worktree." >&2
  exit 2
}
if [[ -n "$(git status --porcelain --untracked-files=all)" ]]; then
  echo "Refusing update: Git worktree is not clean. Commit or safely back up changes first." >&2
  exit 2
fi

REMOTE_REF=""
REF_KIND=""
BRANCH=""
if ((NO_GIT)); then
  RELEASE_TAG="$(git describe --tags --exact-match HEAD 2>/dev/null || true)"
  [[ "$RELEASE_TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || {
    echo "--no-git requires HEAD to be checked out at an exact vN.N.N release tag." >&2
    exit 2
  }
elif [[ -n "$TARGET" ]]; then
  BRANCH_EXISTS=0
  TAG_EXISTS=0
  if git check-ref-format "refs/heads/$TARGET" && git ls-remote --exit-code --heads origin "refs/heads/$TARGET" >/dev/null 2>&1; then
    BRANCH_EXISTS=1
  fi
  if git check-ref-format "refs/tags/$TARGET" && git ls-remote --exit-code --tags origin "refs/tags/$TARGET" >/dev/null 2>&1; then
    TAG_EXISTS=1
  fi
  if ((BRANCH_EXISTS && TAG_EXISTS)); then
    echo "Target name is ambiguous (remote branch and tag both exist): $TARGET" >&2
    exit 2
  elif ((BRANCH_EXISTS)); then
    REF_KIND="branch"
    REMOTE_REF=""
  elif ((TAG_EXISTS)); then
    REF_KIND="tag"
    REMOTE_REF="refs/tags/$TARGET"
  else
    echo "Target must name an existing remote branch or tag: $TARGET" >&2
    exit 2
  fi
elif [[ -n "$(git symbolic-ref --quiet --short HEAD 2>/dev/null || true)" ]]; then
  BRANCH="$(git branch --show-current)"
  git ls-remote --exit-code --heads origin "refs/heads/$BRANCH" >/dev/null 2>&1 || {
    echo "Current branch '$BRANCH' has no matching branch on origin." >&2
    exit 2
  }
  REF_KIND="branch"
  REMOTE_REF="refs/remotes/origin/$BRANCH"
else
  echo "Detached HEAD: supply --target REF or check out an exact release tag and use --no-git." >&2
  exit 2
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_ROOT="$APP/storage/app/backups"
BACKUP="$BACKUP_ROOT/$STAMP"
RUNTIME_MAP="$APP/storage/app/hwrt-sitemap"
RUNTIME_CURRENT="$RUNTIME_MAP/current"
PUBLIC_MAP="$APP/public/sitemap"
EXPECTED_PUBLIC_TARGET="../storage/app/hwrt-sitemap/current"
OFFSITE_INPUT="${HWRT_OFFSITE_BACKUP_DIR:-}"

if [[ ! -L "$PUBLIC_MAP" || "$(readlink "$PUBLIC_MAP")" != "$EXPECTED_PUBLIC_TARGET" ]]; then
  echo "public/sitemap must be the tracked runtime-map symlink '$EXPECTED_PUBLIC_TARGET'; it will not be replaced." >&2
  exit 2
fi
if [[ -L "$RUNTIME_CURRENT" ]]; then
  [[ -f "$RUNTIME_CURRENT/manifest.json" ]] || {
    echo "Runtime map current link is invalid; refusing to update." >&2
    exit 2
  }
elif [[ -e "$RUNTIME_CURRENT" ]]; then
  echo "Runtime map current path is not a symlink; refusing to update." >&2
  exit 2
elif [[ ! -f "$RUNTIME_MAP/manifest.json" && ! -f "$APP/sitemap/manifest.json" ]]; then
  echo "No manifest found in the runtime or Git seed map package; refusing to update." >&2
  exit 2
fi
[[ -f "$APP/.env" ]] || {
  echo "Required .env file is missing; refusing update." >&2
  exit 2
}
if [[ -z "$OFFSITE_INPUT" || "$OFFSITE_INPUT" != /* || ! -d "$OFFSITE_INPUT" || ! -w "$OFFSITE_INPUT" ]]; then
  echo "Set HWRT_OFFSITE_BACKUP_DIR to an existing writable absolute off-host backup directory." >&2
  exit 2
fi
OFFSITE_BACKUP_DIR="$(cd "$OFFSITE_INPUT" && pwd -P)"
case "$OFFSITE_BACKUP_DIR/" in
  "$APP/"*)
    echo "Off-host backup destination must be outside the application directory." >&2
    exit 2
    ;;
esac

mkdir -p "$BACKUP_ROOT"
chmod 700 "$BACKUP_ROOT"
mkdir "$BACKUP"
chmod 700 "$BACKUP"
printf '%s\n' "$(git rev-parse HEAD)" > "$BACKUP/previous-release"
printf '%s\n' "$(git symbolic-ref --quiet --short HEAD 2>/dev/null || git describe --tags --exact-match HEAD 2>/dev/null || echo detached)" \
  > "$BACKUP/previous-ref"
chmod 600 "$BACKUP/previous-release" "$BACKUP/previous-ref"

cp -p "$APP/.env" "$BACKUP/dot-env"
chmod 600 "$BACKUP/dot-env"

MAINTENANCE_ATTEMPTED=0
finish() {
  local status=$?
  trap - EXIT
  if ((MAINTENANCE_ATTEMPTED)); then
    if "$PHP" artisan up; then
      MAINTENANCE_ATTEMPTED=0
    else
      echo "ERROR: failed to exit Laravel maintenance mode; run '$PHP artisan up' manually." >&2
      ((status != 0)) || status=1
    fi
  fi
  exit "$status"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

MAINTENANCE_ATTEMPTED=1
"$PHP" artisan down --retry=60
"$PHP" "$APP/scripts/backup-hwrt-database.php" "$BACKUP"

mkdir -p "$RUNTIME_MAP/versions"
if [[ ! -e "$RUNTIME_CURRENT" ]]; then
  SEED="$RUNTIME_MAP/versions/seed-$STAMP"
  mkdir "$SEED"
  if [[ -f "$RUNTIME_MAP/manifest.json" ]]; then
    find "$RUNTIME_MAP" -mindepth 1 -maxdepth 1 ! -name versions ! -name current \
      -exec cp -a {} "$SEED/" \;
  else
    cp -a "$APP/sitemap/." "$SEED/"
  fi
  [[ -f "$SEED/manifest.json" ]] || {
    echo "No valid manifest found in the runtime or Git seed map package." >&2
    exit 2
  }
  LINK_TEMP="$RUNTIME_MAP/.current-$STAMP"
  ln -s "versions/seed-$STAMP" "$LINK_TEMP"
  mv -Tf "$LINK_TEMP" "$RUNTIME_CURRENT"
fi
cp -a "$RUNTIME_MAP/." "$BACKUP/runtime-map"
chmod -R go-rwx "$BACKUP/runtime-map"
if [[ -d "$APP/storage/app/private" ]]; then
  tar -czf "$BACKUP/uploads.tar.gz" -C "$APP" storage/app/private
  chmod 600 "$BACKUP/uploads.tar.gz"
fi
chmod -R go-rwx "$BACKUP"
OFFSITE_COPY="$OFFSITE_BACKUP_DIR/$STAMP"
cp -a "$BACKUP" "$OFFSITE_COPY"
diff -qr "$BACKUP" "$OFFSITE_COPY" >/dev/null || {
  echo "Off-host backup copy verification failed; no Git ref was changed." >&2
  exit 1
}
"$PHP" artisan up
MAINTENANCE_ATTEMPTED=0

if ((!NO_GIT)); then
  if [[ "$REF_KIND" == "branch" ]]; then
    git fetch --no-tags origin "refs/heads/${TARGET:-$BRANCH}"
    TARGET_COMMIT="$(git rev-parse FETCH_HEAD)"
    git show "$TARGET_COMMIT:public/sitemap" 2>/dev/null | grep -Fxq "$EXPECTED_PUBLIC_TARGET" || {
      echo "Target branch does not contain the required runtime-map symlink; refusing checkout." >&2
      exit 2
    }
    if [[ -n "$TARGET" ]]; then
      git checkout --detach "$TARGET_COMMIT"
    else
      git merge --ff-only "$TARGET_COMMIT"
    fi
  else
    git fetch --no-tags origin "refs/tags/$TARGET"
    TARGET_COMMIT="$(git rev-parse 'FETCH_HEAD^{commit}')"
    git show "$TARGET_COMMIT:public/sitemap" 2>/dev/null | grep -Fxq "$EXPECTED_PUBLIC_TARGET" || {
      echo "Target tag does not contain the required runtime-map symlink; refusing checkout." >&2
      exit 2
    }
    git fetch --no-tags origin "refs/tags/$TARGET:$REMOTE_REF"
    TAG_COMMIT="$(git rev-parse "$REMOTE_REF^{commit}")"
    [[ "$TAG_COMMIT" == "$TARGET_COMMIT" ]] || {
      echo "Target tag changed during validation; refusing checkout." >&2
      exit 2
    }
    git checkout --detach "$REMOTE_REF"
  fi
fi

if [[ ! -L "$PUBLIC_MAP" || "$(readlink "$PUBLIC_MAP")" != "$EXPECTED_PUBLIC_TARGET" ]]; then
  echo "Checked-out release has an unexpected public/sitemap path." >&2
  exit 2
fi

# A restrictive backup umask can leave checked-out public assets unreadable by Apache.
# Limit public permissions to the document root and the explicitly published map package.
find "$APP/public" -type d -exec chmod 755 {} +
find "$APP/public" -type f -exec chmod 644 {} +
find "$RUNTIME_MAP" -type d -exec chmod 755 {} +
find "$RUNTIME_MAP" -type f -exec chmod 644 {} +

MAINTENANCE_ATTEMPTED=1
"$PHP" artisan down --retry=60
"$COMPOSER" install --no-dev --optimize-autoloader --no-interaction
"$PHP" artisan migrate --force
"$PHP" artisan hwrt:bootstrap-admin
"$PHP" artisan sitemap:import --path="$RUNTIME_CURRENT"
"$PHP" artisan optimize
chmod -R u+rwX,g+rwX storage bootstrap/cache
"$PHP" artisan up
MAINTENANCE_ATTEMPTED=0
trap - EXIT INT TERM

echo "HWRT update completed. Local recovery files: $BACKUP"
echo "Verified backup copy: $OFFSITE_COPY (operator must confirm destination is truly off-host)."
echo "Check: /board, /map, /pending, /reports, /settings and /error"
