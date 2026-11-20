<?php

namespace App\Console\Commands;

use App\Support\DemoDataset;
use Illuminate\Console\Command;

class SeedDemoDataset extends Command
{
    protected $signature = 'hwrt:demo-seed {--password : Prompt for the demo user password}';

    protected $description = 'Load clearly tagged HWRT demo users, locations, overlays and work entries';

    public function handle(): int
    {
        $password = $this->option('password') ? $this->secret('Demo user password (minimum 12 characters)') : null;
        if ($this->option('password') && (! is_string($password) || strlen($password) < 12)) {
            $this->error('Demo password must be at least 12 characters.');

            return self::FAILURE;
        }

        $summary = DemoDataset::seed($password);

        $this->info(sprintf(
            'Demo data loaded: %d users, %d locations, %d landmarks, %d sectors, %d entries.',
            $summary['users'], $summary['locations'], $summary['landmarks'], $summary['sectors'], $summary['entries'],
        ));
        $this->line('Demo users: demo.admin, demo.logger, demo.viewer');
        if ($summary['generated_password']) {
            $this->warn("Generated demo password: {$summary['generated_password']}");
            $this->warn('Store this securely, or reseed with --password / HWRT_DEMO_PASSWORD.');
        }

        return self::SUCCESS;
    }
}
