<?php

declare(strict_types=1);

namespace CseLog;

/** Request/response helpers: input, JSON, redirects, CSRF, view rendering. */
final class Http
{
    public static function input(string $key, ?string $default = null): ?string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    public static function intInput(string $key, ?int $default = null): ?int
    {
        $value = self::input($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    public static function floatInput(string $key, ?float $default = null): ?float
    {
        $value = self::input($key);

        return $value === null || $value === '' ? $default : (float) $value;
    }

    /** @param array<string, mixed>|array<int, mixed> $data */
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

    public static function notFound(string $message = 'Not found'): never
    {
        http_response_code(404);
        self::render('error', ['title' => '404', 'message' => $message]);
        exit;
    }

    public static function forbidden(string $message = 'You do not have access to that.'): never
    {
        http_response_code(403);
        self::render('error', ['title' => 'Forbidden', 'message' => $message]);
        exit;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
            http_response_code(419);
            exit('CSRF token mismatch. Refresh the page and try again.');
        }
    }

    public static function flash(?string $message = null, string $type = 'ok'): ?array
    {
        if ($message !== null) {
            $_SESSION['flash'] = ['message' => $message, 'type' => $type];

            return null;
        }

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return $flash;
    }

    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = []): void
    {
        $data['currentUser'] = Auth::user();
        $data['flash'] ??= self::flash();

        extract($data, EXTR_SKIP);
        $viewFile = dirname(__DIR__) . '/views/' . $view . '.php';

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        if (($data['bare'] ?? false) === true) {
            echo $content;

            return;
        }

        require dirname(__DIR__) . '/views/layout.php';
    }

    public static function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }
}
