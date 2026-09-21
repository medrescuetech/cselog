# 11 — Build plan (v1, with real map data)

Consolidates docs 01–10 into one executable plan now that the site, map data and constraints
are known. Where this conflicts with an earlier doc, this one wins.

## What we know (inputs locked)

| Item | Value |
|---|---|
| Site | Perdaman Urea Project (PR25), Burrup Peninsula WA. Work areas: Site F (process plant), Site C (construction/laydown), Port/MOF, Conveyor corridor, LA30/LA44. |
| Map data | **Tier 3.** Weekly drone imagery + boundaries/named-infrastructure polygons, all MGA Zone 50 (EPSG:28350), pulled into `docs/maps/` by `tools/arcgis/fetch_arcgis.py`. Plus the SCJV site map drawing (PDF). |
| App scope | Control-room log with a live map. Pins over a fixed site image. **No zoom-out beyond the site, no GPS navigation, no wayfinding.** |
| Hosting | cPanel shared hosting, deployed from GitHub. |
| Stack | PHP 8.2 / Laravel 11 / MySQL / Blade + Alpine + Tailwind / Leaflet. |

## Map design decision — two layers, one coordinate system

There are two visual "types": the **satellite/drone imagery** and the **drawn site plan**. They
are not two maps; they are two layers on one map and the user toggles between them (or blends
with an opacity slider). Everything below follows from that.

### Coordinate system: MGA50 metres, on `L.CRS.Simple`

We do **not** use Web Mercator / lat-lng in the browser. Because the app never zooms out past
the site and never talks to GPS, the simplest correct choice is:

- Leaflet `L.CRS.Simple`, where a map unit = **1 metre of MGA50 easting/northing**
  (`lat` = northing, `lng` = easting).
- Every raster layer is placed with `L.imageOverlay(url, bounds)` where `bounds` come straight
  from its world file (`.jgw`) / `.json` sidecar — no control-point fitting for imagery.
- Pins, landmarks and area polygons are stored as **`easting, northing` (EPSG:28350) doubles**.
  A pin dropped on the imagery lands on the same real-world spot on the drawn plan, and survives
  swapping the imagery for next week's capture.
- `lat/lng` (EPSG:4326) is derived on demand for export only (a fixed Transverse Mercator
  formula — 30 lines of PHP, or `proj4php`). Nothing in the UI needs it.

This keeps the whole system in one flat, metric, north-up coordinate space with no tile maths
and no projection edge cases, while still being real-world referenced.

### Layers

| Layer | Source | Format in repo | How served |
|---|---|---|---|
| Imagery — Site C&F (current) | `2026SeptWk2SiteCF` etc., L17 = 0.25 m/px, 4864×7424 | `docs/maps/imagery/*.jpg` + `.jgw` | Tiled once at upload (`vips dzsave` → `public/maps/<version>/tiles`), because a 10 MB / 36 Mpx JPEG is too heavy as a single overlay on a tablet. |
| Imagery — whole site (context) | Nearmap basemap, L15 = 1 m/px, 6400×5632 | same | Same. Used for Port/Conveyor/LA44 and as the "where am I" backdrop. |
| Drawn site plan | SCJV drawing PDF **or, preferably, an ArcGIS print with imagery off** (plot plan + labels only) | PDF → PNG at 200 dpi (`pdftoppm`) | Same tiling. Georeferenced **once** via 3–4 control points against the imagery (admin screen, RMS readout) — *unless* GMC exports it from ArcGIS, in which case the export extent is the georeference and no fitting is needed. |
| Boundaries / areas | `docs/maps/features/*.geojson` | GeoJSON (EPSG:4326) → reprojected to MGA50 at import | `L.polygon` from the `/api/areas` endpoint; toggleable. |
| Landmarks | `project-boundaries-infrastructure.geojson` centroids (142 named structures) | import → `landmarks` table | `L.marker` with label; toggleable. |
| Live pins | `entries` where `closed_at is null` | — | Polled every 15 s from `/api/open`. |

Imagery refresh is a re-run of `fetch_arcgis.py imagery … 17` + upload as a new `map_version`;
pins don't move.

### Answer to "do you want the visual layer in a different format?"

No. JPEG + world file (already in the repo) is the right input for the imagery. For the drawn
layer the ideal is a **PNG/PDF exported from ArcGIS with only the plot-plan/label layers on**
(the Print widget, A3/A2 landscape, 200–300 dpi, imagery unticked) plus a note of the print
extent — that gives a drawn layer that is pixel-aligned with the imagery for free.

## Data model deltas (vs `03-data-model.md`)

- `map_versions`: add `crs` (`'EPSG:28350'`), `xmin, ymin, xmax, ymax` (metres),
  `width_px, height_px`, `tile_url`, `kind` (`imagery | plan`), `captured_on`, `source_url`.
- `locations`, `entries`, `landmarks`: store `easting`, `northing` (DECIMAL(10,3)). Drop the
  pixel `x,y` columns from the plan — pixel coords are a pure function of the version's extent.
