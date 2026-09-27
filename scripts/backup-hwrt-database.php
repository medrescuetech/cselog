<?php

use Illuminate\Contracts\Console\Kernel;

set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, 'Database backup failed: '.$exception->getMessage()."\n");
    exit(1);
});

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php scripts/backup-hwrt-database.php BACKUP_DIRECTORY\n");
    exit(2);
}

umask(0077);
$backupDirectory = rtrim($argv[1], DIRECTORY_SEPARATOR);
if (! is_dir($backupDirectory) || ! is_writable($backupDirectory)) {
    fwrite(STDERR, "Backup directory must already exist and be writable.\n");
    exit(2);
}

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function failBackup(string $message): never
{
    throw new RuntimeException($message);
}

function resolveDumpBinary(string $driver): string
{
    $override = getenv('HWRT_DB_DUMP_BIN');
    $candidates = $override
        ? [$override]
        : ($driver === 'mariadb' ? ['mariadb-dump', 'mysqldump'] : ['mysqldump', 'mariadb-dump']);

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

    failBackup('No compatible database dump utility was found; set HWRT_DB_DUMP_BIN to its path.');
}

function mysqlOption(string $value): string
{
    if (preg_match('/[\x00-\x1f\x7f]/', $value)) {
        failBackup('Database connection settings contain unsupported control characters.');
    }

    return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
}

function databaseSettings(array $settings): array
{
    $url = $settings['url'] ?? null;
    if (is_string($url) && $url !== '') {
        $parts = parse_url($url);
        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['mysql', 'mariadb'], true)) {
            failBackup('Configured database URL is invalid or uses an unsupported driver.');
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

$connection = (string) config('database.default');
$settings = config("database.connections.$connection");
if (! is_array($settings)) {
    failBackup('Configured database connection is unavailable.');
}
$driver = $settings['driver'] ?? '';
$stamp = gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));

if ($driver === 'sqlite') {
    $sourcePath = $settings['database'] ?? '';
    if (! is_string($sourcePath) || $sourcePath === '' || $sourcePath === ':memory:'
        || ! is_file($sourcePath) || ! class_exists(SQLite3::class)) {
        failBackup('SQLite backup requires an existing file database and the PHP SQLite3 extension.');
    }

    $destination = $backupDirectory.DIRECTORY_SEPARATOR."database-$stamp.sqlite";
    $temporary = $destination.'.part';
    try {
        $source = new SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
        $target = new SQLite3($temporary, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $ok = $source->backup($target);
        $source->close();
        $target->close();
        if (! $ok || ! is_file($temporary) || filesize($temporary) === 0
            || ! rename($temporary, $destination)) {
            failBackup('SQLite online backup did not complete.');
        }
        chmod($destination, 0600);
    } catch (Throwable) {
        @unlink($temporary);
        failBackup('SQLite online backup failed; no database changes were made.');
    }

    fwrite(STDOUT, "Database backup created: $destination (SQLite).\n");
    exit(0);
}

if (! in_array($driver, ['mysql', 'mariadb'], true)) {
    failBackup("Unsupported configured database driver '$driver'; no database changes were made.");
}

$settings = databaseSettings($settings);
$database = $settings['database'] ?? '';
if (! is_string($database) || $database === '') {
    failBackup('Configured database name is empty.');
}

$destination = $backupDirectory.DIRECTORY_SEPARATOR."database-$stamp.sql";
$temporary = $destination.'.part';
$optionFile = $backupDirectory.DIRECTORY_SEPARATOR.'.database-client-'.$stamp.'.cnf';
$clientSettings = [
    'host' => (string) ($settings['host'] ?? ''),
    'port' => (string) ($settings['port'] ?? ''),
    'user' => (string) ($settings['username'] ?? ''),
    'password' => (string) ($settings['password'] ?? ''),
    'socket' => (string) ($settings['unix_socket'] ?? ''),
];
$content = "[client]\n";
foreach ($clientSettings as $key => $value) {
    if ($value !== '') {
        $content .= "$key=".mysqlOption($value)."\n";
    }
}

try {
    if (file_put_contents($optionFile, $content, LOCK_EX) === false || ! chmod($optionFile, 0600)) {
        failBackup('Could not create a private database-client options file.');
    }

    $binary = resolveDumpBinary($driver);
    $command = [
        $binary,
        "--defaults-extra-file=$optionFile",
        '--single-transaction',
        '--quick',
        '--hex-blob',
        '--no-tablespaces',
        "--result-file=$temporary",
        '--',
        $database,
    ];
    $process = proc_open($command, [
        0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (! is_resource($process)) {
        failBackup('Could not start the database dump utility.');
    }
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0 || ! is_file($temporary) || filesize($temporary) === 0
        || ! rename($temporary, $destination)) {
        failBackup("Database dump failed (exit code $exitCode); diagnostics suppressed to protect connection credentials.");
    }
    chmod($destination, 0600);
} finally {
    @unlink($optionFile);
    @unlink($temporary);
}

fwrite(STDOUT, "Database backup created: $destination ($driver).\n");
