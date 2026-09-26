# HWRT V2 Continuation — 2026-09-25 15:34 AWST

**Product:** HWRT — High Risk Work Tracker  
**Repository:** `medrescuetech/cselog`  
**Development branch:** `v2/hwrt`  
**Current V2 version marker:** `2.0.0-dev`  
**Timezone standard:** `Australia/Perth`  
**Purpose:** Durable continuation/handover point for the V2 build and cPanel deployment work.

---

## 1. Current project position

The original CSE Log/CSEM application has moved into a V2 redesign called **HWRT — High Risk Work Tracker**.

The stable V1/review work already merged to `main` includes:

- blank-map startup fix;
- real Chromium visual map acceptance testing;
- Control Room imagery/plan pin-alignment test;
- dedicated application/browser error logging;
- protected `/error` viewer;
- initial Admin user-management page;
- Open Board conversion to a table with explicit headers.

The active V2 work is being developed on:

```text
v2/hwrt
```

V2 has **not yet been merged to `main` or tagged as a release**. It should be treated as development code until the V2 test suite is clean and the cPanel dev instance has been upgraded and manually reviewed.

---

## 2. Known cPanel review environment

The current cPanel account is:

```text
/home/akgmxkpo
```

Known review application path:

```text
/home/akgmxkpo/csem-review
```

Known host capabilities:

- PHP `8.3.33`
- Laravel `13.32.0`
- Git `2.48.2`
- Composer installed locally at `~/bin/composer`
- SQLite/PDO SQLite available
- MySQL/PDO MySQL available
- required PHP modules present including mbstring, openssl, curl, fileinfo, tokenizer and XML support.

The review database was initialised successfully and the existing map package imported as:

```text
areas: 169
landmarks: 142
```

Local cPanel preflight returned HTTP 200 for:

```text
/login
/up
```

The existing review map raster is present and readable through the public sitemap link.

A dedicated diagnostic log has also been proven writable on the host.

### Important deployment uncertainty

The last fully confirmed state was the local cPanel preflight. Before the next V2 deployment, verify the current document root for `dev.akgmed.org` and the current Git branch/commit in `~/csem-review` rather than assuming the live dev domain has already been switched.

---

## 3. V2 product identity and versioning

Implemented/planned V2 identity:

```text
HWRT — High Risk Work Tracker
```

A repository `VERSION` file has been added with:

```text
2.0.0-dev
```

The authenticated UI is intended to display:

```text
HWRT v2.0.0-dev
```

in the bottom-left corner.

Future release model is semantic versioning:

- `v2.0.0` first V2 release;
- `v2.0.1` bugfix;
- `v2.1.0` backwards-compatible feature;
- `v3.0.0` breaking product/data-model change.

A safe update helper has been added:

```text
scripts/update-hwrt.sh
```

The intention is to use Git tags/branches for controlled cPanel updates.

---

## 4. V2 database direction

HWRT is already database-backed.

### Development/review

SQLite remains appropriate for the dev/review instance because it is simple and already working on cPanel.

### Production recommendation

For the final multi-user production instance, MariaDB/MySQL on cPanel is preferred because it gives:

- better concurrent writes;
- easier cPanel backup/restore;
- better long-term multi-user behaviour;
- less risk of SQLite locking under concurrent operational use.

No spatial database extension is required. Map geometry remains JSON/GeoJSON.

---

## 5. V2 schema work already added to the branch

A V2 migration exists:

```text
database/migrations/2026_09_25_000002_hwrt_v2.php
```

It currently introduces/extends the following concepts:

- `settings` table;
- unique user `username`;
- optional user email;
- simplified User/Admin permissions;
- work-type `notes_prompt`;
- work-type `is_other`;
- immutable `hrw_ref` such as `HRW-000001`;
- `other_description` for the Other work type;
- `planned_start_at` for advance scheduling;
- `scheduled_by`;
- optional private PDF metadata on catalogue locations.

Existing HRW records are intended to be backfilled with HRW references during migration.

### Migration item to verify before release

The V2 migration must be tested against both:

