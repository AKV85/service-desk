<?php

namespace Tests\Feature\Jobs;

use App\Exceptions\Integrations\IntegrationException;
use App\Jobs\CreateGitHubIssueJob;
use App\Models\Ticket;
use App\Services\Integrations\GitHubIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CreateGitHubIssueJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_github_issue_for_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        $service = Mockery::mock(GitHubIntegrationService::class);

        $service
            ->shouldReceive('createIssueForTicket')
            ->once()
            ->withArgs(fn (Ticket $givenTicket): bool => $givenTicket->is($ticket));

        $job = new CreateGitHubIssueJob(
            ticketId: $ticket->id,
            repository: 'AKV85/service-desk',
        );

        $job->handle($service);
    }

    public function test_job_does_nothing_when_ticket_does_not_exist(): void
    {
        $service = Mockery::mock(GitHubIntegrationService::class);

        $service
            ->shouldNotReceive('createIssueForTicket');

        $job = new CreateGitHubIssueJob(
            ticketId: 999999,
            repository: 'AKV85/service-desk',
        );

        $job->handle($service);

        $this->assertTrue(true);
    }

    public function test_job_does_nothing_for_soft_deleted_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        $ticket->delete();

        $service = Mockery::mock(GitHubIntegrationService::class);

        $service
            ->shouldNotReceive('createIssueForTicket');

        $job = new CreateGitHubIssueJob(
            ticketId: $ticket->id,
            repository: 'AKV85/service-desk',
        );

        $job->handle($service);

        $this->assertTrue(true);
    }

    public function test_retryable_exception_is_rethrown(): void
    {
        $ticket = Ticket::factory()->create();

        $exception = new IntegrationException(
            message: 'Temporary GitHub failure.',
            provider: 'github',
            operation: 'create_issue',
            retryable: true,
        );

        $service = Mockery::mock(GitHubIntegrationService::class);

        $service
            ->shouldReceive('createIssueForTicket')
            ->once()
            ->andThrow($exception);

        $job = new CreateGitHubIssueJob(
            ticketId: $ticket->id,
            repository: 'AKV85/service-desk',
        );

        $this->expectExceptionObject($exception);

        $job->handle($service);
    }

    public function test_non_retryable_exception_fails_job(): void
    {
        $ticket = Ticket::factory()->create();

        $exception = new IntegrationException(
            message: 'Invalid GitHub configuration.',
            provider: 'github',
            operation: 'create_issue',
            retryable: false,
        );

        $service = Mockery::mock(GitHubIntegrationService::class);

        $service
            ->shouldReceive('createIssueForTicket')
            ->once()
            ->andThrow($exception);

        $job = Mockery::mock(
            CreateGitHubIssueJob::class,
            [$ticket->id, 'AKV85/service-desk'],
        )->makePartial();

        $job
            ->shouldReceive('fail')
            ->once()
            ->with($exception);

        $job->handle($service);
    }

    public function test_unique_id_contains_ticket_and_repository(): void
    {
        $job = new CreateGitHubIssueJob(
            ticketId: 42,
            repository: 'AKV85/service-desk',
        );

        $this->assertSame(
            '42:AKV85/service-desk',
            $job->uniqueId(),
        );

        $this->assertSame(3600, $job->uniqueFor);

        $this->assertSame(
            [30, 120, 300],
            $job->backoff(),
        );
    }
}
