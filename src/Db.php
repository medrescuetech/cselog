<?php

declare(strict_types=1);

namespace CseLog;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper. Supports MySQL (production, cPanel) and SQLite (local dev
 * and small single-site installs) from the same schema file.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $driver = Config::get('DB_CONNECTION', 'sqlite');

        if ($driver === 'sqlite') {
            $path = Config::get('DB_DATABASE', 'storage/cselog.sqlite');
            if (!str_starts_with($path, '/') && !preg_match('/^[A-Za-z]:/', $path)) {
                $path = dirname(__DIR__) . '/' . $path;
            }
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Config::get('DB_HOST', '127.0.0.1'),
                Config::get('DB_PORT', '3306'),
                Config::get('DB_DATABASE', 'cselog')
            );
            $pdo = new PDO($dsn, Config::get('DB_USERNAME', 'root'), Config::get('DB_PASSWORD', ''));
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        return self::$pdo = $pdo;
    }

    public static function driver(): string
    {
        return (string) self::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /** @param array<string|int, mixed> $params */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string|int, mixed> $params */
    public static function value(string $sql, array $params = []): mixed
    {
        $row = self::run($sql, $params)->fetch(PDO::FETCH_NUM);

        return $row === false ? null : $row[0];
    }

    /** @param array<string, mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $cols))
        );
        self::run($sql, $data);

        return (int) self::pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public static function update(string $table, array $data, array $where): void
    {
        $set = implode(', ', array_map(static fn (string $c): string => "$c = :s_$c", array_keys($data)));
        $cond = implode(' AND ', array_map(static fn (string $c): string => "$c = :w_$c", array_keys($where)));

        $params = [];
        foreach ($data as $k => $v) {
            $params["s_$k"] = $v;
        }
        foreach ($where as $k => $v) {
            $params["w_$k"] = $v;
        }

        self::run("UPDATE $table SET $set WHERE $cond", $params);
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    /** Applies schema/schema.sql, translating placeholders for the active driver. */
    public static function migrate(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__) . '/schema/schema.sql');
        $mysql = self::driver() === 'mysql';

        $sql = strtr($sql, [
            '{PK}' => $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
            '{FK}' => $mysql ? 'BIGINT UNSIGNED' : 'INTEGER',
            '{TABLE_OPTS}' => $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '',
        ]);

        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($mysql && preg_match('/^CREATE INDEX IF NOT EXISTS (\w+) ON (\w+)/i', $statement, $m) === 1) {
                // MySQL has no CREATE INDEX ... IF NOT EXISTS; emulate it.
                $exists = self::value(
                    'SELECT COUNT(*) FROM information_schema.statistics
                      WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                    [$m[2], $m[1]]
                );

                if ((int) $exists > 0) {
                    continue;
                }

                $statement = (string) preg_replace('/^CREATE INDEX IF NOT EXISTS /i', 'CREATE INDEX ', $statement);
            }

            self::pdo()->exec($statement);
        }
    }
}
