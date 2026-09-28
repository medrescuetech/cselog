#!/usr/bin/env python3
"""Regenerate sitemap/manifest.json from the .json sidecars next to each raster.

Run after adding/refreshing any raster:  python3 sitemap/tools/build_manifest.py
Layer ordering, groups and default visibility live in LAYER_RULES below; everything spatial
(extent, resolution, pixel size) comes from the sidecars so it can never drift from the files.
"""
import glob
import json
import os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# (glob relative to ROOT, group, title, default_visible, opacity)
LAYER_RULES = [
    ("imagery/basemap-nearmap-*_L15_1m.jpg", "imagery", "Whole site — Nearmap Oct 2023 (1 m/px)", True, 1.0),
    ("imagery/site-cf-*_L17_0.25m.jpg", "imagery", "Site C & F — current drone imagery (0.25 m/px)", True, 1.0),
    ("imagery/site-cf-*_L16_0.5m.jpg", "imagery", "Site C & F — current drone imagery (0.5 m/px, light)", False, 1.0),
    ("plan/plotplan-overall-*.png", "plan", "Plot plan — Overall (Saipem 2025)", True, 1.0),
    ("plan/plotplan-sitecf-detail-*.png", "plan", "Plot plan — Site C / F detail (Saipem 2025)", False, 1.0),
    ("prints/print-site-c-2026-*_plotplan-on-imagery.jpg", "prints", "GMC print — Site C, plot plan on imagery (21 Sep 2026)", False, 1.0),
    ("prints/print-site-f-2026-*_plotplan-on-imagery.jpg", "prints", "GMC print — Site F, plot plan on imagery (21 Sep 2026)", False, 1.0),
    ("prints/print-site-f-2026-*_plotplan-boundaries-on-imagery.jpg", "prints", "GMC print — Site F, plot plan + boundaries (21 Sep 2026)", False, 1.0),
    ("prints/print-site-c-2023-*.jpg", "prints", "GMC print — Site C, plot plan on Oct 2023 imagery", False, 1.0),
]

VECTORS = [
    ("features/project-development-envelope.geojson", "Project development envelope", True),
    ("features/lease-boundaries.geojson", "Lease boundaries", False),
    ("features/project-boundaries-infrastructure.geojson", "Named infrastructure (142)", True),
    ("features/cadastre.geojson", "Cadastre (context)", False),
]


def vector_epsg(rel):
    with open(os.path.join(ROOT, rel)) as fh:
        return json.load(fh).get("crs_epsg", 4326)


def main():
    layers = []
    for pattern, group, title, visible, opacity in LAYER_RULES:
        for path in sorted(glob.glob(os.path.join(ROOT, pattern))):
            rel = os.path.relpath(path, ROOT)
            side = json.load(open(os.path.splitext(path)[0] + ".json"))
            layers.append({
                "id": os.path.splitext(os.path.basename(path))[0],
                "group": group,
                "title": title,
                "file": rel,
                "world_file": os.path.splitext(rel)[0] + (".jgw" if rel.endswith(".jpg") else ".pgw"),
                "crs": side.get("crs", "EPSG:28350"),
                "resolution_m": side["resolution_m"],
                "width_px": side["width_px"],
                "height_px": side["height_px"],
                "extent": side["extent_mga50"],
                "transparent": rel.endswith(".png"),
                "default_visible": visible,
                "opacity": opacity,
                "source": side.get("service") or side.get("sources") or side.get("source"),
            })
    xs = [l["extent"]["xmin"] for l in layers] + [l["extent"]["xmax"] for l in layers]
    ys = [l["extent"]["ymin"] for l in layers] + [l["extent"]["ymax"] for l in layers]
    manifest = {
        "name": "HWRT site map package",
        "crs": "EPSG:28350",
        "crs_name": "GDA94 / MGA zone 50 — units are metres (easting, northing)",
        "extent": {"xmin": min(xs), "ymin": min(ys), "xmax": max(xs), "ymax": max(ys)},
        "excluded": ["Yara Pilbara plant east of Site C (masked out of prints; ~ easting > 476950)"],
        "rasters": layers,
        "vectors": [
            {"file": f, "title": t, "default_visible": v, "crs": f"EPSG:{vector_epsg(f)}"}
            for f, t, v in VECTORS
        ],
    }
    with open(os.path.join(ROOT, "manifest.json"), "w") as f:
        json.dump(manifest, f, indent=2)
    print(f"{len(layers)} rasters, {len(VECTORS)} vectors -> sitemap/manifest.json")


if __name__ == "__main__":
    main()
