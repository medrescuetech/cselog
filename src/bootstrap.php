<?php

declare(strict_types=1);

/** Autoloader + configuration bootstrap. No Composer required. */

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'CseLog\\')) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen('CseLog\\')));
    $file = __DIR__ . '/' . $relative . '.php';

    if (is_readable($file)) {
        require $file;
    }
});

CseLog\Config::load();

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

if (CseLog\Config::bool('APP_DEBUG')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}
