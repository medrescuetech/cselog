# HWRT V2 — merge and deployment readiness checklist

**Date:** 27 September 2026 (Australia/Perth)  
**Repository:** medrescuetech/cselog  
**Target:** merge `v2/hwrt` into `main`, tag a tested V2 release, then update the cPanel installation.  
**Status:** in progress. A passing CI run is evidence for its tested commit only; it does not prove a live deployment.

This checklist complements [docs/V2.md](../V2.md) and [the September 25 continuation](HWRT_V2_CONTINUATION_2026-09-25_1534_AWST.md). Work through the gates in order and record evidence in the V2 PR. Do not mark a gate complete merely because code or a plan exists.

## Current repository facts

- `main` includes merged PR #4 (map startup and browser acceptance), #6 (diagnostics), and #7 (initial four-role user manager and board headers).
- PR #5 is open against `main` and reports `mergeable=false`. Its map startup and visual CI changes overlap PR #4; its local Leaflet assets overlap V2/PR #8. Do not merge it wholesale. Compare its unique improvements individually, close as superseded once accounted for.
- `v2/hwrt` is 81 commits ahead and zero behind `main` at the time of review. It carries HWRT branding, username and two-role access, settings, pending work, reports/CSV, map features, and an update helper.
- PR #8 is open and mergeable into `v2/hwrt`, adding bootstrap account protection, atomic map-package publishing, local assets, and further tests. Its head `67fa750` has a successful Visual map acceptance workflow run `36211043330`; inspect job steps and artifacts before treating the whole V2 test matrix as complete.
- `VERSION` on V2 is `2.0.0-dev`. There is no established V2 release tag or verified cPanel V2 rollout.
- Known review host path from the continuation is `/home/akgmxkpo/csem-review`, but the current domain document root and deployed Git ref remain unverified.

## Gate 1 — reconcile branches and scope

- [ ] Open a V2-to-`main` PR (or verify one exists) and attach this checklist. Capture the exact base/head SHAs and check GitHub's mergeability after PR #8 is integrated.
- [ ] Review PR #8 file changes and CI logs/artifacts. Merge it into `v2/hwrt` only after its bootstrap, permissions and map-publisher behaviour pass tests and review. Recheck `v2/hwrt` head and CI afterwards.
- [ ] Compare PR #5 file by file with merged PR #4 and V2. Keep only demonstrably missing value; avoid restoring older map startup logic or a second overlapping CI suite. Close PR #5 as superseded when the comparison is documented.
- [ ] Check the complete V2 diff against `main` for accidental generated files, credentials, old branding in visible UI, and unrelated changes. Preserve existing user records and historical entries.
- [ ] Resolve any outstanding reviews or branch protection checks on the V2 PR. Recheck the final merge commit candidate rather than relying on an older passing run.

## Gate 2 — product and data acceptance

- [ ] Verify visible branding reads **HWRT — High Risk Work Tracker**, including login, navigation, page titles, error pages, downloads, and footer version. Confirm the top navigation shows **Support** linking to https://support.akgmed.org/ on desktop and narrow screens after PR #8 is merged. Decide whether legacy CSEM names in internal namespaces/log paths are intentional and document them.
- [ ] Test upgrade of a copy of the current live database, not only a fresh SQLite seed. Confirm existing users can sign in with username or optional email, legacy roles migrate to User/Admin as specified, and an active Admin survives. Inventory conflicting usernames/emails before applying unique constraints.
- [ ] Verify Admin can create, edit, activate/deactivate and reset users in Settings; User cannot enter admin routes. Verify self-demotion/deactivation and last-Admin protections, reserved bootstrap username, forced password change, and that bootstrap credentials cannot remain usable after setup. Do not publish default credentials.
- [ ] Verify work-type configuration, required notes, Other description, historical references and immutable HRW IDs on existing and new entries.
- [ ] Verify pending work uses Australia/Perth date boundaries, Start/Cancel audit events, and does not silently become Open.
- [ ] Verify Open Board, Map, Logbook, History, Reports, and CSV show consistent IDs, statuses, filters, date boundaries, and access permissions. Include empty results and a multi-page dataset.
- [ ] Verify map pin position and area lookup with imagery/plan opacity, optional API failures, local assets, location PDFs, landmarks, refresh and rollback. Ensure application runtime sends no map or user data to ArcGIS.
- [ ] Confirm the real report outputs requested for operations. The current V2 scope includes filtered table, summary and CSV; printable/PDF shift handover is listed as a possible later release. Record any requirement that must move into V2 before merge.

## Gate 3 — repair the update path before it is used

