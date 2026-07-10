<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function __construct(
        private readonly PasswordResetOtpService $passwordResetOtpService,
    ) {}

    /**
     * Display the password reset view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->string('email')->lower()->value() ?: old('email'),
            'status' => session('status'),
            'devOtp' => session('dev_otp'),
        ]);
    }

    /**
     * Handle an incoming new password request.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $this->passwordResetOtpService->resetPassword(
            $validated['email'],
            $validated['otp'],
            $validated['password'],
        );

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset. You may now sign in.');
    }
}
