<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = $user->isPending()
                ? 'Your registration is pending administrator approval.'
                : 'Your account is inactive. Contact an administrator.';

            return redirect()->route('login')->with('status', $message);
        }

        return $next($request);
    }
}