- [ ] Reconcile the documentation with `scripts/update-hwrt.sh`: `docs/V2.md` describes `./scripts/update-hwrt.sh --no-git`, while the script treats its argument as a Git target and has no `--no-git` option. Implement and test a documented interface.
- [ ] Resolve `public/sitemap` handling. It is a tracked path on V2; the helper calls `ln -sfn` on it, which cannot safely replace a non-empty tracked directory. Choose a single source of truth for runtime map files and publish/swap atomically without dirtying the Git worktree or nesting a symlink inside the directory.
- [ ] Avoid changing Git refs while the app is in maintenance mode unless failure handling and rollback are tested. Validate branch/tag and clean working tree before mutation; refuse unsupported targets. Ensure maintenance mode exits on every failure.
- [ ] Back up the actual configured database before migrations. The helper currently copies only `database/database.sqlite`; for cPanel MariaDB/MySQL it merely prints a reminder. Add a tested dump/restore procedure and an off-host copy appropriate to the deployed database. Do not put secrets into command output or Git.
- [ ] Back up/restorable uploads, map package, `.env` location and release ref. Distinguish code rollback from database restore if a migration is irreversible. Test restore on a disposable copy.
- [ ] Verify PHP and Composer paths, permissions, build artifacts and `vendor/` on cPanel without sudo. Ensure local Tailwind, Alpine and Leaflet files are available after a clean deployment.
- [ ] Correct old cPanel deployment guidance (`ea-php82`, placeholder paths and contradictory deploy layouts) to match the actual PHP 8.3 host, document root, app directory and the chosen single update mechanism. Never run the old sketch's `rsync --delete` against live storage.
- [ ] Make the helper idempotent and run it twice on the staging copy. Confirm the second run leaves the database, map files, and Git checkout healthy. Document exact install/update and rollback commands with examples.

## Gate 4 — automated and manual verification

- [ ] Run Composer validation, PHP syntax checks and the full PHPUnit suite against the final V2 head. Record counts and failing test names; fix failures rather than accepting a prior run from another SHA.
- [ ] Run the browser/Playwright map acceptance with real sitemap import and review screenshots and network failures. Verify raster decoded in the visible viewport and the Control Room pin stays aligned when switching plan/imagery.
- [ ] Run tests for users, bootstrap, migration upgrade, reports/CSV, pending work, permissions, map package publishing and update helper failure recovery. Add focused coverage where a critical behaviour has no test.
- [ ] Run production-style checks with `APP_DEBUG=false`, cached configuration/routes and the same database engine and PHP version as the host. Confirm logs contain useful failure detail without passwords or request bodies.
- [ ] Record final commit SHA, CI run URL, manual acceptance outcomes and known limitations in the V2 PR.

## Gate 5 — staging rehearsal and merge

- [ ] Verify the actual `dev.akgmed.org` document root, deployed directory, branch/commit, environment, database engine, map data and backup location. Do not assume `~/csem-review` serves the domain merely because local `/login` and `/up` returned 200.
- [ ] Take restorable backups, clone/snapshot the deployment and database, then perform a V1-to-V2 update on staging using the final script. Validate migrations, users, reports, map, assets, errors, and write workflows through HTTPS with at least Admin and User accounts.
- [ ] Rehearse a failed update and recovery, including maintenance-mode exit and restoration from the backup. Capture the actual commands and results.
- [ ] Recheck CI and mergeability against the current `main` head. Merge the V2 PR after review and successful staging rehearsal; do not merge PR #5 just to make it disappear.
- [ ] Set `VERSION` to `2.0.0` for the release commit and create a matching `v2.0.0` tag at the exact approved commit. Ensure the tag and changelog correspond to what staging tested.

## Gate 6 — production rollout (after merge)

- [ ] Confirm which live cPanel domain and directory should receive HWRT, plus a maintenance window and recovery contact. Staging and production must not accidentally share a writable database or `.env`.
- [ ] Take and verify fresh production database/file backups and capture the previous deployed commit. Perform the documented update to the tagged release; apply migrations once and verify app comes out of maintenance mode.
- [ ] Smoke test login, user management, work logging, closing, map imagery and pin alignment, reports/CSV, `/error`, local frontend assets and mobile/desktop views. Review logs and monitoring after ordinary use.
- [ ] Record deployed domain, tag/SHA, date/time (Australia/Perth), database backup reference, smoke-test outcomes and rollback result/plan. Update README so it describes the deployed product rather than the old local CSEM status.

## Immediate next actions

1. Review and integrate PR #8 into `v2/hwrt`.
2. Repair the update helper and contradictory docs, then verify upgrade/restore on a database copy.
3. Complete full CI and staging acceptance on the final V2 SHA.
4. Open/finish the V2-to-`main` PR, merge, tag, and deploy the exact tested release.
