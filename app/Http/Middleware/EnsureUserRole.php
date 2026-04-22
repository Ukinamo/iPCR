<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Backed enum values e.g. employee, supervisor, administrator
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActive()) {
            abort(403);
        }

        $allowed = array_map(static fn (string $r) => UserRole::from($r), $roles);

        if (! in_array($user->role, $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }
}
