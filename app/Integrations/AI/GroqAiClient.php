<?php

namespace App\Integrations\AI;

use App\Contracts\Integrations\AiClient;
use App\Data\Integrations\AI\AiRequestData;
use App\Data\Integrations\AI\AiResponseData;
use App\Exceptions\Integrations\IntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;

final class GroqAiClient implements AiClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function generate(AiRequestData $request): AiResponseData
    {
        $this->ensureConfigured('generate');

        $payload = [
            'model' => (string) config('integrations.ai.groq.model'),
            'input' => $request->input,
            'store' => false,
        ];

        if ($request->instructions !== null) {
            $payload['instructions'] = $request->instructions;
        }

        try {
            $response = $this->http
                ->withToken(
                    (string) config('integrations.ai.groq.api_key'),
                )
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(30)
                ->post(
                    'https://api.groq.com/openai/v1/responses',
                    $payload,
                )
                ->throw();
        } catch (ConnectionException $exception) {
            throw new IntegrationException(
                message: 'Unable to connect to Groq.',
                provider: 'groq',
                operation: 'generate',
                retryable: true,
                previous: $exception,
            );
        } catch (RequestException $exception) {
            $status = $exception->response->status();

            throw new IntegrationException(
                message: "Groq generate request failed with HTTP {$status}.",
                provider: 'groq',
                operation: 'generate',
                retryable: $status === 429 || $status >= 500,
                previous: $exception,
            );
        }

        $content = collect($response->json('output', []))
            ->where('type', 'message')
            ->flatMap(fn (array $item) => $item['content'] ?? [])
            ->where('type', 'output_text')
            ->pluck('text')
            ->filter(fn ($text) => is_string($text) && filled($text))
            ->implode("\n");

        if (blank($content)) {
            throw new IntegrationException(
                message: 'Groq response does not contain generated text.',
                provider: 'groq',
                operation: 'generate',
                retryable: false,
            );
        }

        return new AiResponseData(
            content: $content,
            model: (string) $response->json('model'),
            externalId: $response->json('id'),
            metadata: [
                'usage' => $response->json('usage'),
            ],
        );
    }

    private function ensureConfigured(string $operation): void
    {
        $required = [
            'api_key',
            'model',
        ];

        foreach ($required as $key) {
            if (blank(config("integrations.ai.groq.{$key}"))) {
                throw new IntegrationException(
                    message: "Groq integration configuration is incomplete: {$key} is missing.",
                    provider: 'groq',
                    operation: $operation,
                    retryable: false,
                );
            }
        }
    }
}
