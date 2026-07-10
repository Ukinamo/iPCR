<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => UserRole::Employee->value,
            'account_status' => AccountStatus::Pending->value,
            'supervisor_id' => null,
        ]);
    }

    public function test_pending_users_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'pending@example.com',
            'password' => 'Password1!',
            'account_status' => AccountStatus::Pending,
        ]);

        $response = $this->post('/login', [
            'email' => 'pending@example.com',
            'password' => 'Password1!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_weak_passwords_are_rejected_on_registration(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }
}
