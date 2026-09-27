# HWRT V2 — merge and deployment readiness checklist

**Date:** 27 September 2026 (Australia/Perth)  
**Repository:** medrescuetech/cselog  
**Target:** merge PR #10 (`v2/release-candidate`) into `main`, tag a tested V2 release, then update the cPanel installation.  
**Status:** in progress. A passing CI run is evidence for its tested commit only; it does not prove a live deployment.

This checklist complements [docs/V2.md](../V2.md) and [the September 25 continuation](HWRT_V2_CONTINUATION_2026-09-25_1534_AWST.md). Work through the gates in order and record evidence in the V2 PR. Do not mark a gate complete merely because code or a plan exists.

## Current repository facts (28 September 2026)

- PR #10 is the consolidated `v2/release-candidate` to `main` PR and includes the useful work from closed PRs #8 and #9, the Support link, and this checklist. PR #5 is closed as superseded. PR #11 remains open separately, but its location-picker badge removal is already in the release candidate.
- The release candidate is mergeable and the Visual map acceptance CI run on `c77959e` passed map/browser, MySQL and MariaDB jobs. Later documentation commits require final-head CI recheck. The CI database jobs do not yet prove a dump/restore on the cPanel host.
- `VERSION` remains `2.0.0-dev`. There is no V2 release tag or verified staging/production deployment.
- Known review host path is `/home/akgmxkpo/csem-review`; the current document root and deployed ref remain unverified.

## Gate 1 — reconcile branches and scope

- [x] Consolidate V2 in PR #10 against `main`. [ ] Capture the final head SHA and mergeability after staging acceptance.
- [x] Carry PR #8 bootstrap, permissions and map-publisher changes into the consolidated PR #10 branch. [ ] Complete staging acceptance of these paths.
- [x] Close overlapping PR #5 as superseded by merged PR #4 and the consolidated V2 branch.
- [ ] Check the complete V2 diff against `main` for accidental generated files, credentials, old branding in visible UI, and unrelated changes. Preserve existing user records and historical entries.
- [ ] Resolve any outstanding reviews or branch protection checks on the V2 PR. Recheck the final merge commit candidate rather than relying on an older passing run.

## Gate 2 — product and data acceptance

- [ ] Verify visible branding reads **HWRT — High Risk Work Tracker**, including login, navigation, page titles, error pages, downloads, and footer version. Confirm the top navigation shows **Support** linking to https://support.akgmed.org/ on desktop and narrow screens on PR #10. Decide whether legacy CSEM names in internal namespaces/log paths are intentional and document them.
- [ ] Test upgrade of a copy of the current live database, not only a fresh SQLite seed. Confirm existing users can sign in with username or optional email, legacy roles migrate to User/Admin as specified, and an active Admin survives. Inventory conflicting usernames/emails before applying unique constraints.
- [ ] Verify Admin can create, edit, activate/deactivate and reset users in Settings; User cannot enter admin routes. Verify self-demotion/deactivation and last-Admin protections, reserved bootstrap username, forced password change, and that bootstrap credentials cannot remain usable after setup. Do not publish default credentials.
- [ ] Verify work-type configuration, required notes, Other description, historical references and immutable HRW IDs on existing and new entries.
- [ ] Verify pending work uses Australia/Perth date boundaries, Start/Cancel audit events, and does not silently become Open.
- [ ] Verify Open Board, Map, Logbook, History, Reports, and CSV show consistent IDs, statuses, filters, date boundaries, and access permissions. Include empty results and a multi-page dataset.
- [ ] Verify map pin position and area lookup with imagery/plan opacity, optional API failures, local assets, location PDFs, landmarks, refresh and rollback. Ensure application runtime sends no map or user data to ArcGIS.
- [ ] Confirm the real report outputs requested for operations. The current V2 scope includes filtered table, summary and CSV; printable/PDF shift handover is listed as a possible later release. Record any requirement that must move into V2 before merge.

## Gate 3 — repair the update path before it is used

- [x] Implement the documented `scripts/update-hwrt.sh --no-git` interface for an already checked-out release. Rehearse it on staging before production use.
- [ ] Verify `public/sitemap` on staging is the tracked symlink `../storage/app/hwrt-sitemap/current` (confirmed in the release-candidate checkout). A legacy deployment with a real directory intentionally causes the helper to stop before maintenance mode; migrate that directory to the runtime package deliberately and rehearse the atomic package swap.
- [ ] Avoid changing Git refs while the app is in maintenance mode unless failure handling and rollback are tested. Validate branch/tag and clean working tree before mutation; refuse unsupported targets. Ensure maintenance mode exits on every failure.
- [x] Implement backup of the actual configured database before migrations: SQLite consistent snapshot or MariaDB/MySQL compressed dump, failing the update if backup fails. See [database backup and restore](../12-database-backup-and-restore.md). [ ] Rehearse dump and restore with the actual cPanel database, and make a verified off-host copy. Do not put secrets into command output or Git.
- [ ] Back up/restorable uploads, map package, `.env` location and release ref. Distinguish code rollback from database restore if a migration is irreversible. Test restore on a disposable copy.
- [ ] Verify PHP and Composer paths, permissions, build artifacts and `vendor/` on cPanel without sudo. Ensure local Tailwind, Alpine and Leaflet files are available after a clean deployment.
- [x] Replace the old cPanel `rsync --delete` and password-in-command sketch with the V2 SSH release procedure in [deployment guidance](../08-deployment-cpanel.md). [ ] Fill in actual host PHP path, domain document root and database after inspecting cPanel.
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

1. Inspect cPanel's actual document root, database driver, dump utility and current deployment ref.
2. Rehearse PR #10 upgrade, backup and restore against a staging copy, including a second idempotent update.
3. Complete final-head CI and acceptance; set release version, merge PR #10, and tag the exact tested release.
4. Update the production site from that release and record smoke tests and rollback reference.
