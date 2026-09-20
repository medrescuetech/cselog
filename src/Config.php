<?php

declare(strict_types=1);

namespace CseLog;

/** Reads configuration from the .env file at the project root (no dependencies). */
final class Config
{
    /** @var array<string, string>|null */
    private static ?array $values = null;

    public static function load(?string $path = null): void
    {
        $path ??= dirname(__DIR__) . '/.env';
        $values = [];

        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $value = trim($value);
                if (strlen($value) > 1 && ($value[0] === '"' || $value[0] === "'") && $value[0] === substr($value, -1)) {
                    $value = substr($value, 1, -1);
                }
                $values[trim($key)] = $value;
            }
        }

        self::$values = $values;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$values === null) {
            self::load();
        }

        $fromEnv = getenv($key);
        if ($fromEnv !== false && $fromEnv !== '') {
            return $fromEnv;
        }

        return self::$values[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    public static function isConfigured(): bool
    {
        return is_readable(dirname(__DIR__) . '/.env');
    }
}
