# 02 — Architecture

> **As built:** the Laravel/Blade/Tailwind stack described below was replaced during
> implementation with plain PHP 8.1+ and PDO (`src/Router.php`, `src/Db.php`, `views/*.php`) and
> a vendored Leaflet, so the deploy has no Composer, no `vendor/` and no build step. Everything
> else here — the constraints, the component split, the polling model, the map approach — still
> describes the running app.

## Constraints driving the design

1. **cPanel shared hosting.** No root, no long-running daemons you can trust, PHP is the
   first-class citizen, MySQL/MariaDB is the database, cron is the scheduler.
2. **Deploy from GitHub.** cPanel's *Git Version Control* can clone a repo and run a
   `.cpanel.yml` deployment on push — that is the pipeline.
3. **Small user base, low write volume, high read/refresh.** Dozens of entries a day, a handful
   of concurrent users, one wallboard polling constantly.

## Chosen stack

```
Browser (phone / control-room screen)
  Blade-rendered pages + Alpine.js for interactivity
  Leaflet 1.9 for the map, Leaflet-Geoman for drawing
        │  HTTPS, JSON for the live bits
        ▼
cPanel host
  Apache + PHP 8.2 (docroot → /public)
  Laravel 11
    ├─ Web routes (forms, pages)
    ├─ /api/* JSON (open entries, pins, layers) — polled by the map
    ├─ Auth (Breeze, session-based)
    └─ Console scheduler via cPanel cron (backups, overdue alerts, digest emails)
  MySQL / MariaDB
  Storage: map images + generated tiles on disk (public/maps/...), served statically
```

### Why PHP/Laravel rather than Node/Next

Node on cPanel means Passenger-managed apps: they die on PHP/stack upgrades, are painful to
restart from a deploy hook, and shared hosts throttle long-running processes. PHP is what the
hosting is actually built for. Laravel gives migrations, validation, auth, an ORM, CSV export
and a scheduler out of the box, so the bespoke work is only the map and the location catalogue.

### Why Leaflet

The requirement spans "a picture with pins on it" *and* "a satellite overlay". Leaflet does both
with the same API: `L.CRS.Simple` for a pure image, standard Web Mercator with a tile basemap
plus a georeferenced `ImageOverlay`/tile layer for the real-world case. Switching between them
is a configuration change per map, not a rewrite. It's ~40 KB, no build step required, and has
mature plugins for drawing (Geoman), marker clustering and fullscreen.

### Why not

| Option | Why not |
|---|---|
| Google Maps JS API | Custom image as the primary base is awkward; billing/API key management; overkill. |
| Mapbox GL | Great, but vector-first, heavier on old site tablets, and paid above modest usage. |
| OpenLayers | Very capable (native GeoTIFF!) but a steeper API for the small amount of GIS here. Reconsider if real GIS layers (shapefiles, WMS) become central. |
| SharePoint / Power Apps | Per-seat licensing, custom image + pin-save flow is fighting the tool. |
| Airtable / Google Forms + Sheets | No live custom map, no overlay editing, poor offline/audit story. |
| PostGIS | Not available on standard cPanel. Not needed — polygons are small and stored as GeoJSON; point-in-polygon runs in PHP or in MySQL spatial functions. |

## Components

| Component | Responsibility |
|---|---|
| `EntryController` | Create/close/edit entries; the 30-second log form. |
| `LocationController` | Search, create-from-pin, rename, archive, merge, import/export. |
| `MapController` | Serve map metadata, image/tile URLs, georeference, layer GeoJSON. |
| `OverlayController` | CRUD for landmarks and areas (GeoJSON in, GeoJSON out). |
| `WorkTypeController` | Admin-managed picklist. |
| `ReportController` | History filters, CSV export, shift report. |
| `Geo` service | Pixel↔lat/lng transform from control points; point-in-polygon for area labelling. |
| `TileBuilder` job | Optional: slice an uploaded large image into tiles (see `04-...`). |
| `AuditLogger` | Writes `entry_events`; all mutations pass through it. |

## API surface (polled by the map / wallboard)

```
GET  /api/maps/{map}                → image or tile template, bounds, CRS, georeference
GET  /api/maps/{map}/overlays       → { landmarks: GeoJSON, areas: GeoJSON }
GET  /api/entries/open?map=         → open entries with resolved pin coords + age
POST /api/entries                   → create (location_id | pin)
POST /api/entries/{id}/close
GET  /api/locations/search?q=       → type-ahead, recent/frequent first
POST /api/locations                 → promote a dropped pin to the catalogue
```

Responses are ETag'd; the wallboard polls every 10–20 s and usually gets a 304.

## Security

- Session auth with CSRF on all forms; role middleware on admin routes.
- Enforce HTTPS + HSTS (cPanel AutoSSL), secure/HttpOnly cookies.
- Uploaded map images validated by type and size, stored outside the webroot and published
  through a controlled path; tiles are static.
- `.env` never in git — created once on the host (see `08-deployment-cpanel.md`).
- Rate-limit the write endpoints; log every mutation with actor and IP.

## Operations

- Nightly `mysqldump` + `tar` of map storage via cPanel cron, copied off-host (rclone/S3 or the
  host's own backup product).
- Laravel scheduler on a single cron entry (`* * * * * php artisan schedule:run`) drives overdue
  alerts and the nightly backup command.
- Error tracking: Laravel log + optional Sentry free tier.
