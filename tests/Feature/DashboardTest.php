<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect('/login');
    }

    public function test_requester_sees_status_counts_only_for_own_tickets(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $otherRequester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'My new ticket',
            'description' => 'Test',
            'status' => TicketStatus::New,
        ]);

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'My resolved ticket',
            'description' => 'Test',
            'status' => TicketStatus::Resolved,
        ]);

        Ticket::create([
            'created_by_id' => $otherRequester->id,
            'title' => 'Other new ticket',
            'description' => 'Test',
            'status' => TicketStatus::New,
        ]);

        $response = $this
            ->actingAs($requester)
            ->get(route('dashboard'));

        $response->assertOk();

        $response->assertSee('New: 1');
        $response->assertSee('In progress: 0');
        $response->assertSee('Resolved: 1');
        $response->assertSee('Closed: 0');

        $response->assertSee('My new ticket');
        $response->assertSee('My resolved ticket');
        $response->assertDontSee('Other new ticket');
    }

    public function test_agent_sees_status_counts_for_all_tickets(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'New ticket',
            'description' => 'Test',
            'status' => TicketStatus::New,
        ]);

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Resolved ticket',
            'description' => 'Test',
            'status' => TicketStatus::Resolved,
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('New: 1');
        $response->assertSee('Resolved: 1');
    }

    public function test_admin_sees_status_counts_for_all_tickets(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $requester = User::factory()->create();

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'First closed ticket',
            'description' => 'Test',
            'status' => TicketStatus::Closed,
        ]);

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Second closed ticket',
            'description' => 'Test',
            'status' => TicketStatus::Closed,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Closed: 2');
    }

    public function test_agent_sees_tickets_assigned_to_them(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $otherAgent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        Ticket::create([
            'created_by_id' => $requester->id,
            'assigned_to_id' => $agent->id,
            'title' => 'Assigned to me',
            'description' => 'Test',
        ]);

        Ticket::create([
            'created_by_id' => $requester->id,
            'assigned_to_id' => $otherAgent->id,
            'title' => 'Assigned to someone else',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('dashboard'));

        $response->assertOk();

        $response->assertSee('Assigned to me');

        /*
         * "Assigned to someone else" can still appear in Recent tickets,
         * so we do not assertDontSee() it on the whole page.
         */
    }

    public function test_agent_sees_unassigned_ticket_count(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Unassigned one',
            'description' => 'Test',
        ]);

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Unassigned two',
            'description' => 'Test',
        ]);

        Ticket::create([
            'created_by_id' => $requester->id,
            'assigned_to_id' => $agent->id,
            'title' => 'Assigned ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Unassigned tickets: 2');
    }

    public function test_recent_tickets_are_displayed_newest_first(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Older dashboard ticket',
            'description' => 'Test',
            'created_at' => now()->subHour(),
        ]);

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Newest dashboard ticket',
            'description' => 'Test',
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('dashboard'));

        $response->assertOk();

        $response->assertSeeInOrder([
            'Newest dashboard ticket',
            'Older dashboard ticket',
        ]);
    }
}