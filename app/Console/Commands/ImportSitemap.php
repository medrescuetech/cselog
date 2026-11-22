<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\Landmark;
use App\Models\MapSector;
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

    private const OPTIONAL_FILES = [
        // Named road corridors (user map). `site-c-roads.geojson` is the ArcGIS CAD design dump
        // refreshed by hwrt:map-refresh — kept as a separate kind so it doesn't swamp Site C roads.
        'site-c-named-roads.geojson' => ['road', '#8d9db5', false],
        'site-c-roads.geojson' => ['road_design', '#e2e8f0', false],
    ];

    /** Polygon collections loaded as admin-editable `map_sectors` (GA areas). */
    private const SECTOR_FILES = [
        'ga-areas.geojson' => ['#a3e635', 0.0],
    ];

    /** Point collections loaded as `landmarks` with marker fields from feature properties. */
    private const MARKER_FILES = [
        'site-c-markers.geojson',
    ];

    /** First non-blank of the naming fields the PR25 layers use (Layer = CAD layer = structure name). */
    private function nameFor(array $p): string
    {
        foreach (['RefName', 'Layer', 'Lease', 'Name', 'NAME', 'Description'] as $k) {
            $v = trim(preg_replace('/\s+/', ' ', (string) ($p[$k] ?? '')));
            if ($v !== '') {
                return preg_replace('/^ga(?=\d)/i', 'GA', ucwords(strtolower($v)));
            }
        }

        return '';
    }

    public function handle(): int
    {
        $dir = rtrim($this->option('path') ?: config('hwrt.sitemap_path'), '/').'/features';
        $collections = [];

        foreach (self::FILES + self::OPTIONAL_FILES as $file => [$kind, $colour, $landmarks]) {
            $path = "{$dir}/{$file}";
            if (! is_file($path)) {
                if (array_key_exists($file, self::OPTIONAL_FILES)) {
                    continue;
                }
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
                // Road layers are imported wholesale — drop stale rows from previous sources
                // (e.g. the CAD design dump) so only the current file's geometry survives.
                if (in_array($kind, ['road', 'road_design'], true)) {
                    Area::where('kind', $kind)->delete();
                }
                foreach ($fc['features'] as $i => $f) {
                    $p = $f['properties'] ?? [];
                    $label = $this->nameFor($p);
                    $name = mb_substr($kind === 'road'
                        ? ($label ?: 'Road').' '.($i + 1)
                        : ($label ?: ucfirst($kind).' '.($i + 1)), 0, 120);
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

        [$nSectors, $nMarkers] = $this->importOverlays($dir);

        $this->info("areas: {$nAreas}, landmarks: {$nLm}, sectors: {$nSectors}, markers: {$nMarkers}");

        return self::SUCCESS;
    }

    /** Optional overlay files: GA polygons -> map_sectors; marker points -> landmarks. */
    private function importOverlays(string $dir): array
    {
        $nSectors = $nMarkers = 0;

        foreach (self::SECTOR_FILES as $file => [$colour, $opacity]) {
            $fc = $this->readCollection("{$dir}/{$file}");
            if (! $fc) {
                continue;
            }
            foreach ($fc['features'] as $i => $f) {
                $name = mb_substr($this->nameFor($f['properties'] ?? []) ?: 'Sector '.($i + 1), 0, 120);
                MapSector::updateOrCreate(
                    ['name' => $name],
                    ['colour' => $colour, 'fill_opacity' => $opacity, 'geometry' => $f['geometry'],
                        'notes' => $fc['source'] ?? $file, 'sort_order' => $i, 'active' => true],
                );
                $nSectors++;
            }
        }

        foreach (self::MARKER_FILES as $file) {
            $fc = $this->readCollection("{$dir}/{$file}");
            if (! $fc) {
                continue;
            }
            foreach ($fc['features'] as $i => $f) {
                $p = $f['properties'] ?? [];
                [$e, $n] = $f['geometry']['coordinates'] ?? [null, null];
                if (! is_numeric($e) || ! is_numeric($n)) {
                    continue;
                }
                $name = mb_substr($this->nameFor($p) ?: 'Marker '.($i + 1), 0, 120);
                Landmark::updateOrCreate(
                    ['category' => (string) ($p['category'] ?? 'marker'), 'name' => $name],
                    [
                        'marker_type' => (string) ($p['marker_type'] ?? 'landmark'),
                        'colour' => (string) ($p['colour'] ?? '#ff5f1f'),
                        'easting' => (float) $e,
                        'northing' => (float) $n,
                        'label_visible' => (bool) ($p['label_visible'] ?? false),
                        'rotation_deg' => (int) ($p['rotation_deg'] ?? 0),
                        'source' => $fc['source'] ?? $file,
                        'active' => true,
                    ],
                );
                $nMarkers++;
            }
        }

        return [$nSectors, $nMarkers];
    }

    private function readCollection(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        $fc = json_decode((string) file_get_contents($path), true);
        if (! is_array($fc) || ! is_array($fc['features'] ?? null) || ($fc['crs_epsg'] ?? 4326) !== 28350) {
            $this->warn("skipping invalid overlay file: {$path}");

            return null;
        }

        return $fc;
    }
}
