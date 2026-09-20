<?php

declare(strict_types=1);

namespace CseLog;

/**
 * File-backed login rate limit, keyed by username + client IP. Deliberately not
 * in the database: it must keep working while the DB is the thing under load,
 * and shared cPanel hosting gives no cache service.
 */
final class Throttle
{
    private const MAX_ATTEMPTS = 8;
    private const WINDOW_SECONDS = 900;
    private const LOCK_SECONDS = 900;

    public static function isLocked(string $username): bool
    {
        $state = self::read($username);

        return $state['locked_until'] > time();
    }

    public static function fail(string $username): void
    {
        $state = self::read($username);
        $now = time();

        if ($state['first_at'] + self::WINDOW_SECONDS < $now) {
            $state = ['attempts' => 0, 'first_at' => $now, 'locked_until' => 0];
        }

        $state['attempts']++;

        if ($state['attempts'] >= self::MAX_ATTEMPTS) {
            $state['locked_until'] = $now + self::LOCK_SECONDS;
            $state['attempts'] = 0;
            $state['first_at'] = $now;
        }

        self::write($username, $state);
    }

    public static function clear(string $username): void
    {
        $file = self::path($username);
        if (is_file($file)) {
            unlink($file);
        }
    }

    public static function retryInSeconds(string $username): int
    {
        return max(0, self::read($username)['locked_until'] - time());
    }

    /** @return array{attempts:int, first_at:int, locked_until:int} */
    private static function read(string $username): array
    {
        $file = self::path($username);
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return [
            'attempts' => (int) ($data['attempts'] ?? 0),
            'first_at' => (int) ($data['first_at'] ?? time()),
            'locked_until' => (int) ($data['locked_until'] ?? 0),
        ];
    }

    /** @param array{attempts:int, first_at:int, locked_until:int} $state */
    private static function write(string $username, array $state): void
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(self::path($username), json_encode($state), LOCK_EX);
    }

    private static function path(string $username): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');

        return self::dir() . '/' . hash('sha256', strtolower(trim($username)) . '|' . $ip) . '.json';
    }

    private static function dir(): string
    {
        return dirname(__DIR__) . '/storage/throttle';
    }
}
