<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_tickets_can_be_searched_by_title(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Printer is broken',
            'description' => 'Test',
        ]);

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Cannot access email',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('tickets.index', [
                'search' => 'Printer',
            ]));

        $response->assertOk();
        $response->assertSee('Printer is broken');
        $response->assertDontSee('Cannot access email');
    }

    public function test_tickets_can_be_filtered_by_status(): void
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
            ->get(route('tickets.index', [
                'status' => TicketStatus::Resolved->value,
            ]));

        $response->assertOk();
        $response->assertSee('Resolved ticket');
        $response->assertDontSee('New ticket');
    }

    public function test_tickets_can_be_filtered_by_priority(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Urgent ticket',
            'description' => 'Test',
            'priority' => TicketPriority::Urgent,
        ]);

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Medium ticket',
            'description' => 'Test',
            'priority' => TicketPriority::Medium,
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('tickets.index', [
                'priority' => TicketPriority::Urgent->value,
            ]));

        $response->assertOk();
        $response->assertSee('Urgent ticket');
        $response->assertDontSee('Medium ticket');
    }

    public function test_agent_can_filter_tickets_by_assignee(): void
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
            'title' => 'Assigned to first agent',
            'description' => 'Test',
        ]);

        Ticket::create([
            'created_by_id' => $requester->id,
            'assigned_to_id' => $otherAgent->id,
            'title' => 'Assigned to second agent',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('tickets.index', [
                'assignee' => (string) $otherAgent->id,
            ]));

        $response->assertOk();
        $response->assertSee('Assigned to second agent');
        $response->assertDontSee('Assigned to first agent');
    }

    public function test_unassigned_tickets_can_be_filtered(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Unassigned ticket',
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
            ->get(route('tickets.index', [
                'assignee' => 'unassigned',
            ]));

        $response->assertOk();
        $response->assertSee('Unassigned ticket');
        $response->assertDontSee('Assigned ticket');
    }

    public function test_requester_still_sees_only_own_tickets_when_filtering(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $otherRequester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'My urgent ticket',
            'description' => 'Test',
            'priority' => TicketPriority::Urgent,
        ]);

        Ticket::create([
            'created_by_id' => $otherRequester->id,
            'title' => 'Other urgent ticket',
            'description' => 'Test',
            'priority' => TicketPriority::Urgent,
        ]);

        $response = $this
            ->actingAs($requester)
            ->get(route('tickets.index', [
                'priority' => TicketPriority::Urgent->value,
            ]));

        $response->assertOk();
        $response->assertSee('My urgent ticket');
        $response->assertDontSee('Other urgent ticket');
    }

    public function test_multiple_filters_can_be_combined(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        Ticket::create([
            'created_by_id' => $requester->id,
            'assigned_to_id' => $agent->id,
            'title' => 'Printer urgent problem',
            'description' => 'Test',
            'status' => TicketStatus::InProgress,
            'priority' => TicketPriority::Urgent,
        ]);

        Ticket::create([
            'created_by_id' => $requester->id,
            'assigned_to_id' => $agent->id,
            'title' => 'Printer medium problem',
            'description' => 'Test',
            'status' => TicketStatus::InProgress,
            'priority' => TicketPriority::Medium,
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('tickets.index', [
                'search' => 'Printer',
                'status' => TicketStatus::InProgress->value,
                'priority' => TicketPriority::Urgent->value,
                'assignee' => (string) $agent->id,
            ]));

        $response->assertOk();
        $response->assertSee('Printer urgent problem');
        $response->assertDontSee('Printer medium problem');
    }

    public function test_invalid_status_filter_is_rejected(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('tickets.index', [
                'status' => 'invalid-status',
            ]));

        $response->assertSessionHasErrors('status');
    }

    public function test_invalid_priority_filter_is_rejected(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('tickets.index', [
                'priority' => 'invalid-priority',
            ]));

        $response->assertSessionHasErrors('priority');
    }

    public function test_filters_are_preserved_in_pagination_links(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        for ($i = 1; $i <= 16; $i++) {
            Ticket::create([
                'created_by_id' => $requester->id,
                'title' => "Printer ticket {$i}",
                'description' => 'Test',
                'status' => TicketStatus::New,
            ]);
        }

        $response = $this
            ->actingAs($agent)
            ->get(route('tickets.index', [
                'search' => 'Printer',
                'status' => TicketStatus::New->value,
            ]));

        $response->assertOk();

        $response->assertSee(
            'search=Printer',
            false
        );

        $response->assertSee(
            'status=new',
            false
        );
    }
}
