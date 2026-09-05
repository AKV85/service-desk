<?php

namespace Database\Seeders;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $requester = User::factory()->create([
            'name' => 'Demo Requester',
            'email' => 'requester@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Requester,
        ]);

        $agent = User::factory()->create([
            'name' => 'Demo Agent',
            'email' => 'agent@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Agent,
        ]);

        $admin = User::factory()->create([
            'name' => 'Demo Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
        ]);

        $newTicket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
            'title' => 'Printer is not working',
            'description' => 'The office printer shows an error and does not print.',
            'status' => TicketStatus::New,
            'priority' => TicketPriority::Medium,
        ]);

        $inProgressTicket = Ticket::factory()
            ->assignedTo($agent)
            ->inProgress()
            ->highPriority()
            ->create([
                'created_by_id' => $requester->id,
                'title' => 'Cannot access corporate email',
                'description' => 'Login to corporate email fails with an authentication error.',
            ]);

        $resolvedTicket = Ticket::factory()
            ->assignedTo($agent)
            ->resolved()
            ->create([
                'created_by_id' => $requester->id,
                'title' => 'VPN connection problem',
                'description' => 'VPN was disconnecting every few minutes.',
                'priority' => TicketPriority::Low,
            ]);

        $closedTicket = Ticket::factory()
            ->assignedTo($agent)
            ->closed()
            ->urgent()
            ->create([
                'created_by_id' => $requester->id,
                'title' => 'Warehouse scanner offline',
                'description' => 'Barcode scanner stopped connecting to the network.',
            ]);

        TicketComment::factory()->create([
            'ticket_id' => $inProgressTicket->id,
            'user_id' => $requester->id,
            'body' => 'The issue still happens after restarting Outlook.',
        ]);

        TicketComment::factory()->create([
            'ticket_id' => $inProgressTicket->id,
            'user_id' => $agent->id,
            'body' => 'I am checking the account configuration.',
        ]);

        TicketComment::factory()->create([
            'ticket_id' => $resolvedTicket->id,
            'user_id' => $agent->id,
            'body' => 'VPN profile was recreated and connection is stable now.',
        ]);

        TicketHistory::factory()->create([
            'ticket_id' => $inProgressTicket->id,
            'user_id' => $agent->id,
            'action' => 'assignee_changed',
            'old_values' => [
                'assigned_to_id' => null,
            ],
            'new_values' => [
                'assigned_to_id' => $agent->id,
            ],
            'created_at' => now()->subHours(3),
        ]);

        TicketHistory::factory()->create([
            'ticket_id' => $inProgressTicket->id,
            'user_id' => $agent->id,
            'action' => 'status_changed',
            'old_values' => [
                'status' => TicketStatus::New->value,
            ],
            'new_values' => [
                'status' => TicketStatus::InProgress->value,
            ],
            'created_at' => now()->subHours(2),
        ]);

        TicketHistory::factory()->create([
            'ticket_id' => $resolvedTicket->id,
            'user_id' => $agent->id,
            'action' => 'status_changed',
            'old_values' => [
                'status' => TicketStatus::InProgress->value,
            ],
            'new_values' => [
                'status' => TicketStatus::Resolved->value,
            ],
            'created_at' => now()->subHour(),
        ]);

        Ticket::factory()
            ->count(6)
            ->create([
                'created_by_id' => $requester->id,
            ]);

        Ticket::factory()
            ->count(4)
            ->assignedTo($agent)
            ->inProgress()
            ->create([
                'created_by_id' => $requester->id,
            ]);
    }
}
