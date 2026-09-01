<?php

namespace Tests\Feature\Models;

use App\Enums\Integrations\GitHubResourceType;
use App\Enums\Integrations\IntegrationSyncStatus;
use App\Models\GitHubResource;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GitHubResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_has_many_github_resources(): void
    {
        $ticket = Ticket::factory()->create();

        $issue = GitHubResource::create([
            'ticket_id' => $ticket->id,
            'resource_type' => GitHubResourceType::Issue,
            'external_id' => '123456',
            'repository' => 'AKV85/service-desk',
            'resource_number' => 42,
            'url' => 'https://github.com/AKV85/service-desk/issues/42',
            'sync_status' => IntegrationSyncStatus::Synced,
        ]);

        $pullRequest = GitHubResource::create([
            'ticket_id' => $ticket->id,
            'resource_type' => GitHubResourceType::PullRequest,
            'external_id' => '654321',
            'repository' => 'AKV85/service-desk',
            'resource_number' => 43,
            'url' => 'https://github.com/AKV85/service-desk/pull/43',
            'sync_status' => IntegrationSyncStatus::Synced,
        ]);

        $ticket->unsetRelation('githubResources');

        $this->assertCount(2, $ticket->githubResources);
        $this->assertTrue(
            $ticket->githubResources->contains($issue),
        );
        $this->assertTrue(
            $ticket->githubResources->contains($pullRequest),
        );
    }

    public function test_github_resource_belongs_to_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        $resource = GitHubResource::create([
            'ticket_id' => $ticket->id,
            'resource_type' => GitHubResourceType::Issue,
            'repository' => 'AKV85/service-desk',
            'resource_number' => 42,
            'url' => 'https://github.com/AKV85/service-desk/issues/42',
            'sync_status' => IntegrationSyncStatus::Pending,
        ]);

        $this->assertTrue(
            $resource->ticket->is($ticket),
        );
    }

    public function test_resource_type_is_cast_to_enum(): void
    {
        $ticket = Ticket::factory()->create();

        $resource = GitHubResource::create([
            'ticket_id' => $ticket->id,
            'resource_type' => GitHubResourceType::Branch,
            'repository' => 'AKV85/service-desk',
            'reference' => 'feature/SD-40-github-integration',
            'url' => 'https://github.com/AKV85/service-desk/tree/feature/SD-40-github-integration',
            'sync_status' => IntegrationSyncStatus::Pending,
        ]);

        $resource->refresh();

        $this->assertSame(
            GitHubResourceType::Branch,
            $resource->resource_type,
        );
    }

    public function test_sync_status_is_cast_to_enum(): void
    {
        $ticket = Ticket::factory()->create();

        $resource = GitHubResource::create([
            'ticket_id' => $ticket->id,
            'resource_type' => GitHubResourceType::Commit,
            'repository' => 'AKV85/service-desk',
            'reference' => 'abcdef1234567890',
            'url' => 'https://github.com/AKV85/service-desk/commit/abcdef1234567890',
            'sync_status' => IntegrationSyncStatus::Synced,
        ]);

        $resource->refresh();

        $this->assertSame(
            IntegrationSyncStatus::Synced,
            $resource->sync_status,
        );
    }

    public function test_metadata_is_cast_to_array(): void
    {
        $ticket = Ticket::factory()->create();

        $resource = GitHubResource::create([
            'ticket_id' => $ticket->id,
            'resource_type' => GitHubResourceType::Issue,
            'repository' => 'AKV85/service-desk',
            'resource_number' => 42,
            'url' => 'https://github.com/AKV85/service-desk/issues/42',
            'sync_status' => IntegrationSyncStatus::Synced,
            'metadata' => [
                'title' => 'GitHub integration',
                'state' => 'open',
            ],
        ]);

        $resource->refresh();

        $this->assertEquals(
            [
                'title' => 'GitHub integration',
                'state' => 'open',
            ],
            $resource->metadata,
        );
    }
}