1. a fresh SQLite database; and
2. the existing cPanel review SQLite database.

Do not deploy V2 to production until this migration rehearsal is clean and a backup/restore cycle has been demonstrated.

---

## 6. Authentication and users

The V2 permission model is intentionally simplified to:

| Role | Access |
|---|---|
| User | Operational HWRT access: Board, Map, Log Work, Pending, Logbook, Reports, start/close work |
| Admin | Everything above plus Settings and diagnostics |

Existing legacy `viewer`, `logger` and `supervisor` roles are intended to migrate to `user`.

### Username login

V2 supports:

- username login; or
- email login when an email exists.

Email is optional and no longer needs to be the login identity.

Settings → Users work has been moved toward:

- display name;
- username;
- optional email;
- User/Admin role;
- active/inactive;
- password reset;
- protection against deactivating/demoting the current Admin;
- requirement to retain at least one active Admin.

---

## 7. High Risk Work types and Notes

V2 work types are database-driven and Admin-configurable under Settings.

Implemented/planned work-type properties:

- name;
- colour;
- sort order;
- active/inactive;
- default;
- Notes required yes/no;
- Notes prompt/help text;
- special `Other` flag.

Initial defaults added to the V2 seeder include:

- Confined Space Entry
- Hot Work
- Working at Heights
- Excavation
- Electrical Isolation
- Inspection
- Other

### Other

If the Other type is selected, an additional description is required and stored on the individual HRW record.

### Notes

Every HRW can have Notes.

A work type can additionally:

- make Notes mandatory; and/or
- provide a work-specific Notes prompt.

---

## 8. Unique HRW number

Each high-risk-work record receives a permanent reference in the form:

```text
HRW-000001
```

The reference is generated from the database entry ID and is intended to appear on:

- Open Board;
- Pending list;
- Logbook;
- detailed History;
- Reports;
- CSV exports;
- map-pin details where appropriate.

It is never reused.

---

## 9. Open Board

The Open Board has been converted from the original card layout into a table with explicit headers.

V2 operational columns include:

- HRW ID
- Elapsed
- Type
- Location
- Area
- Permit
- Notes / Reported by
- Opened
- Opened by
- Action

The live refresh, elapsed-time colour bands and close action are retained.

Board refresh failures now report through the central HWRT error pipeline rather than silently failing.

---

## 10. Advance scheduling / Pending jobs

This is now part of V2 scope and branch work.

A job can be logged before it starts by entering a future planned start.

Behaviour:

- record receives its HRW ID immediately;
- status becomes `pending`;
- `planned_start_at` is retained;
- `scheduled_by` records who created it;
- future pending work appears in a dedicated Pending screen;
- work planned for the current Perth calendar day appears in **Pending Today** on the Open Board;
- pending work does **not** automatically become Open;
- a user presses **Start** when work actually begins;
- actual start time/user are then recorded;
- planned start remains available for audit/reporting;
- pending work can be cancelled with an audit event.

### Timezone

All operational dates and times are standardised to:

```text
Australia/Perth
```

This is intentional even if:

- the user's browser is in another timezone; or
- the underlying cPanel host is using another server timezone.

The log screen displays a Perth clock and Laravel is the authoritative source for actual start/close timestamps.

---

## 11. Logbook and Reports

V2 has controller/view work for a recent Logbook and filtered Reports.

### Logbook

Default intent is recent work with selectable windows:

- 3 days
- 7 days
- 14 days
- 30 days

Information includes:

- HRW ID;
- planned start;
- actual opened time;
- closed time;
- status;
- type;
- location;
- area;
- permit;
- logged by.

### Reports

Planned filters:

- From date
- To date
- Type
- Status including Pending/Open/Closed/Cancelled
- Area
- Free-text search
- All records when no filters are supplied

Summary cards include totals by status.

CSV export uses the same filtering concept and includes HRW ID.

---

## 12. Location PDFs

V2 branch work now includes one optional PDF per catalogue location.

Admin workflow:

```text
Settings → Locations & PDFs
```

Behaviour:

