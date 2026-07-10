<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_request_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_otp_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response
            ->assertRedirect(route('password.reset', ['email' => $user->email]))
            ->assertSessionHas('status');

        Notification::assertSentTo($user, PasswordResetOtpNotification::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->get('/reset-password?email='.$user->email);

        $response->assertStatus(200);
    }

    public function test_password_can_be_reset_with_valid_otp(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, PasswordResetOtpNotification::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'email' => $user->email,
                'otp' => $notification->otp,
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('Password1!', $user->refresh()->password));
    }

    public function test_password_cannot_be_reset_with_invalid_otp(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        $response = $this->post('/reset-password', [
            'email' => $user->email,
            'otp' => '000000',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertSessionHasErrors('otp');
    }

    public function test_recent_otp_requests_are_throttled_without_error(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('password.reset', ['email' => $user->email]));

        Notification::assertSentToTimes($user, PasswordResetOtpNotification::class, 1);
    }
}
