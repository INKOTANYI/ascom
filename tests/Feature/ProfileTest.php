<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())->get('/profile')->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Test User', 'email' => 'Test@Example.com', 'phone' => '788123456', 'language' => 'rw',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $user->refresh();
        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('+250788123456', $user->phone);
        $this->assertSame('rw', $user->language);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', ['name' => 'Test User', 'email' => $user->email, 'language' => 'en'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_a_user_must_keep_a_way_to_sign_in(): void
    {
        $user = User::factory()->create(['username' => null]);

        $this->actingAs($user)->patch('/profile', ['name' => 'Test User', 'email' => '', 'phone' => '', 'language' => 'en'])
            ->assertSessionHasErrors('phone');
    }

    public function test_users_can_not_delete_their_own_account(): void
    {
        $this->actingAs(User::factory()->create())->delete('/profile')->assertStatus(405);
    }
}
