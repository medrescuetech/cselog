<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BackupDatabase extends Command
{
    protected $signature = 'hwrt:backup-database {--output= : Directory outside the web root for the backup}';

    protected $description = 'Create a consistent, restorable backup of the configured HWRT database';

    public function handle(): int
    {
        $directory = (string) ($this->option('output') ?: storage_path('app/backups'));
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            $this->error('Cannot create backup directory.');
            return self::FAILURE;
        }
        $directory = realpath($directory);
        if (! $directory || str_starts_with($directory.DIRECTORY_SEPARATOR, realpath(public_path()).DIRECTORY_SEPARATOR)) {
            $this->error('Backup directory must be outside the public web root.');
            return self::FAILURE;
        }

        $connection = DB::connection();
        $config = $connection->getConfig();
        $driver = $config['driver'] ?? null;
        $name = 'hwrt-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));

        try {
            if ($driver === 'sqlite') {
                $source = $config['database'] ?? '';
                if ($source === ':memory:' || ! is_file($source)) {
                    throw new RuntimeException('Configured SQLite database is not a readable file.');
                }
                $target = $directory.DIRECTORY_SEPARATOR.$name.'.sqlite';
                $connection->getPdo()->exec('VACUUM INTO '.$connection->getPdo()->quote($target));
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $target = $directory.DIRECTORY_SEPARATOR.$name.'.sql.gz';
                $this->dumpMysql($config, $target);
            } else {
                throw new RuntimeException('No backup implementation for configured database driver.');
            }
            if (! is_file($target) || filesize($target) === 0) {
                throw new RuntimeException('Backup was empty.');
            }
            chmod($target, 0600);
            $this->info('Database backup: '.$target);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            if (isset($target) && is_file($target)) {
                unlink($target);
            }
            $this->error('Database backup failed: '.$e->getMessage());
            return self::FAILURE;
        }
    }

    private function dumpMysql(array $config, string $target): void
    {
        $binary = $config['driver'] === 'mariadb' ? 'mariadb-dump' : 'mysqldump';
        $database = (string) ($config['database'] ?? '');
        if ($database === '') {
            throw new RuntimeException('Database name is missing.');
        }

        // MySQL option files avoid placing credentials in process arguments or logs.
        $options = tempnam(dirname($target), '.hwrt-db-');
        if ($options === false) {
            throw new RuntimeException('Cannot create temporary database options file.');
        }
        chmod($options, 0600);
        $escape = static fn ($value) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $value).'"';
        $settings = "[client]\nuser=".$escape($config['username'] ?? '')."\npassword=".$escape($config['password'] ?? '')."\n";
        $settings .= ! empty($config['unix_socket'])
            ? 'socket='.$escape($config['unix_socket'])."\n"
            : 'host='.$escape($config['host'] ?? '127.0.0.1')."\nport=".$escape($config['port'] ?? 3306)."\n";

        try {
            if (file_put_contents($options, $settings) === false) {
                throw new RuntimeException('Cannot write temporary database options file.');
            }
            $args = [$binary, '--defaults-extra-file='.$options, '--single-transaction', '--quick', '--triggers', '--routines', '--events', '--databases', $database];
            $process = proc_open($args, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (! is_resource($process)) {
                throw new RuntimeException('Cannot start database dump.');
            }
            $archive = gzopen($target, 'wb9');
            if ($archive === false) {
                proc_terminate($process);
                throw new RuntimeException('Cannot create compressed backup.');
            }
            while (! feof($pipes[1])) {
                $chunk = fread($pipes[1], 65536);
                if ($chunk === false || ($chunk !== '' && gzwrite($archive, $chunk) === false)) {
                    proc_terminate($process);
                    throw new RuntimeException('Cannot write database backup.');
                }
            }
            gzclose($archive);
            fclose($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0) {
                throw new RuntimeException('Database dump failed: '.trim($error));
            }
        } finally {
            unlink($options);
        }
    }
}
