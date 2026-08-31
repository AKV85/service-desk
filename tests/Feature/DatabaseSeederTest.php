<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'requester@example.com',
            'role' => UserRole::Requester->value,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'agent@example.com',
            'role' => UserRole::Agent->value,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'role' => UserRole::Admin->value,
        ]);

        $this->assertGreaterThan(0, Ticket::count());
        $this->assertGreaterThan(0, TicketComment::count());
        $this->assertGreaterThan(0, TicketHistory::count());

        $this->assertTrue(
            Ticket::query()->whereNull('assigned_to_id')->exists()
        );

        $this->assertTrue(
            Ticket::query()->whereNotNull('assigned_to_id')->exists()
        );
    }
}
