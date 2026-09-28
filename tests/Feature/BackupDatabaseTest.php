<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    public function test_sqlite_backup_contains_committed_data_and_uses_configured_database(): void
    {
        $directory = storage_path('framework/testing/hwrt-backup-'.bin2hex(random_bytes(4)));
        mkdir($directory, 0700, true);
        $source = $directory.'/source.sqlite';
        touch($source);
        $previous = config('database.connections.sqlite.database');
        $previousDefault = config('database.default');

        try {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $source]);
            DB::purge('sqlite');
            DB::connection('sqlite')->statement('CREATE TABLE backup_probe (value TEXT NOT NULL)');
            DB::connection('sqlite')->table('backup_probe')->insert(['value' => 'preserved']);

            $this->assertSame(0, Artisan::call('hwrt:backup-database', ['--output' => $directory]));
            $backups = glob($directory.'/hwrt-*.sqlite');
            $this->assertCount(1, $backups);
            $this->assertSame('preserved', (new PDO('sqlite:'.$backups[0]))
                ->query('SELECT value FROM backup_probe')->fetchColumn());
        } finally {
            DB::purge('sqlite');
            config(['database.default' => $previousDefault, 'database.connections.sqlite.database' => $previous]);
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
