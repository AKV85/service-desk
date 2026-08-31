<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_is_redirected_to_dashboard_after_login(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_is_redirected_to_login_after_logout(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/logout');

        $response->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
