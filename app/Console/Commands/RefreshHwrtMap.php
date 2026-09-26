<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Support\MapPackagePublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class RefreshHwrtMap extends Command
{
    protected $signature = 'hwrt:map-refresh
        {--force : Refresh regardless of configured cadence}
        {--scheduled : Only refresh when requested or due}';

    protected $description = 'Refresh the local HWRT map package from its configured public ArcGIS sources';

    public function handle(): int
    {
        $cadence = Setting::value('map.refresh_cadence', 'manual');
        $requested = Setting::value('map.refresh_requested_at');
        $lastSuccess = Setting::value('map.last_success_at');

        if ($this->option('scheduled') && ! $requested && ! $this->due($cadence, $lastSuccess)) {
            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $requested && $cadence === 'manual') {
            $this->info('Map refresh is manual-only and no refresh has been requested.');

            return self::SUCCESS;
        }

        $root = rtrim(config('hwrt.sitemap_path'), '/\\');
        $python = Setting::value('map.python', 'python3');
        $stage = null;
        $previousTarget = null;
        $published = false;

        Setting::put('map.last_attempt_at', now()->toIso8601String());
        Setting::put('map.last_status', 'running');
        Setting::put('map.last_error', '');

        try {
            $activePath = realpath($root);
            if (! is_link($root) || $activePath === false || ! is_dir($activePath)) {
                throw new \RuntimeException('The runtime map current path must be a valid symbolic link; refusing to modify the installed package.');
            }

            $versions = dirname($root).'/versions';
            if (! is_dir($versions) && ! mkdir($versions, 0775, true) && ! is_dir($versions)) {
                throw new \RuntimeException("Could not create {$versions}");
            }

            $stage = $versions.'/refresh-'.Str::uuid();
            if (! File::copyDirectory($activePath, $stage)) {
                throw new \RuntimeException('Could not copy the current map package into staging.');
            }

            $downloads = $stage.'/.refresh-downloads';
            if (! mkdir($downloads, 0775, true) && ! is_dir($downloads)) {
                throw new \RuntimeException("Could not create {$downloads}");
            }

            $service = trim((string) Setting::value('map.imagery_service_override', ''));
            if ($service === '') {
                $service = $this->discoverImageryService($python, $activePath);
            }

            $this->info("Imagery source: {$service}");

            $l17 = $downloads.'/site-cf_L17.jpg';
            $l16 = $downloads.'/site-cf_L16.jpg';
            $bbox = $this->currentSiteCfBbox($activePath);

            $this->info("Refresh footprint: {$bbox} (same Site C/F extent as current package)");

            $this->runProcess([$python, $activePath.'/tools/fetch_arcgis.py', 'imagery', $service, '17', '--bbox', $bbox, '--out', $l17]);
            $this->runProcess([$python, $activePath.'/tools/fetch_arcgis.py', 'imagery', $service, '16', '--bbox', $bbox, '--out', $l16]);

            $featureSources = [
                'project-boundaries-infrastructure.geojson' => Setting::value(
                    'map.source.infrastructure',
                    'https://services-ap1.arcgis.com/oOhFjgN0cUEZBHqy/arcgis/rest/services/PR25_BDY_ProjectBoundaries/FeatureServer/0'
                ),
                'project-development-envelope.geojson' => Setting::value(
                    'map.source.envelope',
                    'https://services-ap1.arcgis.com/oOhFjgN0cUEZBHqy/arcgis/rest/services/PR25_BDY_ProjectBoundaries/FeatureServer/3'
                ),
                'lease-boundaries.geojson' => Setting::value(
                    'map.source.leases',
                    'https://services-ap1.arcgis.com/oOhFjgN0cUEZBHqy/arcgis/rest/services/PR25_BDY_LeaseBoundaries/FeatureServer/0'
                ),
                'cadastre.geojson' => Setting::value(
                    'map.source.cadastre',
                    'https://services-ap1.arcgis.com/oOhFjgN0cUEZBHqy/arcgis/rest/services/PR25_ADM_Cadastre/FeatureServer/0'
                ),
            ];

            foreach ($featureSources as $file => $url) {
                $this->runProcess([
                    $python,
                    $activePath.'/tools/fetch_arcgis.py',
                    'features',
                    (string) $url,
                    '--out',
                    $downloads.'/'.$file,
                    '--sr',
                    '28350',
                ]);
            }

            // Prepare the complete replacement off-line; the active package stays untouched.
            $this->replaceCandidateRaster($l17, $stage.'/imagery/site-cf-2026-09-14_L17_0.25m.jpg');
            $this->replaceCandidateRaster($l16, $stage.'/imagery/site-cf-2026-09-14_L16_0.5m.jpg');

            foreach (array_keys($featureSources) as $file) {
                $this->replaceCandidateFile($downloads.'/'.$file, $stage.'/features/'.$file);
            }

            File::deleteDirectory($downloads);
            $this->runProcess([$python, $stage.'/tools/build_manifest.py'], $stage);
            $this->validateStagedPackage($stage, array_keys($featureSources));

            $previousTarget = app(MapPackagePublisher::class)->publish($root, $stage);
            $published = true;

            $exit = Artisan::call('sitemap:import', ['--path' => $root]);
            if ($exit !== self::SUCCESS) {
                throw new \RuntimeException('sitemap:import failed: '.trim(Artisan::output()));
            }
            $published = false;

            Setting::put('map.last_success_at', now()->toIso8601String());
            Setting::put('map.last_status', 'success');
            Setting::put('map.last_error', '');
            Setting::put('map.refresh_requested_at', '');
            Setting::put('map.last_imagery_service', $service);

            $this->info('HWRT local map package refreshed successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            if ($published && $previousTarget !== null) {
                try {
                    app(MapPackagePublisher::class)->restore($root, $previousTarget);
                    $published = false;
                } catch (Throwable $rollbackError) {
                    Log::channel('hwrt_errors')->critical('Map refresh rollback failed', [
                        'exception' => $rollbackError::class,
                        'message' => $rollbackError->getMessage(),
                    ]);
                    $e = new \RuntimeException(
                        $e->getMessage().' The previous map package could not be restored: '.$rollbackError->getMessage(),
                        0,
                        $e,
                    );
                }
            }
            if ($stage && ! $published && is_dir($stage)) {
                File::deleteDirectory($stage);
            }

            Setting::put('map.last_status', 'failed');
            Setting::put('map.last_error', $e->getMessage());
            Log::channel('hwrt_errors')->error('Map refresh failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function discoverImageryService(string $python, string $root): string
    {
        $query = (string) Setting::value('map.imagery_query', 'Imagery - Site C');
        $process = new Process([$python, $root.'/tools/fetch_arcgis.py', 'layers', '--grep', $query]);
        $process->setTimeout(120);
        $process->mustRun();

        $matches = [];
        foreach (preg_split('/\R/', trim($process->getOutput())) ?: [] as $line) {
            $parts = explode("\t", trim($line));
            $url = trim((string) end($parts));
            if (str_contains($url, '/MapServer')) {
                $matches[] = $url;
            }
        }

        if ($matches) {
            return $matches[0];
        }

        // Fallback to the source recorded with the packaged Site C/F imagery.
        $manifestPath = $root.'/manifest.json';
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            foreach ($manifest['rasters'] ?? [] as $raster) {
                $source = $raster['source'] ?? null;
                if (str_starts_with((string) ($raster['id'] ?? ''), 'site-cf')
                    && is_string($source)
                    && str_contains($source, '/MapServer')) {
                    return $source;
                }
            }
        }

        throw new \RuntimeException("Could not discover an ArcGIS imagery service matching '{$query}' and no packaged fallback source exists.");
    }

    private function due(string $cadence, ?string $lastSuccess): bool
    {
        if ($cadence === 'manual') {
            return false;
        }
        if (! $lastSuccess) {
            return true;
        }

        $last = \Illuminate\Support\Carbon::parse($lastSuccess);

        return match ($cadence) {
            'daily' => $last->lte(now()->subDay()),
            'weekly' => $last->lte(now()->subWeek()),
            'monthly' => $last->lte(now()->subMonth()),
            default => false,
        };
    }

    private function currentSiteCfBbox(string $root): string
    {
        $matches = glob($root.'/imagery/site-cf-*_L17_0.25m.json') ?: [];
        if (! $matches) {
            throw new \RuntimeException('Current Site C/F imagery sidecar is missing; refusing to change map coverage.');
        }

        sort($matches);
        $sidecar = end($matches);
        $json = json_decode((string) file_get_contents($sidecar), true);
        $e = $json['extent_mga50'] ?? null;
        foreach (['xmin', 'ymin', 'xmax', 'ymax'] as $key) {
            if (! isset($e[$key]) || ! is_numeric($e[$key])) {
                throw new \RuntimeException('Current Site C/F extent is invalid; refusing map refresh.');
            }
        }
        if ($e['xmax'] <= $e['xmin'] || $e['ymax'] <= $e['ymin']) {
            throw new \RuntimeException('Current Site C/F extent is empty or inverted; refusing map refresh.');
        }

        return implode(',', [$e['xmin'], $e['ymin'], $e['xmax'], $e['ymax']]);
    }

    private function validateStagedPackage(string $stage, array $featureFiles): void
    {
        $manifest = json_decode((string) @file_get_contents($stage.'/manifest.json'), true);
        if (! is_array($manifest) || ! is_array($manifest['rasters'] ?? null) || $manifest['rasters'] === []) {
            throw new \RuntimeException('The staged map manifest is missing or invalid.');
        }

        $rasterFiles = [];
        foreach ($manifest['rasters'] as $raster) {
            foreach (['file', 'world_file'] as $key) {
                $relativePath = $raster[$key] ?? null;
                if (! is_string($relativePath) || ! is_file($stage.'/'.$relativePath) || filesize($stage.'/'.$relativePath) === 0) {
                    throw new \RuntimeException("The staged map manifest references a missing or empty {$key}.");
                }
            }
            $rasterFiles[] = basename($raster['file']);

            $sidecar = $stage.'/'.preg_replace('/\.(jpg|png)$/i', '.json', $raster['file']);
            if (! is_file($sidecar) || filesize($sidecar) === 0) {
                throw new \RuntimeException("The staged raster sidecar is missing or empty: {$sidecar}");
            }
        }

        foreach (['site-cf-2026-09-14_L17_0.25m.jpg', 'site-cf-2026-09-14_L16_0.5m.jpg'] as $requiredRaster) {
            if (! in_array($requiredRaster, $rasterFiles, true)) {
                throw new \RuntimeException("The staged package does not contain {$requiredRaster}.");
            }
        }

        foreach ($featureFiles as $file) {
            $path = $stage.'/features/'.$file;
            $collection = json_decode((string) @file_get_contents($path), true);
            if (! is_array($collection)
                || ($collection['crs_epsg'] ?? null) !== 28350
                || ! is_array($collection['features'] ?? null)) {
                throw new \RuntimeException("The staged feature collection is invalid: {$path}");
            }
        }
    }

    private function runProcess(array $command, ?string $workingDirectory = null): void
    {
        $process = new Process($command, $workingDirectory ?: base_path(), null, null, 1800);
        $process->mustRun(function ($type, $buffer): void {
            $this->output->write($buffer);
        });
    }

    private function replaceCandidateRaster(string $from, string $to): void
    {
        $fromBase = preg_replace('/\.jpg$/', '', $from);
        $toBase = preg_replace('/\.jpg$/', '', $to);

        foreach (['.jpg', '.jgw', '.json'] as $ext) {
            $this->replaceCandidateFile($fromBase.$ext, $toBase.$ext);
        }
    }

    private function replaceCandidateFile(string $from, string $to): void
    {
        if (! is_file($from) || filesize($from) === 0) {
            throw new \RuntimeException("Refusing to install missing/empty map file: {$from}");
        }

        $new = $to.'.new';
        if (! copy($from, $new)) {
            throw new \RuntimeException("Could not stage {$to}");
        }
        if (! rename($new, $to)) {
            @unlink($new);
            throw new \RuntimeException("Could not replace {$to}");
        }
    }
}
