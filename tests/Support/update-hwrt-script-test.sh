#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP="$(mktemp -d)"
trap 'rm -rf "$TEMP"' EXIT
FAKE_BIN="$TEMP/bin"
mkdir -p "$FAKE_BIN"

cat > "$FAKE_BIN/git" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
printf 'git %s\n' "$*" >> "$FAKE_LOG"
case "$1" in
  rev-parse)
    [[ "${2:-}" == --is-inside-work-tree ]] && { echo true; exit 0; }
    [[ "${2:-}" == HEAD ]] && { echo "${FAKE_CURRENT_REF:-original}"; exit 0; }
    [[ "$*" == *FETCH_HEAD* || "$*" == *refs/tags/* ]] && { echo target-commit; exit 0; }
    ;;
  status)
    [[ "${FAKE_DIRTY:-0}" == 1 ]] && echo ' M tracked-file'
    exit 0
    ;;
  symbolic-ref)
    [[ "${FAKE_TAG:-0}" == 1 ]] && exit 1
    echo main
    exit 0
    ;;
  branch)
    echo main
    exit 0
    ;;
  describe)
    [[ "${FAKE_TAG:-0}" == 1 ]] && { echo v2.0.1; exit 0; }
    exit 1
    ;;
  ls-remote)
    [[ "${FAKE_TARGET_EXISTS:-1}" == 1 ]] || exit 1
    if [[ "$*" == *refs/heads/main ]]; then
      echo 'abcdef0123456789 refs/heads/main'
      exit 0
    fi
    if [[ "$*" == *refs/tags/v2.0.1 ]]; then
      echo 'abcdef0123456789 refs/tags/v2.0.1'
      exit 0
    fi
    exit 1
    ;;
  check-ref-format)
    exit 0
    ;;
  show)
    echo ../storage/app/hwrt-sitemap/current
    exit 0
    ;;
  fetch|merge|checkout)
    if [[ "$1" == merge || "$1" == checkout ]]; then
      echo changed > "$FAKE_REF_FILE"
    fi
    exit 0
    ;;
esac
exit 0
MOCK

cat > "$FAKE_BIN/php" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
if [[ "${1:-}" == -v ]]; then
  echo 'PHP mock'
  exit 0
fi
if [[ "${1:-}" == */backup-hwrt-database.php ]]; then
  printf 'database-backup\n' >> "$FAKE_LOG"
  [[ "${FAKE_FAIL:-}" != backup ]] || exit 17
  printf 'mock sql backup\n' > "$2/database-test.sql"
  exit 0
fi
if [[ "${1:-}" == artisan ]]; then
  action="${2:-}"
  printf '%s\n' "$action" >> "$FAKE_LOG"
  [[ "$action" != "${FAKE_FAIL:-never}" ]] || exit 17
  exit 0
fi
exit 0
MOCK

cat > "$FAKE_BIN/composer" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
printf 'composer %s\n' "$*" >> "$FAKE_LOG"
[[ "${1:-}" != install || "${FAKE_FAIL:-}" != composer ]] || exit 19
exit 0
MOCK

chmod +x "$FAKE_BIN/"*

new_fixture() {
  APP="$TEMP/app-$1"
  OFFSITE="$TEMP/offsite-$1"
  mkdir -p "$APP/scripts" "$APP/storage/app/private" "$APP/sitemap" "$APP/public" "$OFFSITE"
  cp "$ROOT/scripts/update-hwrt.sh" "$APP/scripts/update-hwrt.sh"
  : > "$APP/artisan"
  : > "$APP/composer.json"
  : > "$APP/composer.lock"
  printf 'APP_ENV=testing\n' > "$APP/.env"
  printf '{"rasters":[]}\n' > "$APP/sitemap/manifest.json"
  if ! ln -s ../storage/app/hwrt-sitemap/current "$APP/public/sitemap" 2>/dev/null; then
    echo 'SKIP: this host cannot create temporary symlinks; run these tests on Linux CI.' >&2
    exit 0
  fi
  printf 'original' > "$APP/ref-state"
  LOG="$TEMP/log-$1"
  : > "$LOG"
}