- upload/replace/remove PDF;
- PDF stored in Laravel private storage, not the public web root;
- authenticated users access it through a protected route;
- replacing a PDF deletes the previous stored file;
- an open HRW pin linked to that catalogue location includes an **Open location PDF** link.

This means location-specific plans/instructions can travel with the map location without exposing the underlying files publicly.

---

## 13. Manual key landmarks

V2 branch work includes manual landmark management under:

```text
Settings → Landmarks
```

Landmark data includes:

- name;
- category;
- MGA Zone 50 easting;
- MGA Zone 50 northing;
- active/inactive;
- source.

The Settings page includes the live site map. Clicking the map fills the MGA coordinates, allowing an Admin to add a key landmark without separately calculating coordinates.

Manual landmarks coexist with imported infrastructure landmarks because sitemap import uses keyed update/create behaviour rather than deleting all existing landmarks.

---

## 14. Map architecture and privacy

### Runtime map

HWRT runtime map operation is local.

The browser uses local endpoints/files such as:

```text
/api/layers
/api/areas
/api/landmarks
/sitemap/...
```

The application does **not** send operational HRW data to ArcGIS.

Specifically, routine map operation does not upload:

- HRW jobs;
- pins;
- notes;
- permits;
- users/usernames;
- location PDFs;
- location catalogue records;
- application database data.

### ArcGIS metadata

ArcGIS URLs stored in the map manifest are provenance/source information and are now intended to remain server-side rather than being returned unnecessarily to normal browser sessions.

### Current remaining privacy hardening

The UI still needs the final V2 step of moving frontend libraries off public CDNs:

- Tailwind
- Alpine
- Leaflet

Until that is completed, browsers still make ordinary outbound requests to those CDNs. That does not transmit the HWRT database, but it can expose normal HTTP metadata such as IP, user-agent and referrer.

**No-runtime-third-party-dependency is still a V2 release requirement.**

---

## 15. Map refresh design

The user requirement is to keep the map equivalent to what exists now while making refresh easy.

V2 now has branch work for:

```text
Settings → Map
```

with refresh cadence options:

- Manual only
- Daily
- Weekly
- Monthly

and:

```text
Request map refresh now
```

### Refresh behaviour

The refresh command is:

```text
php artisan hwrt:map-refresh --scheduled
```

The scheduler checks for:

- a manual queued refresh; or
- a refresh that is due based on configured cadence.

The refresh performs download-only requests to the current public ArcGIS source services.

The intended sequence is:

1. identify the imagery source (auto-discovery or Admin override);
2. read the current installed Site C/F map footprint;
3. use that **same current footprint** as the imagery bounding box;
4. download new L17/L16 imagery into a staging directory;
5. download the existing feature data sets;
6. validate required staged files are present/non-empty;
7. replace the runtime package only after the complete download stage succeeds;
8. rebuild the local manifest;
9. run `sitemap:import`;
10. record last attempt/success/status/error in Settings.

### Engineering plan overlays

Routine automatic map refresh does **not** automatically replace the current engineering/plot-plan overlays.

Those should remain deliberate Git-reviewed changes because they affect the interpretation/alignment of the operational site plan.

### Failure behaviour

The current map remains live while a refresh is downloading.

If refresh fails before replacement, the intention is that the existing operational map remains available.

Before release, this needs a dedicated failure/rollback test to prove a failed refresh cannot leave a partially replaced package.

---

## 16. Runtime map directory versus Git

Automatic map refresh must not modify Git-tracked map files in production because that would leave the working tree dirty and make future `git pull` updates conflict.

The chosen V2 deployment model is:

```text
Git seed package:
~/csem-review/sitemap

Runtime map package:
~/csem-review/storage/app/hwrt-sitemap

Public URL link:
~/csem-review/public/sitemap -> ../storage/app/hwrt-sitemap
```

The update helper seeds the runtime package from the Git package if it does not exist.

Automatic/manual operational refresh then modifies only the runtime copy.

---

## 17. Safe Git update model

The branch now includes:

```text
scripts/update-hwrt.sh
```

Intended behaviour:

