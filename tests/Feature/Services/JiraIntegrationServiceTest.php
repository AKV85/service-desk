<?php

namespace Tests\Feature\Services;

use App\Contracts\Integrations\JiraClient;
use App\Data\Integrations\Jira\CreateJiraIssueData;
use App\Data\Integrations\Jira\JiraIssueData;
use App\Enums\Integrations\IntegrationSyncStatus;
use App\Models\Ticket;
use App\Services\Integrations\JiraIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JiraIntegrationServiceTest extends TestCase
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

    public function test_it_creates_and_persists_jira_issue_for_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'title' => 'Printer is not working',
            'description' => 'The office printer stopped working.',
        ]);

        $fakeClient = new class implements JiraClient
        {
            public int $createCalls = 0;

            public function createIssue(CreateJiraIssueData $data): JiraIssueData
            {
                $this->createCalls++;

                return new JiraIssueData(
                    externalId: '10001',
                    issueKey: 'SD-123',
                    url: 'https://example.atlassian.net/browse/SD-123',
                    status: 'To Do',
                    metadata: ['source' => 'test'],
                );
            }

            public function getIssue(string $externalId): JiraIssueData
            {
                throw new \LogicException('Not used in this test.');
            }
        };

        $this->app->instance(JiraClient::class, $fakeClient);

        $service = app(JiraIntegrationService::class);

        $jiraIssue = $service->createIssueForTicket($ticket);

        $this->assertSame(1, $fakeClient->createCalls);
        $this->assertSame($ticket->id, $jiraIssue->ticket_id);
        $this->assertSame('10001', $jiraIssue->external_id);
        $this->assertSame('SD-123', $jiraIssue->issue_key);
        $this->assertSame(
            'https://example.atlassian.net/browse/SD-123',
            $jiraIssue->url,
        );
        $this->assertSame('To Do', $jiraIssue->external_status);
        $this->assertSame(
            IntegrationSyncStatus::Synced,
            $jiraIssue->sync_status,
        );
        $this->assertSame(['source' => 'test'], $jiraIssue->metadata);
        $this->assertNotNull($jiraIssue->last_synced_at);

        $this->assertDatabaseHas('jira_issues', [
            'ticket_id' => $ticket->id,
            'external_id' => '10001',
            'issue_key' => 'SD-123',
            'sync_status' => IntegrationSyncStatus::Synced->value,
        ]);
    }

    public function test_it_does_not_create_duplicate_jira_issue(): void
    {
        $ticket = Ticket::factory()->create();

        $fakeClient = new class implements JiraClient
        {
            public int $createCalls = 0;

            public function createIssue(CreateJiraIssueData $data): JiraIssueData
            {
                $this->createCalls++;

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

        $service = app(JiraIntegrationService::class);

        $first = $service->createIssueForTicket($ticket);

        $ticket->unsetRelation('jiraIssue');

        $second = $service->createIssueForTicket($ticket);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $fakeClient->createCalls);
        $this->assertDatabaseCount('jira_issues', 1);
    }

    public function test_it_marks_jira_issue_as_failed_when_creation_fails(): void
    {
        $ticket = Ticket::factory()->create();

        $fakeClient = new class implements JiraClient
        {
            public function createIssue(CreateJiraIssueData $data): JiraIssueData
            {
                throw new \RuntimeException('Jira is unavailable.');
            }

            public function getIssue(string $externalId): JiraIssueData
            {
                throw new \LogicException('Not used in this test.');
            }
        };

        $this->app->instance(JiraClient::class, $fakeClient);

        $service = app(JiraIntegrationService::class);

        try {
            $service->createIssueForTicket($ticket);

            $this->fail('Expected Jira creation to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Jira is unavailable.', $exception->getMessage());
        }

        $this->assertDatabaseHas('jira_issues', [
            'ticket_id' => $ticket->id,
            'sync_status' => IntegrationSyncStatus::Failed->value,
            'last_error' => 'Jira is unavailable.',
        ]);
    }

    public function test_it_retries_creation_for_failed_jira_issue(): void
    {
        $ticket = Ticket::factory()->create();

        $jiraIssue = $ticket->jiraIssue()->create([
            'sync_status' => IntegrationSyncStatus::Failed,
            'last_error' => 'Previous Jira failure.',
        ]);

        $fakeClient = new class implements JiraClient
        {
            public int $createCalls = 0;

            public function createIssue(CreateJiraIssueData $data): JiraIssueData
            {
                $this->createCalls++;

                return new JiraIssueData(
                    externalId: '10001',
                    issueKey: 'SD-123',
                    url: 'https://example.atlassian.net/browse/SD-123',
                    status: 'To Do',
                );
            }

            public function getIssue(string $externalId): JiraIssueData
            {
                throw new \LogicException('Not used in this test.');
            }
        };

        $this->app->instance(JiraClient::class, $fakeClient);

        $service = app(JiraIntegrationService::class);

        $result = $service->createIssueForTicket($ticket);

        $this->assertSame(1, $fakeClient->createCalls);
        $this->assertSame($jiraIssue->id, $result->id);
        $this->assertSame(
            IntegrationSyncStatus::Synced,
            $result->sync_status,
        );
        $this->assertSame('10001', $result->external_id);
        $this->assertSame('SD-123', $result->issue_key);
        $this->assertNull($result->last_error);

        $this->assertDatabaseCount('jira_issues', 1);
    }
}
