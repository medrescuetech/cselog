<?php

namespace App\Console\Commands;

use App\Support\DemoDataset;
use Illuminate\Console\Command;

class ClearDemoDataset extends Command
{
    protected $signature = 'hwrt:demo-clear {--force : Remove demo records without prompting}';

    protected $description = 'Remove only records created by hwrt:demo-seed';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Remove all [DEMO] records and demo users?', false)) {
            $this->info('Demo data left unchanged.');

            return self::SUCCESS;
        }

        $summary = DemoDataset::clear();
        $this->info('Demo data removed.');
        foreach (['entries', 'locations', 'landmarks', 'sectors', 'areas', 'work_tags', 'users'] as $key) {
            $this->line("  {$key}: {$summary[$key]}");
        }

        return self::SUCCESS;
    }
}
