<?php

namespace App\Support;

/**
 * Planar geometry on GeoJSON Polygon / MultiPolygon in EPSG:28350 metres.
 * The site is ~3 km across; planar maths is exact enough (< 1 cm).
 */
final class Geo
{
    public static function contains(array $geometry, float $x, float $y): bool
    {
        foreach (self::polygons($geometry) as $rings) {
            if (! self::inRing($rings[0], $x, $y)) {
                continue;
            }
            foreach (array_slice($rings, 1) as $hole) {
                if (self::inRing($hole, $x, $y)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    public static function area(array $geometry): float
    {
        $total = 0.0;
        foreach (self::polygons($geometry) as $rings) {
            $total += abs(self::ringArea($rings[0]));
            foreach (array_slice($rings, 1) as $hole) {
                $total -= abs(self::ringArea($hole));
            }
        }

        return $total;
    }

    /** Centroid of the largest outer ring. */
    public static function centroid(array $geometry): array
    {
        $best = null;
        $bestA = -1;
        foreach (self::polygons($geometry) as $rings) {
            $a = abs(self::ringArea($rings[0]));
            if ($a > $bestA) {
                $best = $rings[0];
                $bestA = $a;
            }
        }
        if (! $best) {
            return [0, 0];
        }
        $cx = $cy = 0;
        $a = 0;
        $n = count($best);
        for ($i = 0; $i < $n - 1; $i++) {
            [$x0, $y0] = $best[$i];
            [$x1, $y1] = $best[$i + 1];
            $c = $x0 * $y1 - $x1 * $y0;
            $a += $c;
            $cx += ($x0 + $x1) * $c;
            $cy += ($y0 + $y1) * $c;
        }
        if (abs($a) < 1e-9) {
            return [$best[0][0], $best[0][1]];
        }

        return [$cx / (3 * $a), $cy / (3 * $a)];
    }

    /** @return array<int, array<int, array<int, array{0: float, 1: float}>>> list of polygons, each a list of rings */
    private static function polygons(array $g): array
    {
        return match ($g['type'] ?? null) {
            'Polygon' => [$g['coordinates']],
            'MultiPolygon' => $g['coordinates'],
            default => [],
        };
    }

    private static function inRing(array $ring, float $x, float $y): bool
    {
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private static function ringArea(array $ring): float
    {
        $s = 0;
        $n = count($ring);
        for ($i = 0; $i < $n - 1; $i++) {
            $s += $ring[$i][0] * $ring[$i + 1][1] - $ring[$i + 1][0] * $ring[$i][1];
        }

        return $s / 2;
    }
}