1. determine app path;
2. create database backup directory;
3. back up SQLite when used;
4. ensure runtime map package exists;
5. point `public/sitemap` to the runtime map;
6. place Laravel into maintenance mode;
7. `git fetch --tags`;
8. pull/update the chosen branch/tag;
9. run Composer install;
10. run migrations;
11. import runtime sitemap;
12. optimise Laravel;
13. restore application availability.

For a MySQL/MariaDB production instance, normal cPanel/database backup must be taken before major releases.

---

## 18. Diagnostics

V2 diagnostics are being renamed to:

```text
storage/logs/hwrt-errors.log
```

Admin-only `/error` remains the HTML viewer.

The central logging pipeline is intended to capture:

- Laravel/server exceptions;
- JavaScript errors;
- promise rejections;
- failed browser resources;
- map startup failures;
- map/raster/layer warnings;
- live-board/open-entry refresh failures;
- map refresh failures.

Passwords and request bodies must not be logged.

---

## 19. Tests already carried forward / updated

The original working project already had:

- PHPUnit feature/unit tests;
- real Chromium/Playwright map rendering test;
- actual raster-visible verification;
- Control Room imagery/plan alignment test.

During V2 work, tests have been updated/added for:

- User/Admin permissions;
- username login without email;
- inactive-user login rejection;
- HRW ID generation;
- Open Board headers;
- advance Pending workflow;
- Perth planned start;
- explicit Start behaviour;
- local-only map layer URLs;
- ArcGIS source metadata not exposed via `/api/layers`;
- work-type Settings;
- light theme setting;
- location PDF private storage/download;
- manual landmarks;
- map refresh request queue;
- V2 error log naming.

### Critical status

**Status updated 2026-09-26:** the latest complete CI run passed on commit
`53704fd2801f8ee957098bafadae5c7989b6e601`. The original pending steps below are historical;
completed work and outstanding gates are recorded in section 24. Do not treat the old CI,
migration, frontend, map-atomicity or database-decision items below as still open.

---

## 20. Original high-priority plan (historical)

The steps in this section captured the plan on 2026-09-25. Their current status is recorded in
section 24; that status supersedes the instructions below.

### Step 1 — run a full V2 CI build now

Open a V2 pull request (or otherwise trigger CI) from:

```text
v2/hwrt
```

to:

```text
main
```

Run:

```text
composer install
php artisan migrate:fresh --seed
php artisan sitemap:import
php artisan test
npm install
Playwright Chromium visual tests
```

Fix every V2 regression before deployment.

### Step 2 — specifically validate the V2 migration

Test both:

- fresh SQLite installation; and
- upgrade from a copy of the current cPanel review database.

Confirm:

- usernames are generated for existing accounts;
- legacy roles become User/Admin correctly;
- existing entries get HRW IDs;
- existing work types remain valid;
- map/location records survive;
- no existing HRW/history data is lost.

### Step 3 — fix any schema defaults left from V1

Review the base schema/defaults so a fresh V2 database cannot create a legacy `logger` role by default.

The final schema should only use:

```text
user
admin
```

### Step 4 — finish the no-CDN V2 frontend

Bundle/serve locally:

- Tailwind;
- Alpine;
- Leaflet CSS/JS/icons.

Re-run visual map tests with outbound CDN access unavailable.

This is required before calling V2 privacy-hardening complete.

### Step 5 — harden map refresh atomicity

The refresh currently stages downloads before replacement, but the final implementation should prove that a failure during the replacement phase cannot produce a half-old/half-new package.

Recommended improvement:

- build a complete new runtime package directory;
- validate it;
- atomically switch a symlink/current-directory pointer to the new package;
- retain the previous package for rollback.

This is safer than replacing individual files one by one.

### Step 6 — verify scheduler/cron on cPanel

The Laravel scheduler should be invoked every minute on cPanel, e.g.:

```bash
cd /home/akgmxkpo/csem-review && /usr/local/bin/php artisan schedule:run >/dev/null 2>&1
```

Confirm the actual PHP path used by the account before creating the cron entry.

Then test:

