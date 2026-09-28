<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\Landmark;
use App\Support\Geo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads the boundary GeoJSON from the standalone sitemap package into `areas`, and the named
 * infrastructure polygons additionally as `landmarks` (centroids). Idempotent: keyed on
 * (kind, name) / (category, name).
 */
class ImportSitemap extends Command
{
    protected $signature = 'sitemap:import {--path= : sitemap directory (default config hwrt.sitemap_path)}';

    protected $description = 'Import areas and landmarks from sitemap/features/*.geojson';

    private const FILES = [
        // file => [kind, colour, is landmark source]
        'project-development-envelope.geojson' => ['boundary', '#ffd400', false],
        'lease-boundaries.geojson' => ['lease', '#00b0ff', false],
        'project-boundaries-infrastructure.geojson' => ['infrastructure', '#ff5f1f', true],
    ];

    /** First non-blank of the naming fields the PR25 layers use (Layer = CAD layer = structure name). */
    private function nameFor(array $p): string
    {
        foreach (['RefName', 'Layer', 'Lease', 'Name', 'NAME', 'Description'] as $k) {
            $v = trim(preg_replace('/\s+/', ' ', (string) ($p[$k] ?? '')));
            if ($v !== '') {
                return ucwords(strtolower($v));
            }
        }

        return '';
    }

    public function handle(): int
    {
        $dir = rtrim($this->option('path') ?: config('hwrt.sitemap_path'), '/').'/features';
        $collections = [];

        foreach (self::FILES as $file => [$kind, $colour, $landmarks]) {
            $path = "{$dir}/{$file}";
            if (! is_file($path)) {
                $this->error("missing required map data: {$path}");

                return self::FAILURE;
            }

            $fc = json_decode((string) file_get_contents($path), true);
            if (! is_array($fc) || ! is_array($fc['features'] ?? null)) {
                $this->error("{$file} is not a valid GeoJSON feature collection");

                return self::FAILURE;
            }
            if (($fc['crs_epsg'] ?? 4326) !== 28350) {
                $this->error("{$file} is not EPSG:28350 — re-run sitemap/tools/fetch_arcgis.py features");

                return self::FAILURE;
            }

            foreach ($fc['features'] as $feature) {
                if (! is_array($feature)
                    || ! is_array($feature['geometry'] ?? null)
                    || ! is_array($feature['properties'] ?? [])) {
                    $this->error("{$file} contains a feature without valid geometry");

                    return self::FAILURE;
                }
            }

            $collections[$file] = [$fc, $kind, $colour, $landmarks];
        }

        [$nAreas, $nLm] = DB::transaction(function () use ($collections): array {
            $nAreas = $nLm = 0;
            foreach ($collections as $file => [$fc, $kind, $colour, $landmarks]) {
                foreach ($fc['features'] as $i => $f) {
                    $p = $f['properties'] ?? [];
                    $name = mb_substr($this->nameFor($p) ?: ucfirst($kind).' '.($i + 1), 0, 120);
                    Area::updateOrCreate(
                        ['kind' => $kind, 'name' => $name],
                        ['geometry' => $f['geometry'], 'colour' => $colour, 'source' => $fc['source'] ?? $file,
                            'source_ref' => (string) ($p['OBJECTID'] ?? $p['FID'] ?? $f['id'] ?? ''), 'sort_order' => $i, 'active' => true],
                    );
                    $nAreas++;
                    if ($landmarks) {
                        [$e, $n] = Geo::centroid($f['geometry']);
                        Landmark::updateOrCreate(
                            ['category' => 'infrastructure', 'name' => $name],
                            ['easting' => $e, 'northing' => $n, 'source' => $fc['source'] ?? $file, 'active' => true],
                        );
                        $nLm++;
                    }
                }
            }

            return [$nAreas, $nLm];
        });

        $this->info("areas: {$nAreas}, landmarks: {$nLm}");

        return self::SUCCESS;
    }
}
