# HWRT — High Risk Work Tracker

HWRT is a web application for logging, scheduling, monitoring and reporting high risk work on site.

**Current development line:** V2  
**Version source:** `VERSION`  
**Authoritative V2 specification:** [docs/V2.md](docs/V2.md)

## Current V2 capabilities

- User/Admin authentication with username sign-in and optional email.
- Open Board with explicit headers, elapsed-time highlighting and HRW references.
- Advance scheduling as **Pending** with planned start times in **Australia/Perth**.
- Pending work appears in a dedicated list and on the Board on its planned Perth calendar day.
- Explicit **Start** action records actual commencement time/user.
- Configurable high risk work types, including **Other**, per-type Notes prompts and Notes-required rules.
- Permanent identifiers such as `HRW-000001`.
- Live local site map with MGA Zone 50 coordinates and open-work pins.
- Configurable manual/automatic refresh of the existing local map package from public ArcGIS sources.
- Runtime map requests use local `/sitemap/...` files; HRW/user/job data is not sent to ArcGIS.
- Optional private PDF attachment for each catalogue location, linked from map pin details.
- Manual key-landmark management with map-click coordinate selection.
- Recent Logbook and filtered Reports with CSV export.
- Dark/light appearance setting.
- Local diagnostics at `/error`.
- Application version displayed in the bottom-left of the authenticated UI.
- Git-based update helper with SQLite backup and a runtime map copy outside the Git working tree.

## Stack

- PHP 8.3
- Laravel 13
- SQLite for development/review
- MariaDB/MySQL recommended for production multi-user use
- Blade + Alpine
- Tailwind
- Leaflet using `L.CRS.Simple`
- Site coordinates: GDA94 / MGA Zone 50 (EPSG:28350)

## Local development

```bash
composer install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate:fresh --seed
php artisan sitemap:import
php artisan serve
```

Default development account from `.env.example`:

```text
username: admin
password: changeme
```

Change this before any real deployment.

## cPanel / SSH review deployment

The application document root must point at the Laravel `public/` directory.

For the current review installation used during development:

```text
/home/akgmxkpo/csem-review/public
```

V2 production/review should use a runtime map directory outside the Git-tracked package:

```text
storage/app/hwrt-sitemap
```

with:

```text
public/sitemap -> ../storage/app/hwrt-sitemap
```

This prevents automatic map refreshes from dirtying the Git working tree.

## Updating from Git

Use:

```bash
cd ~/csem-review
./scripts/update-hwrt.sh
```

or update to a specific release/tag:

```bash
./scripts/update-hwrt.sh v2.0.1
```

The helper:

1. backs up SQLite when present;
2. seeds/maintains the runtime map copy;
3. puts Laravel into maintenance mode;
4. fetches Git changes/tags;
5. installs Composer dependencies;
6. runs migrations;
7. imports the runtime sitemap;
8. optimises Laravel;
9. restores the application.

For MariaDB/MySQL deployments, use the normal cPanel/database backup process before major upgrades.

## Map refresh

The installed map remains local during normal HWRT operation.

Admins can use **Settings → Map** to select:

- Manual only
- Daily
- Weekly
- Monthly

or request a refresh immediately.

The scheduler checks:

```bash
php artisan hwrt:map-refresh --scheduled
```

The production cron should run Laravel's scheduler every minute:

```bash
cd /home/akgmxkpo/csem-review && /usr/local/bin/php artisan schedule:run >/dev/null 2>&1
```

The refresh process:

- performs anonymous download-only requests to the configured public ArcGIS sources;
- uses the current installed Site C/F footprint as the bounding box;
- stages downloads before replacement;
- leaves the current map live if refresh fails;
- refreshes imagery and feature data;
- retains current engineering-plan overlays unless those are explicitly updated through Git;
- re-imports local areas/landmarks;
- records last attempt/success/error in Settings.

It does **not** upload HRW records, users, notes, PDFs, permits or application database content to ArcGIS.

## Documents

Location PDFs are stored in private Laravel storage, not under the public web root. Authenticated users access them through a protected application route.

## Testing

```bash
php artisan test
npm install
npx playwright install chromium
npm run test:browser
```

Browser acceptance verifies that the real site imagery visibly renders and the Control Room coordinate remains aligned between imagery and plan layers.

## V2 design

See [docs/V2.md](docs/V2.md) for the full V2 scope, database plan, settings design, map/privacy approach, reporting model and release criteria.
