<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordResetOtpService
{
    private const OTP_LENGTH = 6;

    private const EXPIRE_MINUTES = 10;

    private const THROTTLE_SECONDS = 30;

    /**
     * @return array{email: string, dev_otp?: string, throttled?: bool, retry_after?: int}|null
     */
    public function sendOtp(string $email): ?array
    {
        $user = $this->findUserByEmail($email);

        if ($user === null) {
            return null;
        }

        $email = $user->email;

        $existing = DB::table('password_reset_tokens')->where('email', $email)->first();

        if ($existing !== null && $this->wasRecentlySent($existing)) {
            return [
                'email' => $email,
                'throttled' => true,
                'retry_after' => $this->secondsUntilResend($existing),
            ];
        }

        $otp = $this->generateOtp();

        try {
            Notification::sendNow($user, new PasswordResetOtpNotification($otp, self::EXPIRE_MINUTES));
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'email' => [$this->mailFailureMessage()],
            ]);
        }

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($otp),
                'created_at' => now(),
            ],
        );

        $result = ['email' => $email];

        if ($this->shouldExposeDevOtp()) {
            $result['dev_otp'] = $otp;
        }

        return $result;
    }

    public function resetPassword(string $email, string $otp, string $password): void
    {
        $user = $this->findUserByEmail($email);

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => ['We could not find a user with that email address.'],
            ]);
        }

        $email = $user->email;

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if ($record === null || $this->isExpired($record)) {
            throw ValidationException::withMessages([
                'otp' => ['This verification code is invalid or has expired.'],
            ]);
        }

        if (! Hash::check($otp, $record->token)) {
            throw ValidationException::withMessages([
                'otp' => ['The verification code is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        event(new PasswordReset($user));
    }

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    private function isExpired(object $record): bool
    {
        $createdAt = $this->parseCreatedAt($record->created_at);

        if ($createdAt === null) {
            return true;
        }

        return $createdAt->lte(now()->subMinutes(self::EXPIRE_MINUTES));
    }

    private function wasRecentlySent(object $record): bool
    {
        $createdAt = $this->parseCreatedAt($record->created_at);

        if ($createdAt === null) {
            return false;
        }

        return $createdAt->greaterThan(now()->subSeconds(self::THROTTLE_SECONDS));
    }

    private function secondsUntilResend(object $record): int
    {
        $createdAt = $this->parseCreatedAt($record->created_at);

        if ($createdAt === null) {
            return self::THROTTLE_SECONDS;
        }

        $elapsed = $createdAt->diffInSeconds(now());

        return max(1, self::THROTTLE_SECONDS - $elapsed);
    }

    private function parseCreatedAt(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value);
    }

    private function findUserByEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first();
    }

    private function shouldExposeDevOtp(): bool
    {
        return app()->environment('local') && config('mail.default') === 'log';
    }

    private function mailFailureMessage(): string
    {
        if (config('mail.default') === 'smtp' && blank(env('MAIL_PASSWORD'))) {
            return 'Email is not configured yet. Add your SMTP password in the .env file (MAIL_PASSWORD).';
        }

        return 'We could not send the verification email. Please check your mail settings and try again.';
    }
}
