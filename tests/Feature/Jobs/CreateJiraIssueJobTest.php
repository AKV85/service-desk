<?php

namespace Tests\Feature\Jobs;

use App\Contracts\Integrations\JiraClient;
use App\Data\Integrations\Jira\CreateJiraIssueData;
use App\Data\Integrations\Jira\JiraIssueData;
use App\Enums\Integrations\IntegrationSyncStatus;
use App\Exceptions\Integrations\IntegrationException;
use App\Jobs\CreateJiraIssueJob;
use App\Models\Ticket;
use App\Services\Integrations\JiraIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateJiraIssueJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'integrations.jira.project_key' => 'SD',
            'integrations.jira.issue_type_id' => '10000',
        ]);
    }

    public function test_job_creates_jira_issue_for_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        $fakeClient = new class implements JiraClient
        {
            public function createIssue(CreateJiraIssueData $data): JiraIssueData
            {
                return new JiraIssueData(
                    externalId: '10001',
                    issueKey: 'SD-123',
                    url: 'https://example.atlassian.net/browse/SD-123',
                );
            }

            public function getIssue(string $externalId): JiraIssueData
            {
                throw new \LogicException('Not used in this test.');
            }
        };

        $this->app->instance(JiraClient::class, $fakeClient);

        (new CreateJiraIssueJob($ticket->id))
            ->handle(app(JiraIntegrationService::class));

        $this->assertDatabaseHas('jira_issues', [
            'ticket_id' => $ticket->id,
            'external_id' => '10001',
            'issue_key' => 'SD-123',
            'sync_status' => IntegrationSyncStatus::Synced->value,
        ]);
    }

    public function test_job_does_nothing_when_ticket_does_not_exist(): void
    {
        $job = new CreateJiraIssueJob(999999);

        $job->handle(
            app(JiraIntegrationService::class),
        );

        $this->assertDatabaseCount('jira_issues', 0);
    }

    public function test_job_does_nothing_when_ticket_is_soft_deleted(): void
    {
        $ticket = Ticket::factory()->create();

        $ticket->delete();

        $job = new CreateJiraIssueJob($ticket->id);

        $job->handle(
            app(JiraIntegrationService::class),
        );

        $this->assertDatabaseCount('jira_issues', 0);
    }

    public function test_job_rethrows_retryable_integration_exception(): void
    {
        $ticket = Ticket::factory()->create();

        $service = \Mockery::mock(
            JiraIntegrationService::class,
        );

        $service->shouldReceive('createIssueForTicket')
            ->once()
            ->andThrow(
                new IntegrationException(
                    message: 'Jira is temporarily unavailable.',
                    provider: 'jira',
                    operation: 'create_issue',
                    retryable: true,
                ),
            );

        $this->expectException(
            IntegrationException::class,
        );

        (new CreateJiraIssueJob($ticket->id))->handle($service);
    }

    public function test_job_fails_immediately_for_non_retryable_integration_exception(): void
    {
        $ticket = Ticket::factory()->create();

        $service = \Mockery::mock(
            JiraIntegrationService::class,
        );

        $service->shouldReceive('createIssueForTicket')
            ->once()
            ->andThrow(
                new IntegrationException(
                    message: 'Jira authentication failed.',
                    provider: 'jira',
                    operation: 'create_issue',
                    retryable: false,
                ),
            );

        $job = \Mockery::mock(CreateJiraIssueJob::class, [$ticket->id])
            ->makePartial();

        $job->shouldReceive('fail')
            ->once()
            ->with(\Mockery::type(
                IntegrationException::class,
            ));

        $job->handle($service);
    }

    public function test_job_is_unique_per_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        $job = new CreateJiraIssueJob($ticket->id);

        $this->assertSame(
            (string) $ticket->id,
            $job->uniqueId(),
        );

        $this->assertSame(3600, $job->uniqueFor);
    }
}
