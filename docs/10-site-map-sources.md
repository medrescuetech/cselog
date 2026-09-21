# 10 — Site map sources (received)

Supersedes the "limited mapping data" assumption in `09-minimum-mapping-data.md`. The site is
the **Perdaman Urea Project (PR25), Burrup Peninsula, WA** (SCJV = the construction JV), and
two real map sources now exist.

## 1. SCJV site map PDF

`docs/maps/scjv-site-map-2026-08-21_0000-GB-A-60002-3.pdf` (drawing no. 0000-GB-A-60002 rev 3,
dated 21 Aug 2026, ~30 MB).

- This is the **Tier 1 / Tier 2** asset from doc 09: rasterise once
  (`pdftoppm -r 200 -png <pdf> plan`) and use it as the `L.CRS.Simple` base image for Phase 2.
- It is a formal drawing, so it is north-up and to scale — a 3–4 point affine fit against the
  ArcGIS imagery below should georeference it to within a few metres (Tier 2).
- Keep the PDF in git as the source of record; the rasterised PNG/tiles stay ignored
  (`/maps`, `/tiles` in `.gitignore`) and are generated at deploy time.

## 2. ArcGIS Online web map — "PR25 - Perdaman Urea Project"

Public Web AppBuilder app (owner `eva.tan`, org `enveng.maps.arcgis.com`):
https://enveng.maps.arcgis.com/apps/webappviewer/index.html?id=e2e70803e3184b8b814f83c9cebaf37c

Underlying web map item `201f054f265c44209cc9924de14065f9` ("PR25 - Master", access: public).
Extent (WGS84): lon 116.7559 → 116.7907, lat -20.6463 → -20.6152.

Contents (~330 layers, all on the same ArcGIS org, projected MGA Zone 50, EPSG:28350):

| Group | Count | Notes |
|---|---|---|
| Imagery | ~136 | Monthly Nearmap/drone tiles 2023 → 2025 as `ArcGISTiledMapServiceLayer` (`tiles-ap1.arcgis.com/oOhFjgN0cUEZBHqy/.../SCJV_<date>_MGA50_Raster/MapServer`). Basemap = `NEARMAP_BurrupTilesCombined_Raster`. |
| Construction | ~79 | Saipem plot plans (Overall, Site C, Site F), conveyor, piling, trenching, firewater, etc. — `FeatureServer` layers. |
| Boundary / Lease | ~20 | Lease boundaries, cadastre, disturbance footprints, PR25_BDY_* |
| Infrastructure, Earthworks, Survey, Design | ~40 | Fencing as-builts (Handley Surveys), duct banks, drainage. |
| Heritage, Geology, Flora, Fauna, Env | ~40 | Not needed for CSEM. |
| Ground Disturbance Permit System | 2 | `PR25_GDP_Master`, `PR25_GDP_PermitRequest` — an existing permit-style spatial workflow on site. |

Verified anonymously readable (no login): web map JSON, feature service `/query`, and tile
fetches. Imagery is updated **weekly** (Site C & F and LA44 drone captures; latest 14 Sep 2026).

**Cloned into the repo** with `tools/arcgis/fetch_arcgis.py` — see `docs/maps/README.md`:
georeferenced JPEG + world file of the whole-site basemap (1 m/px) and the latest Site C/F
capture (0.25 m/px), plus GeoJSON of the lease/project boundaries and the 142 named
infrastructure polygons. Everything else can be consumed live via `esri-leaflet`.

## What this changes

- **We are at Tier 3** (doc 09), not Tier 0/1. Georeferencing (Phase 4) becomes cheap and can be
  pulled forward; the satellite basemap can be the project's own Nearmap tiles rather than Esri
  World Imagery.
- **Boundaries/areas can be imported, not drawn.** Lease boundaries, Site C / Site F plot-plan
  polygons and lot plans can seed the `areas` table via GeoJSON export from the FeatureServer
  (`/query?where=1=1&f=geojson`). The Phase 3 editor is still needed for CSEM-specific zones.
- **Landmarks / location catalogue seed:** the plot-plan feature layers carry named
  structures (tanks, buildings, conveyor transfer towers). A one-off import gives a starting
  catalogue instead of waiting for it to accumulate.
- **Multi-area site:** Site C, Site F, Port/MOF, Conveyor corridor, LA30/LA44 laydowns. The
  `sites`/`maps` model already allows one map per area if a single image is too small.
- **Existing GDP system:** the Ground Disturbance Permit layers suggest GMC/SCJV already run a
  spatial permit workflow in ArcGIS. Worth asking whether CSEM should link to it (permit number
  field on the entry) rather than duplicate it.

## Open items

- Confirm the PDF drawing is the current revision and whether it covers all work areas or just
  the process plant.
- Decide: base image = rasterised PDF (clean, legible labels) vs. Nearmap tiles (current, real)
  — likely both, with the PDF as an overlay on imagery once georeferenced.
