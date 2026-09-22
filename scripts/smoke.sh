#!/usr/bin/env bash
# End-to-end smoke test against a running dev server (php artisan serve).
# Usage: scripts/smoke.sh [base_url] [email] [password]
set -euo pipefail
B=${1:-http://localhost:8000}; EMAIL=${2:-admin@example.com}; PASS=${3:-changeme}
J=$(mktemp); trap 'rm -f $J' EXIT
c() { curl -s -b "$J" -c "$J" "$@"; }
tok() { c "$B$1" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
code() { c -o /dev/null -w '%{http_code}' "$@"; }

echo "login:      $(c -o /dev/null -w '%{http_code}' -X POST "$B/login" -d "_token=$(tok /login)&email=$EMAIL&password=$PASS")"
for p in /board /map /history /log /api/layers /api/areas /api/landmarks "/api/locations?q=control" ; do echo "$p: $(code "$B$p")"; done
echo "nearby:     $(c "$B/api/locations/nearby?easting=476400&northing=7718700" | head -c 200)"
T=$(tok /log)
echo "new loc:    $(c -X POST "$B/api/locations" -H "X-CSRF-TOKEN: $T" -H 'Content-Type: application/json' -H 'Accept: application/json' -d '{"name":"Smoke test pit","easting":476400.5,"northing":7718700.5}' | head -c 200)"
LOC=$(c "$B/api/locations?q=smoke" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
echo "log (cat):  $(code -X POST "$B/log" -d "_token=$T&work_type_id=1&location_id=$LOC&notes=smoke+catalogue&permit_no=P1")"
echo "log (adhoc):$(code -X POST "$B/log" -d "_token=$T&work_type_id=2&location_label=Adhoc+spot&easting=475650&northing=7717250&notes=smoke+adhoc")"
echo "open:       $(c "$B/api/open" -H 'Accept: application/json' | head -c 300)"
ID=$(c "$B/api/open" -H 'Accept: application/json' | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
echo "close $ID:    $(code -X POST "$B/entries/$ID/close" -d "_token=$T&close_note=done")"
echo "history:    $(code "$B/history?status=closed&q=smoke")"
echo "csv:"; c "$B/history.csv?q=smoke" | head -3
