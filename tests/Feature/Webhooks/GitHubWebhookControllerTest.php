<?php

namespace Tests\Feature\Webhooks;

use App\Enums\Integrations\IntegrationWebhookStatus;
use App\Jobs\ProcessGitHubWebhookJob;
use App\Models\IntegrationWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GitHubWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'integrations.github.webhook_secret' => $this->secret,
        ]);
    }

    public function test_valid_webhook_is_accepted_and_persisted(): void
    {
        Queue::fake();

        $payload = [
            'action' => 'edited',
            'issue' => [
                'id' => 5313391602,
                'number' => 27,
                'html_url' => 'https://github.com/AKV85/service-desk/issues/27',
                'state' => 'open',
                'updated_at' => '2026-09-01T20:56:10Z',
                'title' => 'This must not be persisted',
                'body' => 'Neither should this.',
            ],
            'repository' => [
                'full_name' => 'AKV85/service-desk',
                'description' => 'This must not be persisted',
            ],
            'sender' => [
                'login' => 'AKV85',
            ],
        ];

        $response = $this->postGitHubWebhook(
            payload: $payload,
            deliveryId: 'delivery-123',
            eventType: 'issues',
        );

        $response
            ->assertAccepted()
            ->assertJsonPath(
                'data.status',
                IntegrationWebhookStatus::Pending->value,
            );

        $this->assertDatabaseHas('integration_webhook_events', [
            'provider' => 'github',
            'external_event_id' => 'delivery-123',
            'event_type' => 'issues',
            'status' => IntegrationWebhookStatus::Pending->value,
        ]);

        $event = IntegrationWebhookEvent::query()->firstOrFail();

        $this->assertSame(
            'edited',
            $event->payload['action'],
        );

        $this->assertSame(
            5313391602,
            $event->payload['issue']['id'],
        );

        $this->assertSame(
            27,
            $event->payload['issue']['number'],
        );

        $this->assertSame(
            'https://github.com/AKV85/service-desk/issues/27',
            $event->payload['issue']['html_url'],
        );

        $this->assertSame(
            'open',
            $event->payload['issue']['state'],
        );

        $this->assertSame(
            '2026-09-01T20:56:10Z',
            $event->payload['issue']['updated_at'],
        );

        $this->assertSame(
            'AKV85/service-desk',
            $event->payload['repository']['full_name'],
        );

        $this->assertArrayNotHasKey(
            'title',
            $event->payload['issue'],
        );

        $this->assertArrayNotHasKey(
            'body',
            $event->payload['issue'],
        );

        $this->assertArrayNotHasKey(
            'description',
            $event->payload['repository'],
        );

        $this->assertArrayNotHasKey(
            'sender',
            $event->payload,
        );

        $this->assertNotNull($event->received_at);

        Queue::assertPushed(
            ProcessGitHubWebhookJob::class,
            fn (ProcessGitHubWebhookJob $job): bool => $job->webhookEventId === $event->id,
        );
    }

    public function test_invalid_signature_is_rejected_and_not_persisted(): void
    {
        Queue::fake();

        $payload = [
            'action' => 'edited',
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $response = $this->call(
            method: 'POST',
            uri: '/api/webhooks/github',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
                'HTTP_X_GITHUB_DELIVERY' => 'delivery-123',
                'HTTP_X_GITHUB_EVENT' => 'issues',
            ],
            content: $json,
        );

        $response->assertUnauthorized();

        $this->assertDatabaseCount(
            'integration_webhook_events',
            0,
        );

        Queue::assertNothingPushed();
    }

    public function test_missing_delivery_id_is_rejected(): void
    {
        $response = $this->postGitHubWebhook(
            payload: ['action' => 'edited'],
            deliveryId: null,
            eventType: 'issues',
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'integration_webhook_events',
            0,
        );
    }

    public function test_missing_event_type_is_rejected(): void
    {
        $response = $this->postGitHubWebhook(
            payload: ['action' => 'edited'],
            deliveryId: 'delivery-123',
            eventType: null,
        );

        $response->assertUnprocessable();

        $this->assertDatabaseCount(
            'integration_webhook_events',
            0,
        );
    }

    public function test_duplicate_delivery_is_not_persisted_twice(): void
    {
        Queue::fake();

        $payload = [
            'action' => 'edited',
            'issue' => [
                'id' => 5313391602,
                'number' => 27,
            ],
        ];

        $firstResponse = $this->postGitHubWebhook(
            payload: $payload,
            deliveryId: 'delivery-123',
            eventType: 'issues',
        );

        $secondResponse = $this->postGitHubWebhook(
            payload: $payload,
            deliveryId: 'delivery-123',
            eventType: 'issues',
        );

        $firstResponse->assertAccepted();
        $secondResponse->assertOk();

        $this->assertDatabaseCount(
            'integration_webhook_events',
            1,
        );

        Queue::assertPushed(
            ProcessGitHubWebhookJob::class,
            1,
        );
    }

    public function test_unsupported_event_payload_is_not_persisted(): void
    {
        Queue::fake();

        $response = $this->postGitHubWebhook(
            payload: [
                'zen' => 'Keep it logically awesome.',
                'repository' => [
                    'full_name' => 'AKV85/service-desk',
                ],
                'sender' => [
                    'login' => 'AKV85',
                ],
            ],
            deliveryId: 'delivery-ping',
            eventType: 'ping',
        );

        $response->assertAccepted();

        $event = IntegrationWebhookEvent::query()
            ->where(
                'external_event_id',
                'delivery-ping',
            )
            ->firstOrFail();

        $this->assertSame([], $event->payload);

        Queue::assertPushed(
            ProcessGitHubWebhookJob::class,
            1,
        );
    }

    private function postGitHubWebhook(
        array $payload,
        ?string $deliveryId,
        ?string $eventType,
    ) {
        $json = json_encode(
            $payload,
            JSON_THROW_ON_ERROR,
        );

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $this->signature(
                $json,
            ),
        ];

        if ($deliveryId !== null) {
            $headers['HTTP_X_GITHUB_DELIVERY'] = $deliveryId;
        }

        if ($eventType !== null) {
            $headers['HTTP_X_GITHUB_EVENT'] = $eventType;
        }

        return $this->call(
            method: 'POST',
            uri: '/api/webhooks/github',
            server: $headers,
            content: $json,
        );
    }

    private function signature(string $payload): string
    {
        return 'sha256='.hash_hmac(
            'sha256',
            $payload,
            $this->secret,
        );
    }
}
