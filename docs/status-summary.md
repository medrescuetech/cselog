# Current Project Status & Pre-Rollout Summary

**Project:** CSEM — Confined Space Entry Monitor
**Target Site:** Perdaman Urea Project (PR25), Burrup Peninsula WA
**Date:** September 2026

---

## 1. System Overview & Architecture

- **Stack:** PHP 8.3 / Laravel 13, SQLite / MySQL, Blade + Alpine.js + Tailwind CSS (vendored locally under `public/vendor/`).
- **Map System:** Leaflet JS using `L.CRS.Simple` mapped directly to **MGA Zone 50 (EPSG:28350)** coordinates (`easting, northing` in metres).
- **Standalone GIS Package:** Reusable `sitemap/` directory containing site imagery, transparent plot plans, boundaries GeoJSON, and automated Python synchronization scripts (`sitemap/tools/`).
- **Role-Based Access Control (RBAC):** 5-tier access model implemented via `User` roles:
  1. `viewer`: Read-only board & map access.
  2. `map_only`: Read-only map access.
  3. `logger`: Create/close entries and add new locations.
  4. `supervisor`: Manage locations, view history, manage client error logs.
  5. `admin`: Full system administration (users, work types, system options).

---

## 2. Completed Capabilities (Phases 0–2)

1. **Authentication & User Management:** Hand-rolled session auth (`AuthController`), admin management of user accounts and roles (`UserController`).
2. **Quick Entry Logging:** Sub-10-second entry logging (`EntryController`) with auto-timestamping, work type picker, notes, permit numbers, and crew details.
3. **Location Catalogue & Ad-Hoc Pins:** Search-as-you-type catalogue picker, ad-hoc pin drops on map with point-in-polygon area snapshot calculation (`Geo` support utility).
4. **Open Board & Close-Out:** Live open board with elapsed duration tracking, age-based color highlighting (amber/red), and one-click entry close-out with notes.
5. **Interactive Map:** Live Leaflet map (`public/js/csem-map.js`) rendering dual layers (drone/satellite imagery and transparent Saipem plot plans), GeoJSON area boundaries, infrastructure landmarks, and live open entry pins auto-refreshing via polling.
6. **History & Reporting:** Searchable history log with date/type/location filters and CSV export.
7. **Client Error Logging:** Dedicated client error reporting endpoint (`ErrorLogController`) and supervisor log viewer.

---

## 3. Test Suite & Verification Metrics

- **PHPUnit Test Suite:** 19 tests passing across 106 assertions (`php artisan test`).
  - Unit Tests: `GeoTest` (point-in-polygon, hole subtraction, centroid calculation).
  - Feature Tests: `EntryFlowTest` (auth checks, ad-hoc pin drop, catalogue immutability, area snapshots, nearby location warnings, history CSV, layer manifest), `ErrorLogTest` (client log dispatching, supervisor download, admin clear), `UserManagementTest` (role protection, account creation/deactivation safeguards).
- **Routes Inventory:** 28 registered routes across web UI and JSON API endpoints.
- **Audit Tool:** Verified clean via `/home/jules/self_created_tools/audit_tool.py`.

---

## 4. Outline of Steps Before Next Version Rollout

### Phase 3 — Overlay Editor & Catalogue Hygiene
- **Catalogue Management:** Implement location merge (`POST /api/locations/{location}/merge`), archiving, alias management, and review interface for unverified pins within 5 m radius.
- **Overlay Drawing Tooling:** Add Leaflet-Geoman / Leaflet.draw integration to `resources/views/map/index.blade.php` and `app/Http/Controllers/MapController.php` allowing supervisors/admins to draw/edit area polygons (`POST /api/areas`) and landmark markers (`POST /api/landmarks`).

### Phase 4 — Map Automation & Georeferencing Tooling
- **Automated Refresh:** Finalize `sitemap/tools/fetch_arcgis.py` background refresh pipeline and affine registration (`sitemap/tools/georef_print.py`).
- **Coordinate Conversion:** Expose MGA50 (EPSG:28350) to WGS84 (EPSG:4326) coordinate export utilities in `MapController.php`.

### Phase 5 — Wallboard View, Alerts & Shift Handover
- **Kiosk Wallboard:** Dedicated full-screen `/wallboard` auto-refreshing kiosk display (Viewer role) built on `resources/views/entries/board.blade.php`.
- **Overdue Notifications:** Scheduled task in `routes/console.php` running via minutely cron (`php artisan schedule:run`) to dispatch email/SMS alerts when open entries exceed amber/red thresholds.
- **Shift Handover Report:** Printable shift handover report summarizing open and closed entries over the preceding shift.

### Phase 6 — Production Deployment & Staging Setup
- **Environment Setup:** Configure `.env` on cPanel with production database credentials, app key, and admin credentials.
- **cPanel Cron & Deploy:** Enable minutely `ea-php83` cron job (`* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`) and run `.cpanel.yml` / `scripts/update-hwrt.sh` deployment script.
