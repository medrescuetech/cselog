<?php

namespace App\Console\Commands;

use App\Support\BootstrapAdmin;
use Illuminate\Console\Command;

class EnsureBootstrapAdmin extends Command
{
    protected $signature = 'hwrt:bootstrap-admin';

    protected $description = 'Ensure the configured HWRT bootstrap Admin exists without changing existing accounts';

    public function handle(BootstrapAdmin $bootstrapAdmin): int
    {
        $admin = $bootstrapAdmin->ensure();
        $this->info($admin->wasRecentlyCreated
            ? "Created bootstrap Admin '{$admin->username}'. Change its password immediately."
            : "Bootstrap username '{$admin->username}' already exists; account left unchanged.");

        return self::SUCCESS;
    }
}
