<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\Integrations\IntegrationWebhookStatus;
use App\Http\Controllers\Controller;
use App\Integrations\GitHub\Webhooks\GitHubWebhookSignatureVerifier;
use App\Jobs\ProcessGitHubWebhookJob;
use App\Models\IntegrationWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitHubWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        GitHubWebhookSignatureVerifier $signatureVerifier,
    ): JsonResponse {
        $signature = $request->header('X-Hub-Signature-256');
        $secret = config('integrations.github.webhook_secret');

        if (! $signatureVerifier->verify(
            payload: $request->getContent(),
            signature: $signature,
            secret: $secret,
        )) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        $deliveryId = $request->header('X-GitHub-Delivery');
        $eventType = $request->header('X-GitHub-Event');

        if (blank($deliveryId) || blank($eventType)) {
            return response()->json([
                'message' => 'Missing required GitHub webhook headers.',
            ], 422);
        }

        $event = IntegrationWebhookEvent::query()->createOrFirst(
            [
                'provider' => 'github',
                'external_event_id' => $deliveryId,
            ],
            [
                'event_type' => $eventType,
                'status' => IntegrationWebhookStatus::Pending,
                'payload' => $this->sanitizePayload(
                    payload: $request->json()->all(),
                    eventType: $eventType,
                ),
                'received_at' => now(),
            ],
        );
        if ($event->wasRecentlyCreated) {
            ProcessGitHubWebhookJob::dispatch($event->id);
        }

        return response()->json([
            'data' => [
                'id' => $event->id,
                'status' => $event->status->value,
            ],
        ], $event->wasRecentlyCreated ? 202 : 200);
    }

    private function sanitizePayload(
        array $payload,
        string $eventType,
    ): array {
        if ($eventType !== 'issues') {
            return [];
        }

        return [
            'action' => $payload['action'] ?? null,
            'issue' => [
                'id' => data_get($payload, 'issue.id'),
                'number' => data_get($payload, 'issue.number'),
                'html_url' => data_get($payload, 'issue.html_url'),
                'state' => data_get($payload, 'issue.state'),
                'updated_at' => data_get($payload, 'issue.updated_at'),
            ],
            'repository' => [
                'full_name' => data_get(
                    $payload,
                    'repository.full_name',
                ),
            ],
        ];
    }
}
