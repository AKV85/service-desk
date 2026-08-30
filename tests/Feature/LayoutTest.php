<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_displays_navigation_and_user_information(): void
    {
        $user = User::factory()->create([
            'name' => 'Andrej Test',
            'role' => UserRole::Agent,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();

        $response->assertSee('Dashboard');
        $response->assertSee('Tickets');
        $response->assertSee('Create ticket');
        $response->assertSee('Andrej Test');
        $response->assertSee('agent');
        $response->assertSee('Logout');
    }

    public function test_ticket_pages_use_shared_layout(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('tickets.create'));

        $response->assertOk();
        $response->assertSee('Service Desk');
        $response->assertSee('Dashboard');
        $response->assertSee('Tickets');
    }
}
