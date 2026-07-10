<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function __construct(
        private readonly PasswordResetOtpService $passwordResetOtpService,
    ) {}

    /**
     * Display the password reset request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Send a one-time password to the user's email address.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->string('email')->lower()->value();

        $result = $this->passwordResetOtpService->sendOtp($email);

        $canonicalEmail = $result['email'] ?? $email;

        $redirect = redirect()
            ->route('password.reset', ['email' => $canonicalEmail])
            ->with('status', $this->statusMessage($result));

        if ($result !== null && isset($result['dev_otp'])) {
            $redirect->with('dev_otp', $result['dev_otp']);
        }

        return $redirect;
    }

    private function statusMessage(?array $result): string
    {
        if ($result !== null && ($result['throttled'] ?? false)) {
            $seconds = $result['retry_after'] ?? 30;

            return "A verification code was sent recently. Check your email, or wait {$seconds} seconds to request a new one.";
        }

        if ($result !== null && isset($result['dev_otp'])) {
            return 'Verification code generated. In local development the code is shown on the next screen because mail is set to log.';
        }

        return 'We emailed you a 6-digit verification code. Enter it below to reset your password.';
    }
}
