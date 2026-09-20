<?php

declare(strict_types=1);

namespace CseLog;

/** Minimal pattern router: '/entries/{id}/close' style placeholders. */
final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable, role: string}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler, string $role = 'viewer'): void
    {
        $this->add('GET', $pattern, $handler, $role);
    }

    public function post(string $pattern, callable $handler, string $role = 'logger'): void
    {
        $this->add('POST', $pattern, $handler, $role);
    }

    private function add(string $method, string $pattern, callable $handler, string $role): void
    {
        $this->routes[] = ['method' => $method, 'pattern' => $pattern, 'handler' => $handler, 'role' => $role];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = '/' . trim(parse_url($path, PHP_URL_PATH) ?: '/', '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $route['pattern']) . '$#';
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            if ($route['role'] !== 'public') {
                Auth::requireRole($route['role']);
            }
            if ($method === 'POST' && $route['role'] !== 'public') {
                Http::verifyCsrf();
            }

            ($route['handler'])($params);

            return;
        }

        Http::notFound();
    }
}
