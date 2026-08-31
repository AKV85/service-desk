<?php

namespace Tests\Feature\Authorization;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TicketPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_view_ticket_collection(): void
    {
        $requester = User::factory()->requester()->create();

        $this->assertTrue(
            Gate::forUser($requester)->allows('viewAny', Ticket::class)
        );
    }

    public function test_agent_can_view_ticket_collection(): void
    {
        $agent = User::factory()->agent()->create();

        $this->assertTrue(
            Gate::forUser($agent)->allows('viewAny', Ticket::class)
        );
    }

    public function test_admin_can_view_ticket_collection(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue(
            Gate::forUser($admin)->allows('viewAny', Ticket::class)
        );
    }

    public function test_requester_can_view_own_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Own ticket',
            'description' => 'Test',
        ]);

        $this->assertTrue(Gate::forUser($requester)->allows('view', $ticket));
    }

    public function test_requester_cannot_view_another_users_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $anotherUser = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $anotherUser->id,
            'title' => 'Another ticket',
            'description' => 'Test',
        ]);

        $this->assertFalse(Gate::forUser($requester)->allows('view', $ticket));
    }

    public function test_agent_can_view_any_ticket(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $this->assertTrue(Gate::forUser($agent)->allows('view', $ticket));
    }

    public function test_admin_can_view_any_ticket(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $ticket));
    }

    public function test_requester_cannot_assign_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $this->assertFalse(Gate::forUser($requester)->allows('assign', $ticket));
    }

    public function test_agent_can_assign_ticket(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $this->assertTrue(Gate::forUser($agent)->allows('assign', $ticket));
    }

    public function test_requester_can_change_status_only_for_own_resolved_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'status' => TicketStatus::Resolved,
        ]);

        $this->assertTrue(Gate::forUser($requester)->allows('changeStatus', $ticket));
    }

    public function test_requester_cannot_change_status_of_non_resolved_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'status' => TicketStatus::InProgress,
        ]);

        $this->assertFalse(Gate::forUser($requester)->allows('changeStatus', $ticket));
    }

    public function test_requester_cannot_change_ticket_priority(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $this->assertFalse(
            Gate::forUser($requester)->allows('changePriority', $ticket)
        );
    }

    public function test_agent_can_change_ticket_priority(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $this->assertTrue(
            Gate::forUser($agent)->allows('changePriority', $ticket)
        );
    }

    public function test_admin_can_assign_ticket(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $this->assertTrue(
            Gate::forUser($admin)->allows('assign', $ticket)
        );
    }

    public function test_admin_can_change_ticket_priority(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $this->assertTrue(
            Gate::forUser($admin)->allows('changePriority', $ticket)
        );
    }

    public function test_requester_cannot_change_status_of_another_users_resolved_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $anotherUser = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $anotherUser->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'status' => TicketStatus::Resolved,
        ]);

        $this->assertFalse(
            Gate::forUser($requester)->allows('changeStatus', $ticket)
        );
    }
}
