<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class RootRedirectTest extends TestCase
{
    public function test_guest_is_redirected_from_root_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_is_redirected_from_root_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/');

        $response->assertRedirect(route('dashboard'));
    }
}
