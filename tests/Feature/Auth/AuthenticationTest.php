<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_users_can_sign_in_with_email_phone_or_username(): void
    {
        $user = User::factory()->create(['username' => 'jean.uwimana', 'phone' => '+250788123456']);

        foreach ([$user->email, '0788 123 456', '788123456', '+250788123456', 'jean.uwimana', 'JEAN.UWIMANA'] as $login) {
            $response = $this->post('/login', ['login' => $login, 'password' => 'password']);
            $this->assertAuthenticatedAs($user);
            $response->assertRedirect(route('dashboard', absolute: false));
            $this->post('/logout');
        }
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_disabled_accounts_can_not_sign_in(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', ['login' => $user->email, 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_there_is_no_public_registration(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