- `areas.geometry`: polygon as JSON array of `[easting, northing]` rings (MySQL spatial types
  are optional; point-in-polygon for 20–150 polygons is trivial in PHP).
- `locations.area_id` is computed on save (point-in-polygon) and re-computed if areas change.

## Phases

Effort is in Devin sessions. Each phase ends deployed to the cPanel staging subdomain.

### Phase 0 — Skeleton and pipeline (~0.5 session)
- Laravel 11 + Breeze (session auth), Tailwind, Alpine, Leaflet via npm, Pest tests.
- `.cpanel.yml` deploy (per `08-deployment-cpanel.md`); GitHub Action builds `vendor/` and
  assets into a `deploy` branch if the host has no SSH/Composer (question 17).
- Roles: Logger, Supervisor, Admin, Viewer. Seed one admin.
- Health page at `/up`. Proven end-to-end before any feature work.
- **Exit:** hello-world with login live on staging, deployed by a `git push`.

### Phase 1 — Log, board, close, history (~1 session)
- `work_types` (seed: Confined Space Entry default, Hot Work, Working at Heights, Excavation,
  Isolation, Other).
- Quick log form (S1): time = now (editable + reason), work type, location picker
  (search-as-you-type, MRU-ordered), notes, permit no., crew. Submit in < 10 s.
- Open board (S4) with elapsed time, amber/red thresholds (config), one-click close with note.
- History (S5): filters, CSV export, printable shift report.
- Audit log on every create/update/close.
- **Exit:** fully usable with no map at all.

### Phase 2 — Map with two layers + pin drop (~1 session)
- Map screen (S3) on `L.CRS.Simple` in MGA50 metres; layer control: Imagery (current),
  Imagery (whole site), Site plan, with opacity slider on the plan.
- Upload/tiling pipeline for map versions (`vips dzsave`, or pre-tiled in CI and committed to
  `public/maps` if the host lacks vips).
- Import the four GeoJSON files as `areas` (+ 142 landmarks from infrastructure polygons).
- New-location flow (S2): drop pin → "Save this location?" → name / ad-hoc.
- Open pins colour-coded by type, age ring, click → details + close.
- **Exit:** pin dropped on imagery appears in the right spot on the drawn plan and vice versa.

### Phase 3 — Overlay editor + catalogue hygiene (~0.5–1 session)
- Draw/edit areas and landmarks (Leaflet.draw / Geoman), rename, style, archive.
- Location lifecycle from `06`: unverified flag, merge, aliases, move-with-versioning,
  review list (unverified / unused / near-duplicate within 5 m).
- Bulk CSV import/export of locations.

### Phase 4 — Plan georeference tooling + imagery refresh (~0.5 session)
- Admin screen: side-by-side imagery vs. uploaded plan, click 3–4 matching features, affine fit,
  RMS in metres, save as the plan layer's extent. Skipped if the plan comes as an ArcGIS export.
- "Refresh imagery" admin action: paste a tile-service URL → server runs the fetch/tiling →
  new `map_version`, old one retained for history.
- MGA50 → WGS84 conversion for KML/GeoJSON export.

### Phase 5 — Wallboard, alerts, reports, resilience (~1 session)
- `/wallboard` full-screen auto-refresh (Viewer role, kiosk token).
- Overdue alerts (scheduler → email; SMS optional) at configurable thresholds.
- Shift handover report; supervisor bulk-close with reason.
- Offline-tolerant log form (queue submit in localStorage, retry).
- Nightly DB + maps backup, off-host copy.

### Phase 6 — Options (unscheduled)
- QR codes at fixed confined spaces (`locations.code`) for scan-to-open/close.
- Link to the site's Ground Disturbance Permit layer (`PR25_GDP_Master`) by permit number.
- Live pull of the Saipem plot-plan FeatureServer as a vector overlay via `esri-leaflet`.

## Testing and acceptance

- Pest feature tests per phase (auth, log/close, catalogue rules, point-in-polygon, CSV).
- One browser check per phase on staging: log an entry on a phone-sized viewport, see it on the
  wallboard within 15 s, close it.
- Map acceptance: drop a pin on the Control Room in imagery → pin sits on the Control Room
  outline in the plan layer and on the `CONTROL ROOM` polygon from the GeoJSON (±2 m).

## Still needed from GMC

1. cPanel details: SSH yes/no, PHP version, MySQL, cron, staging subdomain (Q17–20 in `07`).
2. Preferred drawn layer: ArcGIS print with imagery off (best) or the SCJV PDF as-is.
3. Confirm login is per person, and who the first admin is.
4. Alert thresholds (default 2 h amber / 4 h red) and who receives overdue emails.
5. Product name (CSEM stays unless told otherwise).

## Immediate next steps

1. Phase 0 on a `feature/phase-0-skeleton` branch → PR → deploy to staging.
2. In parallel, rasterise the SCJV PDF and prototype the two-layer map statically
   (`docs/maps` + Leaflet, no backend) to validate the MGA50-on-CRS.Simple approach early.
