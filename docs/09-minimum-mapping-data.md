# 09 — Approach when mapping data is limited

GMC's position: **limited mapping data available.** This document is the plan for that case.
Short version: it doesn't matter much, and nothing in the product needs to wait for it.

## The key insight

The app never *needs* a GIS file. It needs (a) a picture of the site and (b) a place to put a
pin on that picture. Everything else — real-world coordinates, satellite imagery, exportable
GeoJSON — is an **upgrade** that can be applied later without touching the log data, because
every pin is stored in pixel space first (`04-map-and-georeferencing.md`, "two modes, one data
model").

So: build on whatever image exists today, and treat georeferencing as an optional later phase
that back-fills history rather than a prerequisite.

## Tier 0 — you have literally nothing

Build the map from satellite imagery itself. Esri World Imagery / Google Earth gives a
perfectly serviceable base for a site map: it's already georeferenced, current enough, and free
to use with attribution. Draw your own site structure on top of it with the boundary/landmark
tools (§ below) and you have a working site map in an afternoon, with no survey data at all.

This is the pragmatic default. A satellite base + hand-drawn areas + named landmarks is often
*better* for radio-call logging than a technical survey plan, because it looks like what people
see.

## Tier 1 — a picture of a plan (JPEG/PNG/PDF/screenshot/photo)

The common case. Use `L.CRS.Simple` with the image as-is; pins are pixel coordinates.

Practical notes:
- **PDF** → rasterise once at 200–300 dpi (`pdftoppm -r 300 -png plan.pdf plan`). Vector PDFs
  give a crisp result at any size.
- **Photo of a printed plan on a wall** → works, but deskew/crop it first; a projective
  (4-point) fit later can straighten it if it ever needs georeferencing.
- **Screenshot of a GIS viewer** → grab it at the highest zoom that still shows the whole site;
  if the viewer shows coordinates, note the lat/lng at two opposite corners *while you're there*
  — that's a free georeference and takes 30 seconds.
- Quality bar: legible place-names and enough distinguishable features that an operator can find
  the right spot. 2000–6000 px on the long edge is plenty.

## Tier 2 — a picture *plus* a few known points

This is the cheapest meaningful upgrade and requires no survey equipment:

1. Open the site plan and a satellite view side by side in the admin georeference screen.
2. Click 3–4 features that are unambiguous on both — gate corners, tank centres, a building
   corner, a road intersection.
3. The app least-squares fits an affine transform and reports the RMS error in metres.

With a north-up, to-scale plan this typically lands within a few metres — far better than
needed to say "the open space is in the north-west corner of the tank farm". It unlocks the
satellite basemap, real lat/lng on every pin, sharable coordinates, and GeoJSON/KML export.

**Even cheaper variant:** stand at two recognisable points on site with a phone, note the GPS
coordinates, and click those same two points on the plan. Two points gives scale + rotation +
translation, which is enough for most plans.

## Tier 3 — proper GIS data (if it ever turns up)

GeoTIFF, a world file (`.tfw`/`.jgw`), shapefile, DWG/DXF with a known CRS, or an ArcGIS/QGIS
export. Then the georeference is exact and read straight off the file (`gdalinfo`), and
existing asset points can be imported as locations in bulk. Worth asking the survey team,
the civil/engineering contractor, or the council once — but not worth waiting for.

## Building the map *from the app*, not from a file

Because limited data is expected, the overlay tools are not a nice-to-have — they're how the
site map actually gets built:

- **Boundaries/areas**: draw the yard, plant, tank farm, pond area, contractor lots as named
  polygons. Ten minutes of drawing gives every pin an automatic area label and makes the open
  board groupable by zone.
- **Landmarks**: gates, muster points, weighbridge, shafts, major vessels. These are the visual
  anchors that let an operator orient on the map in a second.
- **Locations accumulate themselves.** After a few weeks of "save this location? yes", the
  catalogue of pins *is* a site map of where work actually happens — arguably the most valuable
  spatial data in the system, and it's generated for free by normal use.

**The site map improves with use rather than being delivered up front.** That's the whole
strategy for limited mapping data.

## Multi-level / indoor

If confined spaces stack vertically (levels of a plant, tanks with internal decks), don't force
them onto one image. Model each level as its own `map` under the site with its own image, and
let the location record which map it belongs to (the data model already does this). A simple
level switcher on the map screen covers it.

## Revised phase plan for this case

| Phase | Scope | Change from the original plan |
|---|---|---|
| 0 | Repo, cPanel deploy, Laravel skeleton, auth | unchanged |
| 1 | Log form, work types, open board, close-out, history/CSV | unchanged — **fully useful with no map at all** |
| 2 | Image map + pin drop + "save this location?" + picker | uses whatever image exists (Tier 0/1) |
| 3 | Landmarks + boundaries editor | **promoted in priority** — this is how the map gets built |
| 4 | Georeferencing (Tier 2 control points) + satellite basemap | optional, do it when a decent image or GPS points exist |
| 5 | Wallboard, overdue alerts, reports | unchanged |

Phases 1–3 deliver the entire brief with nothing more than a screenshot.

## What I need from GMC to start

Just one thing: **whatever image you have** — a photo of a plan on the wall is genuinely enough
to prototype the pin flow. Also useful, if easy:

- Rough site extents (an address or a dropped pin on Google Maps) so the satellite base can be
  centred and I can judge whether Tier 0 is viable on its own.
- A list of 10–20 place names people actually say on the radio. That seeds the catalogue and
  tells me how names are structured ("Sump 4 north wall" vs "CS-104").
- Whether work happens on multiple levels or only at ground level.