- Manual only does nothing unless requested;
- Request refresh now is picked up;
- Daily/Weekly/Monthly due logic works;
- failed refresh records an error and leaves the old map usable.

### Step 7 — cPanel V2 deployment rehearsal

Do **not** overwrite the working review database without a backup.

Suggested sequence:

1. back up `database/database.sqlite`;
2. back up `.env`;
3. confirm current Git status is clean;
4. fetch the V2 branch;
5. set `APP_TIMEZONE=Australia/Perth`;
6. set/confirm HWRT admin username/email/password variables;
7. seed `storage/app/hwrt-sitemap` from current `sitemap/`;
8. repoint `public/sitemap` to runtime map;
9. run the V2 migration;
10. run `sitemap:import` against runtime map;
11. run `php artisan optimize`;
12. manually test every V2 screen;
13. inspect `/error` and `hwrt-errors.log`.

### Step 8 — manual operational acceptance

On `dev.akgmed.org`, manually verify:

- login by username;
- User versus Admin permissions;
- dark/light setting;
- create/edit/deactivate users;
- work-type Settings;
- Other description;
- Notes prompts/requirements;
- log immediate HRW;
- unique HRW ID;
- schedule future HRW;
- Pending list;
- Pending Today on correct Perth date;
- Start pending job;
- close job;
- Open Board live refresh;
- Logbook;
- Reports and CSV;
- map imagery;
- plan overlay alignment;
- manual landmark creation by map click;
- location PDF upload;
- PDF link from map pin;
- `/error` diagnostics;
- map refresh request and refresh-status display.

### Step 9 — production database decision

Before production release, decide whether to:

- remain on SQLite for the expected load; or
- move to cPanel MariaDB/MySQL.

For a real multi-user operational deployment, MariaDB/MySQL remains the preferred option.

### Step 10 — tag V2 only after acceptance

Once CI + cPanel review are clean:

```text
VERSION -> 2.0.0
git tag v2.0.0
```

Do not tag `v2.0.0` while the branch still contains `2.0.0-dev` or known untested migration/runtime-refresh behaviour.

---

## 21. Additional engineering considerations before release

### Pending records and timestamps

The current compatibility approach keeps the existing non-null V1 `opened_at`/`opened_by` columns and uses the planned value provisionally for Pending records. When Start is pressed, actual start replaces those values while `planned_start_at` preserves the plan.

This is practical for SQLite compatibility but should be explicitly documented in the model/tests so future developers do not incorrectly treat `opened_at` as actual commencement for a Pending record.

### Imported versus manual landmarks

Current sitemap import uses `updateOrCreate` keyed by category/name and does not mass-delete landmarks, so manual landmarks can coexist. Keep this behaviour.

### Work-type deletion

Never hard-delete a work type already referenced by historical records. Retire it using `active=false`.

### Location deletion

Likewise, avoid hard-deleting locations referenced historically. Preserve location snapshots on entries and archive catalogue locations instead.

### PDFs

Recommended current rule is one PDF per location, max 25 MB. If operational use later requires multiple drawings/documents, introduce a separate `location_documents` table rather than overloading the single-PDF columns.

### Audit trail

Keep audit events for:

- scheduled;
- started;
- updated;
- closed;
- cancelled;
- reopened if that feature is introduced later.

### Map package version

Consider storing a separate map-package version/date in Settings. The application version and map-data version are different concepts and should eventually be visible separately.

---

## 22. Files/features currently central to V2 work

Key branch files include:

