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
- Repeated Log work form submissions with the same key return the original HRW record.
- Explicit **Start** action records actual commencement time/user.
- Configurable high risk work types, including **Other**, per-type Notes prompts and Notes-required rules.
- Admin-managed additional activity/hazard tags for jobs that span more than one category.
- Permanent identifiers such as `HRW-000001`.
- Live local site map with MGA Zone 50 coordinates and open-work pins.
- Configurable manual/automatic refresh of the existing local map package from public ArcGIS sources.
- Runtime map requests use local `/sitemap/...` files; HRW/user/job data is not sent to ArcGIS.
- Optional private PDF attachment for each catalogue location, linked from map pin details.
- Admin pre-storage, map/coordinate editing, archiving and audit history for saved locations; prior job snapshots remain intact.
- Duplicate-location merge and preview-first MGA50 CSV import, without rewriting historical job positions.
- Manual key-landmark management with map-click coordinate selection.
- Recent Logbook and filtered Reports with CSV export.
- Custom report builder with date meaning, location/type/area/tag filters, grouping and selected CSV columns; printable shift handover view.
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

## Production Prerequisites & System Requirements

1. **PHP**: PHP 8.3 with extensions `mbstring`, `pdo_sqlite` or `pdo_mysql`, `fileinfo`.
2. **cPanel Cron Scheduler**: Run Laravel's scheduler every minute in cPanel Cron Jobs:
   ```bash
   * * * * * /usr/local/bin/ea-php83 /home/USER/csem-review/artisan schedule:run >/dev/null 2>&1
   ```
3. **Python & Pillow (for background map refresh)**:
   - Python 3.8+ (`python3`).
   - `Pillow` image library installed (`pip install Pillow` or system package `python3-pillow`).
   - Binary path configurable in **Settings → Map** (`map.python` setting).
4. **Network Access**: Outbound HTTPS (port 443) to `https://enveng.maps.arcgis.com` and `https://services-ap1.arcgis.com` for background map updates. No outbound traffic is required for normal application/user operation.

## Deployment Options

### Option 1: SSH Automated Update (Recommended)

When SSH access is available:

```bash
cd ~/csem-review
./scripts/update-hwrt.sh            # Update to latest on current branch
./scripts/update-hwrt.sh v2.0.1     # Update to specific release tag
```

The `update-hwrt.sh` helper automatically:
1. Backs up SQLite database (if used);
2. Seeds and maintains the decoupled runtime map package in `storage/app/hwrt-sitemap`;
3. Sets Laravel to maintenance mode (`artisan down`);
4. Fetches Git changes and checks out the requested release/branch;
5. Installs Composer dependencies (`composer install --no-dev --optimize-autoloader`);
6. Runs database migrations (`php artisan migrate --force`);
7. Bootstraps rotatable admin credentials (`php artisan hwrt:bootstrap-admin`);
8. Imports local runtime map data (`php artisan sitemap:import`);
9. Optimizes application caches (`php artisan optimize`);
10. Restores online status (`artisan up`).

### Option 2: cPanel Git Version Control (`.cpanel.yml`)

When using cPanel's Git interface without direct SSH shell access:
1. Push release commits to GitHub.
2. In cPanel → **Git Version Control**, trigger **Update from Remote**.
3. cPanel executes deployment tasks defined in `.cpanel.yml`:
   - Syncs code to the application directory;
   - Runs `composer install --no-dev --optimize-autoloader`;
   - Executes `php artisan migrate --force`;
   - Runs `php artisan optimize`.

### Option 3: Manual File Updating (FTP / File Manager)

If updating files manually via SFTP or cPanel File Manager:
1. Upload updated application files **without** overwriting `.env`, `database/database.sqlite`, or `storage/`.
2. Run database migrations: `php artisan migrate --force`
3. Re-import map data if modified: `php artisan sitemap:import`
4. Clear and optimize caches: `php artisan optimize`

### Node Assets & Frontend Changes

If frontend JavaScript or CSS assets are updated:
- Run asset compilation locally or in CI: `npm ci && npm run build`
- Node is **not** required on the cPanel production server. Compiled assets are served directly from `public/css/app.css` and local vendor directories (`public/vendor/leaflet/`, `public/vendor/alpinejs/`).

## Future Release & Rollout Strategy

1. **Version Tagging**: Every official release is tagged with semantic versioning (`v2.0.0`, `v2.0.1`, `v2.1.0`).
2. **Database Backups**: Always perform a database backup (`mysqldump` for MySQL/MariaDB or SQLite copy) before applying updates.
3. **Decoupled Sitemap**: Runtime map updates are stored under `storage/app/hwrt-sitemap/` with atomic symlink switching, keeping Git history clean.
4. **Staging Environment**: Validate updates on a staging subdomain (`staging.example.com`) before production deployment.
5. **Rollback Strategy**:
   - Code rollback: `git checkout <previous-tag>`
   - Database rollback: restore pre-update database backup.
   - Sitemap rollback: `MapPackagePublisher` automatically restores the previous map package if a refresh or import fails.

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
