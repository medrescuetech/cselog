<?php

use Illuminate\Contracts\Console\Kernel;

set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, 'Database restore failed: '.$exception->getMessage()."\n");
    exit(1);
});

if ($argc !== 3 || ! str_starts_with($argv[2], '--confirm-database=')) {
    fwrite(STDERR, "Usage: php scripts/restore-hwrt-database.php BACKUP_FILE --confirm-database=NAME_OR_PATH\n");
    exit(2);
}

umask(0077);
$backupFile = $argv[1];
$confirmedDatabase = substr($argv[2], strlen('--confirm-database='));
if (! is_file($backupFile) || filesize($backupFile) === 0) {
    fwrite(STDERR, "Restore input must be a non-empty backup file.\n");
    exit(2);
}

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function restoreFailure(string $message): never
{
    throw new RuntimeException($message);
}

function restoreClientBinary(string $driver): string
{
    $override = getenv('HWRT_DB_CLIENT_BIN');
    $candidates = $override
        ? [$override]
        : ($driver === 'mariadb' ? ['mariadb', 'mysql'] : ['mysql', 'mariadb']);
    foreach ($candidates as $candidate) {
        if (str_contains($candidate, DIRECTORY_SEPARATOR) && is_executable($candidate)) {
            return $candidate;
        }
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            $path = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$candidate;
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }
    }
    restoreFailure('No compatible database client was found; set HWRT_DB_CLIENT_BIN to its path.');
}

function restoreDatabaseSettings(array $settings): array
{
    $url = $settings['url'] ?? null;
    if (is_string($url) && $url !== '') {
        $parts = parse_url($url);
        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['mysql', 'mariadb'], true)) {
            restoreFailure('Configured database URL is invalid or uses an unsupported driver.');
        }
        $settings['host'] = isset($parts['host']) ? rawurldecode($parts['host']) : ($settings['host'] ?? '');
        $settings['port'] = $parts['port'] ?? ($settings['port'] ?? '');
        $settings['username'] = isset($parts['user']) ? rawurldecode($parts['user']) : ($settings['username'] ?? '');
        $settings['password'] = isset($parts['pass']) ? rawurldecode($parts['pass']) : ($settings['password'] ?? '');
        $settings['database'] = isset($parts['path'])
            ? rawurldecode(ltrim($parts['path'], '/'))
            : ($settings['database'] ?? '');
        parse_str($parts['query'] ?? '', $query);
        if (isset($query['socket'])) {
            $settings['unix_socket'] = $query['socket'];
        }
    }
    return $settings;
}

function restoreOption(string $value): string
{
    if (preg_match('/[\x00-\x1f\x7f]/', $value)) {
        restoreFailure('Database connection settings contain unsupported control characters.');
    }
    return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
}

$connection = (string) config('database.default');
$settings = config("database.connections.$connection");
$driver = is_array($settings) ? ($settings['driver'] ?? '') : '';
if ($driver === 'sqlite') {
    $databasePath = $settings['database'] ?? '';
    if (strtolower(pathinfo($backupFile, PATHINFO_EXTENSION)) !== 'sqlite'
        || ! is_string($databasePath)
        || $databasePath === ''
        || $databasePath === ':memory:'
        || $confirmedDatabase !== $databasePath
        || ! is_file($databasePath)
        || ! class_exists(SQLite3::class)) {
        fwrite(STDERR, "SQLite restore requires a .sqlite backup, the exact configured file path confirmation, and the PHP SQLite3 extension.\n");
        exit(2);
    }
    if (file_exists($databasePath.'-wal') || file_exists($databasePath.'-shm')) {
        fwrite(STDERR, "SQLite WAL/SHM files exist; stop all application and scheduler processes and resolve them before restore.\n");
        exit(2);
    }

    $temporaryDatabase = $databasePath.'.restore-'.bin2hex(random_bytes(6));
    try {
        $source = new SQLite3($backupFile, SQLITE3_OPEN_READONLY);
        $check = $source->querySingle('PRAGMA integrity_check');
        if ($check !== 'ok') {
            restoreFailure('SQLite backup integrity check failed.');
        }
        $target = new SQLite3($temporaryDatabase, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $ok = $source->backup($target);
        $targetCheck = $target->querySingle('PRAGMA integrity_check');
        $source->close();
        $target->close();
        if (! $ok || $targetCheck !== 'ok' || ! rename($temporaryDatabase, $databasePath)) {
            restoreFailure('SQLite restore did not complete; the existing database was not replaced.');
        }
        chmod($databasePath, 0600);
    } finally {
        @unlink($temporaryDatabase);
    }

    fwrite(STDOUT, "SQLite database restore completed. Verify application data and migrations before reopening service.\n");
    exit(0);
}

if (! in_array($driver, ['mysql', 'mariadb'], true)
    || strtolower(pathinfo($backupFile, PATHINFO_EXTENSION)) !== 'sql') {
    fwrite(STDERR, "Restore file type does not match the configured database driver.\n");
    exit(2);
}
$settings = restoreDatabaseSettings($settings);
$database = (string) ($settings['database'] ?? '');
if ($database === '' || $confirmedDatabase !== $database) {
    fwrite(STDERR, "Confirmation must exactly match the configured database name; no restore was run.\n");
    exit(2);
}

$optionFile = tempnam(sys_get_temp_dir(), 'hwrt-db-client-');
if ($optionFile === false) {
    restoreFailure('Could not create a private temporary database-client file.');
}

try {
    $content = "[client]\n";
    foreach ([
        'host' => (string) ($settings['host'] ?? ''),
        'port' => (string) ($settings['port'] ?? ''),
        'user' => (string) ($settings['username'] ?? ''),
        'password' => (string) ($settings['password'] ?? ''),
        'socket' => (string) ($settings['unix_socket'] ?? ''),
    ] as $key => $value) {
        if ($value !== '') {
            $content .= "$key=".restoreOption($value)."\n";
        }
    }
    if (file_put_contents($optionFile, $content, LOCK_EX) === false || ! chmod($optionFile, 0600)) {
        restoreFailure('Could not protect the temporary database-client file.');
    }

    $command = [
        restoreClientBinary($driver),
        "--defaults-extra-file=$optionFile",
        $database,
    ];
    $process = proc_open($command, [
        0 => ['file', $backupFile, 'r'],
        1 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (! is_resource($process)) {
        restoreFailure('Could not start the database restore client.');
    }
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        restoreFailure("Database restore failed (exit code $exitCode); diagnostics suppressed to protect connection credentials.");
    }
} finally {
    @unlink($optionFile);
}

fwrite(STDOUT, "Database restore completed for the configured connection. Verify application data and migrations before reopening service.\n");
