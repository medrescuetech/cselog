#!/usr/bin/env python3
"""Georeference an ArcGIS Print-widget export (PNG, A3 landscape, 300 dpi) against a raster
produced by render_plotplan.py, and write the cropped map frame + world file (.jpg output is
recommended: the prints are photographic and ~4x smaller as JPEG).

    georef_print.py <print.png> <scale-denominator> <reference-plan.png> <out.png>

How it works: the Print widget states the scale (e.g. 1:3,815) so pixel size in metres is known
exactly; only the offset is unknown. Because the print contains the same coloured plot-plan
linework as the reference raster, a normalised cross-correlation of the two "coloured line" masks
gives the offset to sub-metre precision, with no manual control points. Title, legend and scale
bar are cropped off (the map frame is detected as the fully non-white block).
"""
import argparse
import json
import os

import numpy as np
from PIL import Image
from scipy.signal import fftconvolve

MM_PER_INCH = 25.4


def map_frame(rgb):
    nonwhite = rgb.min(axis=2) < 240
    rows = np.where(nonwhite.mean(axis=1) > 0.8)[0]
    cols = np.where(nonwhite[rows.min():rows.max()].mean(axis=0) > 0.8)[0]
    return cols.min(), rows.min(), cols.max() + 1, rows.max() + 1


def coloured_mask(rgb):
    a = rgb.astype(int)
    return ((a.max(axis=2) - a.min(axis=2)) > 90).astype(np.float32)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("print_png")
    ap.add_argument("scale", type=float, help="scale denominator shown on the print, e.g. 3815")
    ap.add_argument("reference_png", help="render_plotplan.py output (+ .json sidecar)")
    ap.add_argument("out")
    ap.add_argument("--dpi", type=float, default=300)
    ap.add_argument("--mask", help="optional 'x0,y0,x1,y1' in MGA50 to blank out (e.g. neighbouring plant)")
    a = ap.parse_args()

    src = Image.open(a.print_png).convert("RGB")
    rgb = np.asarray(src)
    x0, y0, x1, y1 = map_frame(rgb)
    frame = src.crop((x0, y0, x1, y1))
    res = a.scale * MM_PER_INCH / a.dpi / 1000  # metres per print pixel

    ref_meta = json.load(open(os.path.splitext(a.reference_png)[0] + ".json"))
    ref_res = ref_meta["resolution_m"]
    ext = ref_meta["extent_mga50"]
    ref = Image.open(a.reference_png)

    # correlate at ~1 m/px
    work = 1.0
    f_small = coloured_mask(np.asarray(frame.resize((int(frame.width * res / work), int(frame.height * res / work)), Image.BILINEAR)))
    r_small = np.asarray(ref.resize((int(ref.width * ref_res / work), int(ref.height * ref_res / work)), Image.BILINEAR))
    r_small = (r_small[..., 3] > 64).astype(np.float32)
    f_small -= f_small.mean()
    r_small -= r_small.mean()
    corr = fftconvolve(r_small, f_small[::-1, ::-1], mode="full")
    iy, ix = np.unravel_index(np.argmax(corr), corr.shape)
    # top-left of the frame within the reference, in reference metres
    off_x = (ix - f_small.shape[1] + 1) * work
    off_y = (iy - f_small.shape[0] + 1) * work
    peak = corr.max() / (np.sqrt((f_small ** 2).sum() * (r_small ** 2).sum()) + 1e-9)
    xmin = ext["xmin"] + off_x
    ymax = ext["ymax"] - off_y

    if a.mask:
        mx0, my0, mx1, my1 = map(float, a.mask.split(","))
        px = lambda x: int(round((x - xmin) / res))
        py = lambda y: int(round((ymax - y) / res))
        frame = frame.copy()
        Image.Image.paste(frame, (235, 235, 235), (max(px(mx0), 0), max(py(my1), 0), min(px(mx1), frame.width), min(py(my0), frame.height)))

    base, ext_out = os.path.splitext(a.out)
    frame.save(a.out, quality=88, optimize=True)
    with open(base + (".jgw" if ext_out.lower() in (".jpg", ".jpeg") else ".pgw"), "w") as f:
        f.write(f"{res:.10f}\n0\n0\n{-res:.10f}\n{xmin + res / 2:.4f}\n{ymax - res / 2:.4f}\n")
    meta = {"source": os.path.basename(a.print_png), "crs": "EPSG:28350", "print_scale": a.scale,
            "dpi": a.dpi, "resolution_m": res, "width_px": frame.width, "height_px": frame.height,
            "frame_px_in_source": [int(x0), int(y0), int(x1), int(y1)],
            "extent_mga50": {"xmin": xmin, "ymax": ymax, "xmax": xmin + frame.width * res, "ymin": ymax - frame.height * res},
            "registered_against": os.path.basename(a.reference_png), "correlation_peak": float(peak),
            "masked_mga50": a.mask}
    json.dump(meta, open(base + ".json", "w"), indent=2)
    print(json.dumps(meta, indent=2))


if __name__ == "__main__":
    main()
