# SCJV site map package

A self-contained, project-independent map of the Perdaman Urea Project site (Site C, Site F,
port/conveyor corridor). Copy this directory into any app — CSEM or otherwise — and you have a
working map with imagery, drawn plot plan, boundaries and a coordinate system for pins.

**Everything is in one coordinate system: MGA Zone 50 (EPSG:28350), metres.** Every raster has an
ESRI world file (`.jgw`/`.pgw`) and a `.json` sidecar giving its exact extent; every vector is
GeoJSON in the same CRS. `manifest.json` lists all layers with their extents, so a client never
has to read world files. `viewer.html` is a zero-backend Leaflet reference implementation (open it
over any static server, e.g. `python3 -m http.server` in this folder) — click drops a pin and shows
its easting/northing.

No GPS, no web-mercator, no zoom-out beyond the site: Leaflet `CRS.Simple` with 1 unit = 1 m
(`latlng = [-northing, easting]`). Pins are stored as `(easting, northing)` and therefore work on
any layer, and survive imagery refreshes.

Excluded on purpose: the Yara Pilbara plant immediately east of Site C is masked out of the GMC
prints (grey), and the print title/legend strips are cropped. The 2026 drone imagery is clipped by
the source to the project area (black = no data).

## Layers

### `imagery/` — photographic base
| File | Source | Captured | m/px | px |
|---|---|---|---|---|
| `basemap-nearmap-2023-10_L15_1m.jpg` | Nearmap basemap of the PR25 web map | Oct 2023 | 1.0 | 6400 × 5632 (whole project) |
| `site-cf-2026-09-14_L17_0.25m.jpg` | `2026SeptWk2SiteCF` drone capture | 14 Sep 2026 | 0.25 | 4864 × 7424 |
| `site-cf-2026-09-14_L16_0.5m.jpg` | same, lighter | 14 Sep 2026 | 0.5 | 2560 × 3840 |

### `plan/` — drawn engineering plan, **transparent** PNG, pixel-aligned to the L17 imagery
| File | Source |
|---|---|
| `plotplan-overall-2025_L17_0.25m.png` | `PR25_CON_Overall` (Saipem overall plot plan, Mar 2025) — 223k polylines rendered in the service's own colours |
| `plotplan-sitecf-detail-2025_L17_0.25m.png` | `PR25_CON_SiteC` + `PR25_CON_SiteF01` + `PR25_CON_SiteF02` — 318k polylines, denser detail |

These are what GMC could not export from ArcGIS (the Print widget always burns the imagery in):
rendered straight from the FeatureServer geometry by `tools/render_plotplan.py`, so the background
is truly transparent and the layer can be toggled/faded over any imagery.

### `prints/` — GMC's ArcGIS Print exports (imagery + plot plan burned together), georeferenced
| File | What's in it |
|---|---|
| `print-site-c-2026-09-21_plotplan-on-imagery.jpg` | Site C, plot plan over Sep 2026 imagery |
| `print-site-c-2023-10-imagery_plotplan.jpg` | Site C, plot plan over Oct 2023 imagery |
| `print-site-f-2026-09-21_plotplan-on-imagery.jpg` | Site F, plot plan over imagery |
| `print-site-f-2026-09-21_plotplan-boundaries-on-imagery.jpg` | Site F, plot plan + S45c footprint + subcontractor compound |

Registered automatically by `tools/georef_print.py`: pixel size comes from the print scale
(1:3,815 at 300 dpi = 0.323 m/px); the offset comes from cross-correlating the coloured linework
against `plan/plotplan-overall-*.png`. Useful as ready-made "everything on" views; the imagery +
plan layers above are the preferred, separable set.

### `features/` — GeoJSON, EPSG:28350
| File | Source | Use |
|---|---|---|
| `project-boundaries-infrastructure.geojson` | `PR25_BDY_ProjectBoundaries/0` — 142 polygons, `RefName` = FLARE STACK, CONTROL ROOM, UREA UNIT 1/2, GATE HOUSE, … | seed for areas / landmarks |
| `project-development-envelope.geojson` | `PR25_BDY_ProjectBoundaries/3` | site outline |
| `lease-boundaries.geojson` | `PR25_BDY_LeaseBoundaries/0` — 26 polygons | zones |
| `cadastre.geojson` | `PR25_ADM_Cadastre/0` — 1197 lots | context only |

### `*.pdf` — originals as received
- `scjv-site-map-2026-08-21_0000-GB-A-60002-3.pdf` — SCJV site map drawing 0000-GB-A-60002 rev 3.
- `scjv-site-map-2026-09-satellite.pdf` — satellite print from the ArcGIS app.

## Refreshing (all anonymous, no ArcGIS login)

```bash
T=https://tiles-ap1.arcgis.com/oOhFjgN0cUEZBHqy/arcgis/rest/services
F=https://services-ap1.arcgis.com/oOhFjgN0cUEZBHqy/arcgis/rest/services
cd sitemap
python3 tools/fetch_arcgis.py layers --grep "Imagery - Site C"           # newest weekly capture
python3 tools/fetch_arcgis.py imagery $T/<service>/MapServer 17 --out imagery/site-cf-<date>_L17_0.25m.jpg
python3 tools/render_plotplan.py imagery/site-cf-<date>_L17_0.25m.json plan/plotplan-overall-2025_L17_0.25m.png $F/PR25_CON_Overall/FeatureServer/0
python3 tools/georef_print.py <GMC print>.png 3815 plan/plotplan-overall-2025_L17_0.25m.png prints/<name>.jpg [--mask xmin,ymin,xmax,ymax]
python3 tools/fetch_arcgis.py features $F/PR25_BDY_ProjectBoundaries/FeatureServer/0 --out features/project-boundaries-infrastructure.geojson
python3 tools/build_manifest.py
```

Dependencies: Python 3 + Pillow (+ numpy/scipy for `georef_print.py`).

Not stored: the Ground Disturbance Permit layers (`PR25_GDP_*`, contain named individuals) — fetch
on demand if ever needed.

## Serving in an app

The L17 imagery is a 10 MB JPEG and the plan PNGs ~0.7 MB — fine on a control-room PC as single
`L.imageOverlay`s (as `viewer.html` does). For tablets/phones tile the rasters once at build time
(`vips dzsave` / `gdal2tiles`) and swap `imageOverlay` for `tileLayer`; extents in
`manifest.json` are unchanged.