run_update() {
  local expected=$1
  shift
  local -a extra_env=()
  while (($#)) && [[ "$1" == FAKE_* ]]; do
    extra_env+=("$1")
    shift
  done
  set +e
  (cd "$APP" && env \
    PATH="$FAKE_BIN:$PATH" \
    PHP_BIN="$FAKE_BIN/php" \
    COMPOSER_BIN="$FAKE_BIN/composer" \
    HWRT_OFFSITE_BACKUP_DIR="$OFFSITE" \
    FAKE_LOG="$LOG" \
    FAKE_REF_FILE="$APP/ref-state" \
    "${extra_env[@]}" \
    ./scripts/update-hwrt.sh "$@") > "$TEMP/stdout" 2> "$TEMP/stderr"
  local result=$?
  set -e
  if [[ "$expected" == success && $result -ne 0 ]]; then
    cat "$TEMP/stdout" "$TEMP/stderr" >&2
    echo "Expected update success; got exit $result." >&2
    exit 1
  fi
  if [[ "$expected" == failure && $result -eq 0 ]]; then
    echo "Expected update failure." >&2
    exit 1
  fi
}

assert_absent() {
  if grep -Fq "$1" "$LOG"; then
    echo "Unexpected event '$1' in $LOG" >&2
    exit 1
  fi
}

new_fixture default-branch
run_update success
grep -Fq 'git merge --ff-only target-commit' "$LOG"
assert_absent 'checkout'
grep -Fq 'down' "$LOG"
grep -Fq 'up' "$LOG"
[[ -f "$OFFSITE"/*/database-test.sql ]]

new_fixture remote-tag
run_update success FAKE_CURRENT_REF=original --target v2.0.1
grep -Fq 'git checkout --detach refs/tags/v2.0.1' "$LOG"

new_fixture no-git
run_update success FAKE_TAG=1 --no-git
assert_absent 'git fetch'
assert_absent 'git checkout'
assert_absent 'git merge'

new_fixture backup-failure
run_update failure FAKE_FAIL=backup
grep -Fq 'down' "$LOG"
grep -Fq 'up' "$LOG"
assert_absent 'git fetch'
assert_absent 'git checkout'
[[ "$(cat "$APP/ref-state")" == original ]]
[[ ! -e "$OFFSITE"/*/database-test.sql ]]

new_fixture composer-failure
run_update failure FAKE_FAIL=composer
grep -Fq 'up' "$LOG"
[[ "$(cat "$APP/ref-state")" == changed ]]
[[ "$(grep -c '^up$' "$LOG")" -eq 2 ]]

new_fixture dirty-preflight
run_update failure FAKE_DIRTY=1
assert_absent 'down'
assert_absent 'git fetch'
[[ ! -e "$APP/storage/app/backups" ]]
[[ "$(cat "$APP/ref-state")" == original ]]

new_fixture bad-target
run_update failure FAKE_TARGET_EXISTS=0 --target missing
assert_absent 'down'
assert_absent 'git fetch'
[[ ! -e "$APP/storage/app/backups" ]]
[[ "$(cat "$APP/ref-state")" == original ]]

new_fixture bad-public-map
rm "$APP/public/sitemap"
mkdir "$APP/public/sitemap"
printf 'tracked map directory\n' > "$APP/public/sitemap/manifest.json"
run_update failure
assert_absent 'down'
assert_absent 'git fetch'
[[ -f "$APP/public/sitemap/manifest.json" ]]
[[ ! -e "$APP/storage/app/backups" ]]

new_fixture no-git-target
run_update failure FAKE_TAG=1 --no-git --target v2.0.1
assert_absent 'down'
assert_absent 'git fetch'
[[ ! -e "$APP/storage/app/backups" ]]

new_fixture missing-offsite
set +e
(cd "$APP" && env PATH="$FAKE_BIN:$PATH" PHP_BIN="$FAKE_BIN/php" COMPOSER_BIN="$FAKE_BIN/composer" FAKE_LOG="$LOG" FAKE_REF_FILE="$APP/ref-state" ./scripts/update-hwrt.sh) > "$TEMP/stdout" 2> "$TEMP/stderr"
result=$?
set -e
[[ $result -ne 0 ]]
assert_absent 'down'
assert_absent 'git fetch'
[[ ! -e "$APP/storage/app/backups" ]]

echo 'update-hwrt safety tests passed'
