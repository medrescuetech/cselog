# CSEM — Confined Space Entry Monitor

A web-based log and live map for tracking open work locations on a site (primarily confined
space entries) that are called in by radio.

**Status:** working application (phases 0–3 + 5 partial) running against a **placeholder site
map** that is swapped for the real image in Admin → Maps. Georeferencing and a satellite basemap
(phase 4) remain optional future work. See [Running it](#running-it) below.

---

## 1. The problem

People radio in to say a confined space (or other work) is open at a particular place on site.
Today that is tracked informally. What's needed:

- Log the call in a few seconds: time (auto), location, work type, notes.
- Know at a glance what is **currently open** and for how long.
- See those open jobs as **pins on our own site map** (survey/GIS image), optionally with a
  satellite layer underneath.
- Keep a **historical record** for compliance/review.
- Build up a **reusable location catalogue** so the common places are one tap next time — while
  accepting that locations change constantly, new ones appear, old ones disappear.
- Draw **landmarks** and **boundaries/areas** on the map image as reference overlays.

## 2. The core workflow

```
Radio call
   │
   ▼
[Quick log form]  time = now (editable)   work type = Confined Space Entry (default, list editable)
   │              location = search/select existing  ── or ── "New location…"
   │              notes = free text
   ▼
Submit
   │
   ├── existing location selected ──────────────► entry is open, pin appears on map
   │
   └── new location ──► [Map view] user drops a pin on the site image
                              │
                              ▼
                     "Save this location for next time?"
                              │
                    ┌─────────┴─────────┐
                   Yes                  No
                    │                    │
          name it + save to         pin stored on this
          the catalogue             entry only (ad-hoc)
                    │                    │
                    └─────────┬──────────┘
                              ▼
                      Open board + live map
                              │
                        (work finishes)
                              ▼
                      Close entry → time closed, closed by, optional notes
                              ▼
                           History
```

## 3. What it does (feature set)

### Logging
- One-screen entry form; timestamp captured server-side on submit (editable with an audit note
  if the call was logged late).
- Work type from a **customisable list** (default: *Confined Space Entry*; others e.g. Hot Work,
  Working at Heights, Excavation, Isolation, Other + free text).
- Notes, caller/permit number, crew/contractor (optional fields — configurable).
- Location by search-as-you-type over the catalogue, ordered by most-recently/most-frequently
  used, or a brand-new pin.

### Open board
- Live list of open locations: place name, type, opened at, **elapsed time**, notes, who logged it.
- Configurable duration thresholds → amber/red highlight for long-running entries (e.g. >2 h,
  >4 h) so nothing is forgotten on the board.
- One-click close, with confirmation and optional close note.

### Live map
- Our own image as the base map (survey plan / GIS export / PDF-derived raster).
- Open entries as pins, colour-coded by work type, red/amber by age. Click a pin → details +
  close button.
- Optional satellite/aerial basemap under the image when the image is georeferenced, with an
  opacity slider.
- Landmark markers and boundary/area polygons drawn as overlays (see §5).
- Auto-refresh (polling) so a wall-mounted screen stays current.

### History
- Filter by date range, site, area, location, work type, status, person.
- CSV/Excel export; printable shift report.
- Every create/update/close is recorded in an immutable audit log.

### Location catalogue (built to churn)
- Locations are created on the fly from a dropped pin and promoted to the catalogue on "Yes".
- **Archive, don't delete.** Archived locations stay attached to their history but drop out of
  the picker. A "merge into…" action folds duplicates together and keeps an alias.
- Aliases: "Pit 4" = "No. 4 Sump" — both find the same location.
- New locations are flagged *unverified* until an admin confirms them; a nightly/weekly review
  list shows unverified, unused and duplicate-looking entries.
- Locations can be moved (pin corrected) — the change is versioned so old entries keep the
  position they were logged at.
- Bulk import/export (CSV) for when a new survey drops.

### Admin
- Sites, map images/versions, georeferencing control points.
- Work types (add/rename/reorder/retire).
- Landmarks and areas editor (draw, name, style).
- Users and roles: *Logger* (create/close entries), *Supervisor* (edit history, manage
  locations), *Admin* (everything), *Viewer* (read-only wallboard).

## 4. Recommended stack

Chosen to match the constraint: **cPanel shared hosting, deployed from GitHub**.

| Layer | Choice | Why |
|---|---|---|
| Runtime | PHP 8.2+ | The one thing cPanel always runs well; no Node daemon/Passenger fragility. |
| Framework | **None — plain PHP + PDO** (small router / controller / view layer in `src/`) | Chosen over Laravel once the app was built: no Composer, no `vendor/`, no build step, so a cPanel deploy is a file copy. Laravel remains a clean migration path if the scope grows. |
| DB | MySQL 8 / MariaDB 10.6+ | Included with cPanel. Spatial types available if wanted; not required. |
| UI | PHP templates + hand-written CSS/JS, no build step | Nothing to compile; fast on a phone over patchy site wifi/4G. |
| Map | **Leaflet 1.9** | Handles both a plain image (`L.CRS.Simple` + `ImageOverlay`) and real geographic layers (satellite tiles + georeferenced overlay) — the same library covers both phases. |
| Drawing | Leaflet-Geoman (or Leaflet.draw) | Landmarks, boundary polygons, freehand areas. |
| Big images | `gdal2tiles` / `vips dzsave` pre-tiling, served as static tiles | A 200 MB survey raster can't be a single `ImageOverlay`; tiles keep mobile usable. |
| Live updates | Polling (10–20 s) + ETag | Websockets are unreliable on shared cPanel; polling is enough for this volume. |

**Deliberately not chosen:** Next.js/Node on cPanel (Passenger apps break on shared hosts and on
PHP-version changes), a no-code platform (can't do the custom image map + pin save flow), and
PostGIS (not available on standard cPanel).

## Running it

Local (SQLite, no services to install):

```bash
php bin/install.php --demo --admin-pass='devpass123'   # writes .env, schema, seed data
php -S 127.0.0.1:8000 -t public public/index.php
```

Then open <http://127.0.0.1:8000> and log in as `admin`. `--demo` seeds locations, areas,
landmarks and a handful of open/closed entries; drop it for a clean install.

Production on cPanel is the same installer against MySQL — see
[`docs/08-deployment-cpanel.md`](docs/08-deployment-cpanel.md) and `.cpanel.yml`.

**Swapping in the real map:** Admin → Maps → upload any image (survey plan, GIS export,
satellite screenshot, phone photo of the plan on the wall) → make it the default, then draw the
boundaries and landmarks in Admin → Landmarks & boundaries. Pins are stored in the image's own
pixel space, so no georeferencing is needed.

See [`docs/02-architecture.md`](docs/02-architecture.md) for detail and
[`docs/08-deployment-cpanel.md`](docs/08-deployment-cpanel.md) for the cPanel + GitHub deploy
pipeline (`.cpanel.yml`, Git Version Control, cron, `.env` handling).

## 5. Maps, pins and georeferencing (the interesting bit)

There are two ways to run the map, and the data model supports both at once:

1. **Image-only (Phase 1).** `L.CRS.Simple`. A pin is stored as `(x, y)` in image pixel space.
   Works with any picture — a scanned plan, a screenshot, a PDF export. No GIS data needed.
2. **Georeferenced (Phase 2).** The map image is pinned to the real world with 2+ control points
   (or supplied as a GeoTIFF / known bounds). Leaflet then shows a satellite/aerial basemap under
   a semi-transparent overlay of your plan, and a pin has a real `(lat, lng)`.

Every pin is stored with `x, y` **and**, when a georeference exists, `lat, lng` derived from it.
That means: start today with a plain image, add the georeference later, and history retro-fits
instead of being stranded in pixel coordinates.

Landmarks (points) and boundaries/areas (polygons) are stored as GeoJSON in the same coordinate
space as the map they belong to, so they can be toggled as layers, used to label a pin ("this pin
is inside *Tank Farm B*"), and reused when the base image is replaced with a newer survey.

Full detail: [`docs/04-map-and-georeferencing.md`](docs/04-map-and-georeferencing.md).

**Limited mapping data is not a blocker.** GMC has flagged that little map data exists — see
[`docs/09-minimum-mapping-data.md`](docs/09-minimum-mapping-data.md) for the tiered plan. In
short: a photo of a plan on the wall, or even satellite imagery with hand-drawn boundaries, is
enough to run the whole thing; the site map is then built up by drawing areas/landmarks and by
the location catalogue accumulating from normal use.

## 6. Data model (sketch)

```
sites ──< maps ──< map_versions (image/tiles, georeference control points)
  │        │
  │        ├──< landmarks   (point geojson, name, icon)
  │        └──< areas       (polygon geojson, name, colour)  ── boundaries
  │
  ├──< locations ──< location_positions (versioned x/y + lat/lng, effective_from)
  │        │          aliases, status(active|archived), verified, merged_into_id, usage_count
  │        │
  └──< entries  (location_id nullable + ad-hoc x/y, work_type_id, notes, status,
        │        opened_at, opened_by, closed_at, closed_by)
        └──< entry_events (audit: created/updated/closed, actor, payload, at)

work_types (name, is_default, sort_order, active)
users / roles
```

Full DDL sketch: [`docs/03-data-model.md`](docs/03-data-model.md).

## 7. Delivery plan

| Phase | Scope | Status |
|---|---|---|
| 0 | Repo, cPanel↔GitHub deploy, app skeleton, auth + roles | done |
| 1 | Log form, work types, open board, close-out, history + CSV | done |
| 2 | Image map (CRS.Simple), pin drop, "save this location?", catalogue picker | done |
| 3 | Landmarks + boundaries editor, layer toggles, pin-in-area labelling | done |
| 4 | Georeferencing + satellite basemap, tiled large images *(optional — see docs/09)* | not started |
| 5 | Overdue escalation (amber/red), shift report | done; email/SMS alerts + offline form not started |

("Session" = one continuous Devin working session, not a person-week.)

## 8. Open questions

Answers to these change the build; see [`docs/07-open-questions.md`](docs/07-open-questions.md)
for the full list. The ones that matter most:

1. How many sites, and roughly how many open entries at once / per day?
2. What map data actually exists — image only, PDF, shapefile/DWG, GeoTIFF, an ArcGIS/QGIS export?
   (GMC: limited — the plan for that is `docs/09-minimum-mapping-data.md`.)
3. Who logs the call: one controller at a desk, or multiple people on phones in the field?
4. Does this need to feed or replace an existing permit-to-work system?
5. Any regulatory retention/audit requirement on the records (and for how long)?
6. Is login per person (accountability) or a shared control-room account?

## Repo layout

```
README.md                      ← this document
bin/install.php                installer: schema, seed data, admin user, --demo
schema/schema.sql              portable DDL (SQLite + MySQL via placeholders)
src/                           Config, Db, Router, Auth, Http, Support, Repo, Controllers/
views/                         PHP templates (board, log, pin, map, history, admin/…)
public/                        docroot: index.php front controller, .htaccess, assets/, maps/
public/maps/placeholder-site.svg  stand-in site plan until the real image is uploaded
.cpanel.yml                    cPanel Git Version Control deployment tasks
docs/01-requirements.md        requirements captured from the brief, with assumptions marked
docs/02-architecture.md        stack, components, alternatives considered
docs/03-data-model.md          tables, DDL sketch, key design decisions
docs/04-map-and-georeferencing.md   image maps, tiling, control points, overlays
docs/05-workflows-and-ui.md    screen-by-screen flows and wireframe notes
docs/06-location-lifecycle.md  how the changing location catalogue is kept sane
docs/07-open-questions.md      what we need from GMC to finalise
docs/08-deployment-cpanel.md   cPanel + GitHub deploy pipeline
docs/09-minimum-mapping-data.md how to build this with little or no map data
docs/NOTES.md                  running notes, ideas, things deliberately excluded
```
