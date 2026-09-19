# 04 — Maps, pins, overlays and georeferencing

## Two modes, one data model

### Mode A — image only (`crs: simple`)

Leaflet with `L.CRS.Simple` treats the image as a flat plane; coordinates are image pixels.

```js
const bounds = [[0, 0], [height, width]];              // pixel space
const map = L.map('map', { crs: L.CRS.Simple, minZoom: -3 });
L.imageOverlay(imageUrl, bounds).addTo(map);
map.fitBounds(bounds);
// dropping a pin:
map.on('click', e => placePin({ x: e.latlng.lng, y: e.latlng.lat }));
```

Works with **any** picture: a scan, a PDF page rasterised at 300 dpi, a screenshot of the GIS
viewer. No GIS knowledge, no coordinate system, no external data. This is Phase 2 of delivery
and it satisfies the brief on its own.

### Mode B — georeferenced (`crs: epsg3857`)

Standard Web Mercator map with a satellite/aerial basemap, and the site plan drawn on top at
adjustable opacity.

```js
const map = L.map('map');                              // default CRS: EPSG3857
L.tileLayer(satelliteUrl, { attribution }).addTo(map); // basemap
L.imageOverlay(planUrl, geoBounds, { opacity: 0.7 }).addTo(map);   // or L.tileLayer for tiled
```

Pins now have real `lat`/`lng`: they survive a base-image swap, can be handed to anyone with a
GPS, and can be exported to KML/GeoJSON for the site's own GIS.

**Both modes store `x,y` and, when possible, `lat,lng` for every point.** Start in Mode A;
georeference later; run the backfill; history keeps working.

## Georeferencing a plain image

Store, per `map_version`, an affine transform derived from control points:

```json
{
  "method": "affine",
  "control_points": [
    { "x": 412,  "y": 1890, "lat": -27.47011, "lng": 153.02123, "label": "Main gate" },
    { "x": 3120, "y": 1877, "lat": -27.47008, "lng": 153.02981, "label": "Weighbridge" },
    { "x": 1740, "y": 260,  "lat": -27.46402, "lng": 153.02550, "label": "Tank 3 centre" }
  ],
  "matrix": [a, b, c, d, e, f],
  "rms_error_m": 1.8
}
```

- 2 points gives scale + rotation + translation (enough for most survey plans, which are
  north-up and to scale). 3+ points lets us least-squares fit and report an RMS error so we know
  how good the fit is. 4+ with a projective fit handles a photographed/skewed plan.
- The transform is computed once (server-side, on save) and applied in both directions:
  `px→geo` when saving a pin in Mode A, `geo→px` when importing existing GIS points.
- **How to get control points without survey gear:** pick 3 unambiguous features visible on both
  the plan and satellite imagery (gate corners, tank centres, building corners), click them on
  the plan, then click the same spots on the satellite view. Two side-by-side maps in the admin
  UI, with a live RMS readout.
- If a **GeoTIFF / world file (.tfw, .jgw) / shapefile** exists, skip all that: read the bounds
  directly (`gdalinfo`) and the georeference is exact. Always ask for this first.

## Large images

A 200 MB survey raster cannot be a single `ImageOverlay` — mobile browsers will die. Pre-slice
it into tiles once, on upload:

```bash
# georeferenced raster → web-mercator tiles
gdal2tiles.py -z 0-7 -w none --xyz site_plan.tif public/maps/7/tiles/

# non-georeferenced image → pixel-space tiles
vips dzsave site_plan.png public/maps/7/tiles --layout google --suffix .png
```

Tiles are static files served by Apache — fast, cacheable, no PHP in the request path. Threshold:
tile anything over ~8000 px on a side or ~15 MB; below that a plain overlay is simpler.

If `gdal`/`vips` aren't available on the cPanel host (likely on shared hosting), tile locally or
in a GitHub Action and commit/upload the tile directory — it's static output, it doesn't need to
be generated on the server.

## Satellite basemaps

| Source | Notes |
|---|---|
| Esri World Imagery | Free tile URL, good AU coverage, attribution required. Usual default. |
| Nearmap | Best AU imagery and very current, but a paid subscription — worth it if GMC already has one. |
| Google Satellite | Requires the Maps API and billing; don't scrape the tile endpoints. |
| Site's own orthophoto/drone capture | Best of all if it exists — treat it as another georeferenced overlay. |

Make it configurable per site; the app ships with Esri World Imagery.

## Landmarks and boundaries

- Drawn with **Leaflet-Geoman**: point tool for landmarks, polygon/rectangle tool for areas,
  edit/drag/delete, snap-to-vertex.
- Saved as GeoJSON in the map's coordinate space (and mirrored to lat/lng when georeferenced).
- Rendered as toggleable layers with a legend; areas have a name label at the centroid.
- **Point-in-polygon** on entry creation stamps the containing area onto the entry, so the open
  board can be grouped by zone ("3 open in Tank Farm B") and reports can be filtered by area.
- Because overlays are stored against the *map*, not the image file, replacing the base image
  with a newer survey keeps every landmark and boundary — provided the georeference matches. If
  the new image has a different scale/origin, the admin re-runs the control points and the app
  offers to re-project the existing overlays through the old→new transform.

## Pin rendering rules

| Signal | Rendering |
|---|---|
| Work type | Marker colour / icon (from `work_types.colour`). |
| Age | Halo: grey <2 h, amber 2–4 h, red >4 h (thresholds configurable). |
| Closed | Hidden on the live map; shown greyed in history playback. |
| Overlapping pins | Cluster with `Leaflet.markercluster`, spiderfy on click. |
| Ad-hoc (unsaved) location | Dashed outline, so the board shows it isn't a catalogue location. |
