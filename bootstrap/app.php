<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsurePasswordChanged;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'password.changed' => EnsurePasswordChanged::class,
        ]);
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->report(function (\Throwable $e): void {
            $context = [
                'event_id' => (string) Str::uuid(),
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            try {
                $request = request();
                $context += [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'route' => $request->route()?->getName(),
                    'user_id' => $request->user()?->id,
                    'email' => $request->user()?->email,
                    'ip' => $request->ip(),
                ];
            } catch (\Throwable) {
                // Console/bootstrap exception: request context may not exist.
            }

            try {
                Log::channel('hwrt_errors')->error($e->getMessage(), $context);
            } catch (\Throwable $loggingError) {
                error_log(sprintf(
                    'HWRT exception logger unavailable (%s); original exception: %s in %s:%d',
                    $loggingError->getMessage(),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                ));
            }
        });
    })->create();
