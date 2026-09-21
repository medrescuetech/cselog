# Map assets

Everything here is pulled from the public PR25 ArcGIS Online web map (see
`../10-site-map-sources.md`) with `tools/arcgis/fetch_arcgis.py`, or supplied by GMC as PDF.
All rasters are MGA Zone 50 (EPSG:28350), north-up, with an ESRI world file (`.jgw`) and a
`.json` sidecar giving the exact extent — i.e. **already georeferenced**, no control points
needed.

## Rasters (`imagery/`)

| File | Source layer | Captured | m/px | Size |
|---|---|---|---|---|
| `basemap-nearmap-2023-10_L15_1m.jpg` | `NEARMAP_BurrupTilesCombined_Raster` (web map basemap) | Oct 2023 | 1.0 | 6400 × 5632 — whole project extent (Site C, Site F, port, conveyor, LA30/LA44) |
| `site-cf-2026-09-14_L16_0.5m.jpg` | `2026SeptWk2SiteCF` ("Imagery - Site C & F (14 Sept 2026)") | 14 Sep 2026 | 0.5 | 2560 × 3840 |
| `site-cf-2026-09-14_L17_0.25m.jpg` | same | 14 Sep 2026 | 0.25 | 4864 × 7424 — recommended Phase 2 base image for Site C/F |
| `*_preview.jpg` | downsampled previews | | | |

Weekly drone captures are published for Site C & F and LA44; re-run the fetch to refresh:

```bash
T=https://tiles-ap1.arcgis.com/oOhFjgN0cUEZBHqy/arcgis/rest/services
python3 tools/arcgis/fetch_arcgis.py layers --grep "Imagery - Site C"          # find the newest service
python3 tools/arcgis/fetch_arcgis.py imagery $T/<service>/MapServer 17 --out docs/maps/imagery/<name>.jpg
```

## Vectors (`features/`, GeoJSON, EPSG:4326)

| File | Source | Use in CSEM |
|---|---|---|
| `project-boundaries-infrastructure.geojson` | `PR25_BDY_ProjectBoundaries/0` — 142 polygons, `RefName` = FLARE STACK, CONTROL ROOM, UREA UNIT 1/2, AMMONIA STORAGE, GATE HOUSE, … | **Seed for `areas` and `landmarks`** |
| `lease-boundaries.geojson` | `PR25_BDY_LeaseBoundaries/0` — 26 polygons | Site outline / zones |
| `project-development-envelope.geojson` | `PR25_BDY_ProjectBoundaries/3` | Overall site boundary |
| `cadastre.geojson` | `PR25_ADM_Cadastre/0` — 1197 lots | Context only |

Not stored:
- **Saipem plot plans** (`PR25_CON_Overall`, `PR25_CON_SiteC`, …) are raw CAD linework — 670k /
  467k polylines. Consume live from the FeatureServer (or as a rendered tile layer) rather than
  committing.
- **Ground Disturbance Permit** layer (`PR25_GDP_Master`, 15 permits) contains named
  individuals — fetch on demand, don't commit.

## PDFs

- `scjv-site-map-2026-08-21_0000-GB-A-60002-3.pdf` — SCJV site map drawing, rev 3, 21 Aug 2026.
- `scjv-site-map-2026-09-satellite.pdf` — satellite view print from the ArcGIS app, Sep 2026.
