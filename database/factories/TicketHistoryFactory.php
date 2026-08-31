<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketHistory>
 */
class TicketHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'action' => 'status_changed',
            'old_values' => [
                'status' => TicketStatus::New->value,
            ],
            'new_values' => [
                'status' => TicketStatus::InProgress->value,
            ],
        ];
    }
}
