#!/usr/bin/env python3
"""Render CAD-derived FeatureServer linework (Saipem plot plans) into a transparent PNG that is
pixel-aligned with an imagery raster produced by fetch_arcgis.py.

    render_plotplan.py <imagery.json> <out.png> <layer-url> [<layer-url> ...]

The imagery .json sidecar supplies the MGA50 extent and pixel size; the output PNG has the same
dimensions and reuses the imagery's .jgw, so both can be shown as overlays on one CRS.Simple map.
Features are fetched with server-side coordinate quantization at the output pixel size, which
cuts the payload by an order of magnitude. Raw pages are cached under --cache so re-rendering is
free.
"""
import argparse
import hashlib
import json
import os
import shutil
import sys
import time
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor

from PIL import Image, ImageDraw

UA = {"User-Agent": "hwrt-map-fetch/2.0"}

DEFAULT_RGB = (40, 40, 40)


def renderer_colours(meta):
    """Unique-value renderer (field -> RGB) as published on the service, so the output matches
    what the ArcGIS viewer shows. Returns (field, {value: rgb})."""
    r = meta.get("drawingInfo", {}).get("renderer", {})
    if r.get("type") != "uniqueValue":
        return None, {}
    return r["field1"], {str(u["value"]): tuple(u["symbol"]["color"][:3]) for u in r.get("uniqueValueInfos", [])}


def get_json(url, **params):
    params.setdefault("f", "json")
    req = urllib.request.Request(url + "?" + urllib.parse.urlencode(params), headers=UA)
    with urllib.request.urlopen(req, timeout=120) as r:
        return json.load(r)


def fetch_layer(url, ext, res, cache):
    meta = get_json(url)
    oid = meta["objectIdField"]
    field, _ = renderer_colours(meta)
    page = meta.get("maxRecordCount", 2000)
    st = get_json(f"{url}/query", where="1=1", outStatistics=json.dumps([
        {"statisticType": "min", "onStatisticField": oid, "outStatisticFieldName": "lo"},
        {"statisticType": "max", "onStatisticField": oid, "outStatisticFieldName": "hi"}]))
    lo, hi = st["features"][0]["attributes"]["lo"], st["features"][0]["attributes"]["hi"]
    key = hashlib.md5(f"{url}|{json.dumps(ext)}|{res}".encode()).hexdigest()[:10]
    cdir = os.path.join(cache, key)
    os.makedirs(cdir, exist_ok=True)
    quant = json.dumps({"mode": "view", "originPosition": "upperLeft", "tolerance": res,
                        "extent": {**ext, "spatialReference": {"wkid": 28350}}})
    geom = json.dumps({**ext, "spatialReference": {"wkid": 28350}})

    # Paging by object-id range is far cheaper for the server than resultOffset on 600k+ rows,
    # and is safe to run concurrently.
    def one(start):
        path = os.path.join(cdir, f"{start}.json")
        if os.path.exists(path):
            return path
        for attempt in range(5):
            try:
                d = get_json(f"{url}/query", where=f"{oid}>={start} AND {oid}<{start + page}",
                             outFields=field or oid, outSR=28350,
                             geometry=geom, geometryType="esriGeometryEnvelope", inSR=28350,
                             spatialRel="esriSpatialRelIntersects",
                             quantizationParameters=quant, resultRecordCount=page)
                if "error" in d:
                    raise RuntimeError(d["error"])
                with open(path, "w") as f:
                    json.dump(d, f)
                return path
            except Exception as e:
                err = e
                time.sleep(2 * (attempt + 1))
        print(f"  page {start} failed: {err}", file=sys.stderr)
        return None

    starts = list(range(lo, hi + 1, page))
    print(f"{meta['name']}: ids {lo}..{hi}, {len(starts)} pages", file=sys.stderr)
    with ThreadPoolExecutor(max_workers=6) as ex:
        paths = [p for p in ex.map(one, starts) if p]
    return meta, paths


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("imagery_json")
    ap.add_argument("out")
    ap.add_argument("layers", nargs="+")
    ap.add_argument("--cache", default=os.path.expanduser("~/.cache/hwrt-arcgis"))
    ap.add_argument("--width", type=int, default=1, help="line width in px")
    ap.add_argument("--mono", action="store_true", help="ignore renderer colours, draw dark grey")
    a = ap.parse_args()

    img = json.load(open(a.imagery_json))
    ext = img["extent_mga50"]
    res = img["resolution_m"]
    W, H = img["width_px"], img["height_px"]
    canvas = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw = ImageDraw.Draw(canvas)
    n = 0
    for url in a.layers:
        meta, paths = fetch_layer(url.rstrip("/"), ext, res, a.cache)
        field, colours = renderer_colours(meta)
        for path in paths:
            d = json.load(open(path))
            t = d.get("transform")
            for f in d.get("features", []):
                g = f.get("geometry") or {}
                val = str((f.get("attributes") or {}).get(field)) if field else None
                rgb = DEFAULT_RGB if a.mono else colours.get(val, DEFAULT_RGB)
                for ring in g.get("paths", []) + g.get("rings", []):
                    if t:  # quantized: integer deltas in output-pixel units
                        pts, x, y = [], 0, 0
                        for i, (dx, dy) in enumerate(ring):
                            x, y = (dx, dy) if i == 0 else (x + dx, y + dy)
                            pts.append((x, y))
                    else:
                        pts = [((x - ext["xmin"]) / res, (ext["ymax"] - y) / res) for x, y in ring]
                    if len(pts) >= 2:
                        draw.line(pts, fill=rgb + (255,), width=a.width)
                        n += 1
    canvas.save(a.out, optimize=True)
    base = os.path.splitext(a.imagery_json)[0]
    shutil.copy(base + ".jgw", os.path.splitext(a.out)[0] + ".pgw")
    with open(os.path.splitext(a.out)[0] + ".json", "w") as f:
        json.dump({"sources": a.layers, "aligned_to": os.path.basename(a.imagery_json),
                   "crs": "EPSG:28350", "resolution_m": res, "width_px": W, "height_px": H,
                   "extent_mga50": ext, "polylines_drawn": n}, f, indent=2)
    print(f"wrote {a.out}: {n} polylines", file=sys.stderr)


if __name__ == "__main__":
    main()
