<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();
        if (! $user || ! $user->active || ! $user->atLeast($role)) {
            abort(403);
        }

        return $next($request);
    }
}
