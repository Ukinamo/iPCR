<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        $queryAs = $request->query('as');

        if (is_string($queryAs) && $queryAs !== '') {
            if (UserRole::tryFrom($queryAs)) {
                $request->session()->put('login_portal_role', $queryAs);
            } else {
                $request->session()->forget('login_portal_role');
            }
        }

        $portalRole = $request->session()->get('login_portal_role');
        $loginPortal = null;

        if (is_string($portalRole) && UserRole::tryFrom($portalRole)) {
            $enum = UserRole::from($portalRole);
            $loginPortal = [
                'role' => $enum->value,
                'label' => $enum->label(),
            ];
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'loginPortal' => $loginPortal,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $request->session()->forget('login_portal_role');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
