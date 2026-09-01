<?php

namespace Tests\Feature\Models;

use App\Enums\Integrations\IntegrationWebhookStatus;
use App\Models\IntegrationWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationWebhookEventModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_event_casts_attributes(): void
    {
        $event = IntegrationWebhookEvent::create([
            'provider' => 'github',
            'external_event_id' => 'delivery-123',
            'event_type' => 'issues',
            'status' => IntegrationWebhookStatus::Pending,
            'payload' => [
                'action' => 'edited',
                'issue' => [
                    'id' => 123456,
                    'number' => 27,
                ],
            ],
            'received_at' => now(),
        ]);

        $event->refresh();

        $this->assertSame(
            IntegrationWebhookStatus::Pending,
            $event->status,
        );

        $this->assertSame('edited', $event->payload['action']);
        $this->assertSame(123456, $event->payload['issue']['id']);
        $this->assertNotNull($event->received_at);
        $this->assertNull($event->processed_at);
    }

    public function test_provider_and_external_event_id_must_be_unique_together(): void
    {
        IntegrationWebhookEvent::create([
            'provider' => 'github',
            'external_event_id' => 'delivery-123',
            'event_type' => 'issues',
            'status' => IntegrationWebhookStatus::Pending,
            'received_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        IntegrationWebhookEvent::create([
            'provider' => 'github',
            'external_event_id' => 'delivery-123',
            'event_type' => 'issues',
            'status' => IntegrationWebhookStatus::Pending,
            'received_at' => now(),
        ]);
    }

    public function test_same_external_event_id_can_exist_for_different_providers(): void
    {
        IntegrationWebhookEvent::create([
            'provider' => 'github',
            'external_event_id' => 'delivery-123',
            'event_type' => 'issues',
            'status' => IntegrationWebhookStatus::Pending,
            'received_at' => now(),
        ]);

        IntegrationWebhookEvent::create([
            'provider' => 'jira',
            'external_event_id' => 'delivery-123',
            'event_type' => 'issue_updated',
            'status' => IntegrationWebhookStatus::Pending,
            'received_at' => now(),
        ]);

        $this->assertDatabaseCount('integration_webhook_events', 2);
    }
}
