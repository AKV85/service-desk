<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_by_id' => User::factory()->requester(),
            'assigned_to_id' => null,
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'status' => TicketStatus::New,
            'priority' => TicketPriority::Medium,
            'resolved_at' => null,
            'closed_at' => null,
        ];
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn(array $attributes) => [
            'assigned_to_id' => $user->id,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => TicketStatus::InProgress,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => TicketStatus::Closed,
            'resolved_at' => now()->subHour(),
            'closed_at' => now(),
        ]);
    }

    public function lowPriority(): static
    {
        return $this->state(fn(array $attributes) => [
            'priority' => TicketPriority::Low,
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn(array $attributes) => [
            'priority' => TicketPriority::High,
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn(array $attributes) => [
            'priority' => TicketPriority::Urgent,
        ]);
    }
}