```text
VERSION
config/hwrt.php
docs/V2.md
scripts/update-hwrt.sh

database/migrations/2026_09_25_000002_hwrt_v2.php

app/Models/Setting.php
app/Models/User.php
app/Models/WorkType.php
app/Models/Entry.php

app/Http/Controllers/AuthController.php
app/Http/Controllers/SettingsController.php
app/Http/Controllers/UserController.php
app/Http/Controllers/WorkTypeController.php
app/Http/Controllers/ReportController.php
app/Http/Controllers/MapSettingsController.php
app/Http/Controllers/LocationSettingsController.php
app/Http/Controllers/LandmarkSettingsController.php
app/Http/Controllers/EntryController.php

app/Console/Commands/RefreshHwrtMap.php

resources/views/settings/index.blade.php
resources/views/settings/users.blade.php
resources/views/settings/work-types.blade.php
resources/views/settings/locations.blade.php
resources/views/settings/landmarks.blade.php
resources/views/reports/logbook.blade.php
resources/views/reports/index.blade.php
resources/views/entries/pending.blade.php
resources/views/entries/board.blade.php
resources/views/entries/create.blade.php
resources/views/map/index.blade.php

public/js/hwrt-map.js

tests/Feature/EntryFlowTest.php
tests/Feature/UserManagementTest.php
tests/Feature/ErrorLogTest.php
tests/Feature/HwrtV2Test.php
tests/Browser/map.spec.js
```

---

## 23. Resume instruction for the next session

Start with section 24 for the current branch/PR/CI state and remaining work. The original resume
sequence recorded when this note was created is superseded: CI, local frontend assets, atomic map
publication, and the production database choice have since been addressed and validated as noted
below. Do not deploy, merge, or release without the required human approval.

---

## 24. Current release assessment

**Repository-side implementation and CI are complete for this workstream; hosted acceptance and
release approval remain open.**

### Completed and verified

- Current branch: `gmednet-hwrt-v2-continuation`, based on `v2/hwrt`.
- Draft PR: [medrescuetech/cselog#8](https://github.com/medrescuetech/cselog/pull/8), targeting
  `v2/hwrt`.
- Current PR head: `53704fd2801f8ee957098bafadae5c7989b6e601`. The local branch was fetched from
  `origin` and confirmed even with its upstream; the worktree was clean at the time of this update.
- Latest CI run [36208574649](https://github.com/medrescuetech/cselog/actions/runs/36208574649)
  passed: MySQL 8.4 migration/feature tests, MariaDB 11.4 migration/feature tests, and SQLite-backed
  PHPUnit plus Chromium map acceptance.
- The database portability job rehearses a V1-like upgrade and verifies retention of an existing
  Admin account, HRW record/reference and `Other` work type, plus creation of the rotatable
  bootstrap Admin.
- The CI environment selects `APP_ENV` and `DB_CONNECTION` deterministically. MariaDB readiness
  uses its native health check.
- Local frontend assets/build and atomic map-package publication/rollback are implemented. The
  earlier single-engine CI failure was caused by the MariaDB container health check and is fixed
  in the passing run.
- Production database choice is MySQL (`DB_CONNECTION=mysql`), with MariaDB supported and covered
  by CI. Moving between engines requires a verified logical backup/restore; changing the setting
  alone does not migrate data. See `docs/V2.md`, section 9.

### Remaining tasks and release gates

1. **Human PR review and merge:** review PR #8 and merge it into `v2/hwrt` only after approval.
   Keep it draft until maintainers agree the hosted acceptance gates below are ready.
2. **Authorized database rehearsal:** obtain a protected backup/copy of the current cPanel review
   database and rehearse migration/restore into the selected MySQL target. Verify records and
   accounts, retain the original unchanged, and record the outcome. The synthetic CI fixture is
   not a substitute for this data-specific rehearsal.
3. **cPanel operational acceptance:** with authorized access and a human observer, validate the
   deployment/update procedure, cron scheduler, map refresh success/failure behavior, and the
   manual acceptance checklist in section 20 on `dev.akgmed.org`. No host or database changes
   have been made by this work.
4. **Release decision:** resolve any findings from the database rehearsal and dev acceptance,
   obtain release approval, then update `VERSION` and create `v2.0.0` only after acceptance. Do not
   deploy, merge, tag, or release as part of this repository-side work without that authorization.

The local environment does not provide PHP/Composer or a database server; PHP/database validation
was performed in GitHub Actions. No cPanel deployment, host-state change, merge, tag, or release
was performed.

This status supersedes the open-item assessment and resume sequence originally recorded on
**2026-09-25 15:34 AWST**.
