<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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

        $root = rtrim(config('hwrt.sitemap_path'), '/');
        $tmp = $root.'/.refresh';
        $python = Setting::value('map.python', 'python3');

        Setting::put('map.last_attempt_at', now()->toIso8601String());
        Setting::put('map.last_status', 'running');
        Setting::put('map.last_error', '');

        try {
            if (! is_dir($tmp) && ! mkdir($tmp, 0775, true) && ! is_dir($tmp)) {
                throw new \RuntimeException("Could not create {$tmp}");
            }

            $service = trim((string) Setting::value('map.imagery_service_override', ''));
            if ($service === '') {
                $service = $this->discoverImageryService($python, $root);
            }

            $this->info("Imagery source: {$service}");

            $l17 = $tmp.'/site-cf_L17.jpg';
            $l16 = $tmp.'/site-cf_L16.jpg';

            $this->run([$python, $root.'/tools/fetch_arcgis.py', 'imagery', $service, '17', '--out', $l17]);
            $this->run([$python, $root.'/tools/fetch_arcgis.py', 'imagery', $service, '16', '--out', $l16]);

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
                $this->run([
                    $python,
                    $root.'/tools/fetch_arcgis.py',
                    'features',
                    (string) $url,
                    '--out',
                    $tmp.'/'.$file,
                    '--sr',
                    '28350',
                ]);
            }

            // Only replace production files after every download has succeeded.
            $this->swapRaster($l17, $root.'/imagery/site-cf-2026-09-14_L17_0.25m.jpg');
            $this->swapRaster($l16, $root.'/imagery/site-cf-2026-09-14_L16_0.5m.jpg');

            foreach (array_keys($featureSources) as $file) {
                $this->atomicReplace($tmp.'/'.$file, $root.'/features/'.$file);
            }

            $this->run([$python, $root.'/tools/build_manifest.py']);

            $exit = Artisan::call('sitemap:import', ['--path' => $root]);
            if ($exit !== self::SUCCESS) {
                throw new \RuntimeException('sitemap:import failed: '.trim(Artisan::output()));
            }

            Setting::put('map.last_success_at', now()->toIso8601String());
            Setting::put('map.last_status', 'success');
            Setting::put('map.last_error', '');
            Setting::put('map.refresh_requested_at', '');
            Setting::put('map.last_imagery_service', $service);

            $this->info('HWRT local map package refreshed successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
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

        if (! $matches) {
            throw new \RuntimeException("Could not discover an ArcGIS imagery service matching '{$query}'.");
        }

        return $matches[0];
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

    private function run(array $command): void
    {
        $process = new Process($command, base_path(), null, null, 1800);
        $process->mustRun(function ($type, $buffer): void {
            $this->output->write($buffer);
        });
    }

    private function swapRaster(string $from, string $to): void
    {
        $fromBase = preg_replace('/\.jpg$/', '', $from);
        $toBase = preg_replace('/\.jpg$/', '', $to);

        foreach (['.jpg', '.jgw', '.json'] as $ext) {
            $this->atomicReplace($fromBase.$ext, $toBase.$ext);
        }
    }

    private function atomicReplace(string $from, string $to): void
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
