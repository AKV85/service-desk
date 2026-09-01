<?php

namespace Tests\Feature\Services;

use App\Jobs\CreateJiraIssueJob;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Services\TicketCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_ticket_and_notifies_creator(): void
    {
        Notification::fake();
        Queue::fake();

        config([
            'integrations.jira.enabled' => false,
        ]);

        $creator = User::factory()->create();

        $ticket = app(TicketCreationService::class)->create(
            creator: $creator,
            title: 'Printer is broken',
            description: 'The office printer stopped working.',
        );

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'created_by_id' => $creator->id,
            'title' => 'Printer is broken',
            'description' => 'The office printer stopped working.',
        ]);

        Notification::assertSentTo(
            $creator,
            TicketCreatedNotification::class,
        );

        Queue::assertNotPushed(CreateJiraIssueJob::class);
    }

    public function test_it_dispatches_jira_job_when_integration_is_enabled(): void
    {
        Notification::fake();
        Queue::fake();

        config([
            'integrations.jira.enabled' => true,
        ]);

        $creator = User::factory()->create();

        $ticket = app(TicketCreationService::class)->create(
            creator: $creator,
            title: 'Printer is broken',
            description: 'The office printer stopped working.',
        );

        Queue::assertPushed(
            CreateJiraIssueJob::class,
            fn(CreateJiraIssueJob $job): bool => $job->ticketId === $ticket->id,
        );
    }
}
