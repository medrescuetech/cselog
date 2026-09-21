#!/usr/bin/env python3
"""Pull map data out of the public PR25 ArcGIS Online web map.

Sub-commands:
  layers                       list operational layers of the web map (title, type, url)
  imagery <service-url> <lvl>  stitch a tiled MapServer into a JPEG + .jgw world file
  features <layer-url>         export a FeatureServer layer as GeoJSON (EPSG:4326)

All services are in MGA Zone 50 (EPSG:28350). Only the standard library + Pillow are used.
"""
import argparse
import io
import json
import math
import os
import sys
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor

from PIL import Image

WEBMAP_ITEM = "201f054f265c44209cc9924de14065f9"
PORTAL = "https://enveng.maps.arcgis.com/sharing/rest/content/items"
UA = {"User-Agent": "csem-map-fetch/1.0"}


def get_json(url, **params):
    params.setdefault("f", "json")
    req = urllib.request.Request(url + "?" + urllib.parse.urlencode(params), headers=UA)
    with urllib.request.urlopen(req, timeout=60) as r:
        return json.load(r)


def get_bytes(url):
    req = urllib.request.Request(url, headers=UA)
    with urllib.request.urlopen(req, timeout=60) as r:
        return r.read()


def cmd_layers(args):
    data = get_json(f"{PORTAL}/{WEBMAP_ITEM}/data")
    for l in data.get("baseMap", {}).get("baseMapLayers", []):
        print(f"BASEMAP\t{l.get('title')}\t{l.get('url')}")
    for l in data.get("operationalLayers", []):
        if args.grep and args.grep.lower() not in (l.get("title") or "").lower():
            continue
        print(f"{l.get('layerType')}\t{l.get('title')}\t{l.get('url')}")


def cmd_imagery(args):
    svc = args.service.rstrip("/")
    info = get_json(svc)
    ti = info["tileInfo"]
    size = ti["rows"]
    ox, oy = ti["origin"]["x"], ti["origin"]["y"]
    res = {l["level"]: l["resolution"] for l in ti["lods"]}[args.level]
    ext = info["fullExtent"]
    if args.bbox:
        ext = dict(zip(("xmin", "ymin", "xmax", "ymax"), map(float, args.bbox.split(","))))
    tile_m = size * res
    c0 = int((ext["xmin"] - ox) // tile_m)
    c1 = int((ext["xmax"] - ox) // tile_m)
    r0 = int((oy - ext["ymax"]) // tile_m)
    r1 = int((oy - ext["ymin"]) // tile_m)
    ncols, nrows = c1 - c0 + 1, r1 - r0 + 1
    print(f"{info['name']}: level {args.level} ({res:.3f} m/px), {ncols}x{nrows} tiles "
          f"-> {ncols*size}x{nrows*size} px", file=sys.stderr)

    canvas = Image.new("RGB", (ncols * size, nrows * size), (0, 0, 0))

    def fetch(rc):
        r, c = rc
        try:
            return rc, get_bytes(f"{svc}/tile/{args.level}/{r}/{c}")
        except Exception:
            return rc, None

    jobs = [(r, c) for r in range(r0, r1 + 1) for c in range(c0, c1 + 1)]
    missing = 0
    with ThreadPoolExecutor(max_workers=16) as ex:
        for (r, c), b in ex.map(fetch, jobs):
            if not b:
                missing += 1
                continue
            canvas.paste(Image.open(io.BytesIO(b)).convert("RGB"), ((c - c0) * size, (r - r0) * size))
    print(f"missing tiles: {missing}/{len(jobs)}", file=sys.stderr)

    out = args.out
    os.makedirs(os.path.dirname(out) or ".", exist_ok=True)
    canvas.save(out, quality=args.quality)
    # ESRI world file: pixel size, rotation, rotation, -pixel size, centre of top-left pixel
    tlx = ox + c0 * tile_m
    tly = oy - r0 * tile_m
    base, _ = os.path.splitext(out)
    with open(base + ".jgw", "w") as f:
        f.write(f"{res}\n0.0\n0.0\n{-res}\n{tlx + res/2}\n{tly - res/2}\n")
    with open(base + ".json", "w") as f:
        json.dump({
            "service": svc, "name": info["name"], "description": info.get("description"),
            "level": args.level, "resolution_m": res, "crs": "EPSG:28350",
            "width_px": canvas.width, "height_px": canvas.height,
            "extent_mga50": {"xmin": tlx, "ymax": tly,
                             "xmax": tlx + canvas.width * res, "ymin": tly - canvas.height * res},
        }, f, indent=2)
    print(f"wrote {out} (+ .jgw, .json)", file=sys.stderr)


def cmd_features(args):
    url = args.layer.rstrip("/")
    meta = get_json(url)
    max_rec = meta.get("maxRecordCount", 1000)
    feats = []
    offset = 0
    while True:
        page = get_json(f"{url}/query", where="1=1", outFields="*", outSR=4326,
                        f="geojson", resultOffset=offset, resultRecordCount=max_rec)
        feats.extend(page.get("features", []))
        if not page.get("properties", {}).get("exceededTransferLimit") and len(page.get("features", [])) < max_rec:
            break
        offset += max_rec
    fc = {"type": "FeatureCollection", "name": meta.get("name"),
          "source": url, "geometryType": meta.get("geometryType"), "features": feats}
    os.makedirs(os.path.dirname(args.out) or ".", exist_ok=True)
    with open(args.out, "w") as f:
        json.dump(fc, f, separators=(",", ":"))
    print(f"wrote {args.out}: {len(feats)} features ({meta.get('geometryType')})", file=sys.stderr)


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = p.add_subparsers(dest="cmd", required=True)
    s = sub.add_parser("layers"); s.add_argument("--grep"); s.set_defaults(fn=cmd_layers)
    s = sub.add_parser("imagery")
    s.add_argument("service"); s.add_argument("level", type=int); s.add_argument("--out", required=True)
    s.add_argument("--bbox", help="xmin,ymin,xmax,ymax in EPSG:28350 (default: service full extent)")
    s.add_argument("--quality", type=int, default=85); s.set_defaults(fn=cmd_imagery)
    s = sub.add_parser("features"); s.add_argument("layer"); s.add_argument("--out", required=True)
    s.set_defaults(fn=cmd_features)
    a = p.parse_args(); a.fn(a)


if __name__ == "__main__":
    main()
