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

    public function test_database_seeder_does_not_create_demo_data_when_disabled(): void
    {
        config()->set('demo.enabled', false);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseMissing('users', [
            'email' => 'requester@example.com',
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'agent@example.com',
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'admin@example.com',
        ]);

        $this->assertSame(0, Ticket::count());
    }

    public function test_database_seeder_creates_demo_data_when_enabled(): void
    {
        config()->set('demo.enabled', true);

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
