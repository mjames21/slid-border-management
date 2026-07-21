<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_redirects_to_login(): void
    {
        $response = $this->get('/register');

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Public registration is disabled. Administrators create user accounts.');
    }

    public function test_new_users_cannot_self_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseMissing(User::class, [
            'email' => 'test@example.com',
        ]);
    }
}
