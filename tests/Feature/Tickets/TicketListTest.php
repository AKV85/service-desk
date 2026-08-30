<?php

namespace Tests\Feature\Tickets;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_ticket_list(): void
    {
        $response = $this->get('/tickets');

        $response->assertRedirect('/login');
    }

    public function test_requester_sees_only_own_tickets(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $anotherUser = User::factory()->create();

        Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Own ticket',
            'description' => 'Test',
        ]);

        Ticket::create([
            'created_by_id' => $anotherUser->id,
            'title' => 'Another user ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($requester)
            ->get('/tickets');

        $response->assertOk();
        $response->assertSee('Own ticket');
        $response->assertDontSee('Another user ticket');
    }

    public function test_agent_sees_all_tickets(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $firstRequester = User::factory()->create();
        $secondRequester = User::factory()->create();

        Ticket::create([
            'created_by_id' => $firstRequester->id,
            'title' => 'First ticket',
            'description' => 'Test',
        ]);

        Ticket::create([
            'created_by_id' => $secondRequester->id,
            'title' => 'Second ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($agent)
            ->get('/tickets');

        $response->assertOk();
        $response->assertSee('First ticket');
        $response->assertSee('Second ticket');
    }

    public function test_admin_sees_all_tickets(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $firstRequester = User::factory()->create();
        $secondRequester = User::factory()->create();

        Ticket::create([
            'created_by_id' => $firstRequester->id,
            'title' => 'First ticket',
            'description' => 'Test',
        ]);

        Ticket::create([
            'created_by_id' => $secondRequester->id,
            'title' => 'Second ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/tickets');

        $response->assertOk();
        $response->assertSee('First ticket');
        $response->assertSee('Second ticket');
    }

    public function test_tickets_are_sorted_by_created_at_descending(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $olderTicket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Older ticket',
            'description' => 'Test',
        ]);

        $olderTicket->created_at = now()->subDay();
        $olderTicket->save();

        $newestTicket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Newest ticket',
            'description' => 'Test',
        ]);

        $newestTicket->created_at = now();
        $newestTicket->save();

        $response = $this
            ->actingAs($agent)
            ->get('/tickets');

        $response->assertOk();

        $response->assertSeeInOrder([
            'Newest ticket',
            'Older ticket',
        ]);
    }

    public function test_unassigned_ticket_is_displayed_correctly(): void
    {
        $requester = User::factory()->create();

        Ticket::create([
            'created_by_id' => $requester->id,
            'assigned_to_id' => null,
            'title' => 'Unassigned ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($requester)
            ->get('/tickets');

        $response->assertOk();
        $response->assertSee('Unassigned');
    }

    public function test_ticket_list_is_paginated(): void
    {
        $requester = User::factory()->create();

        for ($i = 1; $i <= 16; $i++) {
            Ticket::create([
                'created_by_id' => $requester->id,
                'title' => "Ticket {$i}",
                'description' => 'Test',
            ]);
        }

        $response = $this
            ->actingAs($requester)
            ->get('/tickets');

        $response->assertOk();

        $this->assertCount(
            15,
            $response->viewData('tickets')->items()
        );
    }

    public function test_user_can_open_accessible_ticket_from_list(): void
    {
        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Openable ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($requester)
            ->get('/tickets');

        $response->assertOk();
        $response->assertSee(route('tickets.show', $ticket));
    }
}
