<?php

declare(strict_types=1);

namespace CseLog;

final class Auth
{
    private const ROLES = ['viewer' => 1, 'logger' => 2, 'supervisor' => 3, 'admin' => 4];

    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => self::isHttps(),
            ]);
            session_start();
        }
    }

    /**
     * TLS is commonly terminated by a proxy on cPanel, so $_SERVER['HTTPS'] is
     * not enough on its own. FORCE_HTTPS=true pins it for hosts that forward
     * nothing useful.
     */
    private static function isHttps(): bool
    {
        if (Config::bool('FORCE_HTTPS', false)) {
            return true;
        }

        if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            return true;
        }

        return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $id = $_SESSION['user_id'] ?? null;
        if ($id === null) {
            return null;
        }

        $user = Db::first('SELECT * FROM users WHERE id = ? AND active = 1', [$id]);

        return self::$user = $user;
    }

    public static function attempt(string $username, string $password): bool
    {
        $username = strtolower(trim($username));

        if (Throttle::isLocked($username)) {
            return false;
        }

        $user = Db::first('SELECT * FROM users WHERE username = ? AND active = 1', [$username]);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            Throttle::fail($username);

            return false;
        }

        Throttle::clear($username);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        self::$user = $user;
        Db::update('users', ['last_login_at' => gmdate('Y-m-d H:i:s')], ['id' => $user['id']]);

        return true;
    }

    public static function logout(): void
    {
        self::$user = null;
        $_SESSION = [];
        session_destroy();
    }

    public static function can(string $role): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }

        return (self::ROLES[$user['role']] ?? 0) >= (self::ROLES[$role] ?? 99);
    }

    public static function requireRole(string $role): void
    {
        if (self::user() === null) {
            $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '/';
            Http::redirect('/login');
        }

        if (!self::can($role)) {
            Http::forbidden('This action needs the ' . $role . ' role.');
        }
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    /** @return array<int, string> */
    public static function roles(): array
    {
        return array_keys(self::ROLES);
    }
}
