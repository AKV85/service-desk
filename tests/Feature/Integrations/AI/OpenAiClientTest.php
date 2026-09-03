<?php

namespace Tests\Feature\Integrations\AI;

use App\Data\Integrations\AI\AiRequestData;
use App\Exceptions\Integrations\IntegrationException;
use App\Integrations\AI\OpenAiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'integrations.ai.api_key' => 'test-api-key',
            'integrations.ai.model' => 'test-model',
        ]);

        Http::preventStrayRequests();
    }

    public function test_it_generates_ai_response(): void
    {
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_123',
                'model' => 'test-model',
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => 'Restart the printer and check the network connection.',
                            ],
                        ],
                    ],
                ],
                'usage' => [
                    'input_tokens' => 20,
                    'output_tokens' => 10,
                    'total_tokens' => 30,
                ],
            ]),
        ]);

        $client = app(OpenAiClient::class);

        $result = $client->generate(
            new AiRequestData(
                input: 'The office printer is not working.',
                instructions: 'Provide a short troubleshooting suggestion.',
            ),
        );

        $this->assertSame(
            'Restart the printer and check the network connection.',
            $result->content,
        );
        $this->assertSame('test-model', $result->model);
        $this->assertSame('resp_123', $result->externalId);
        $this->assertSame(
            30,
            $result->metadata['usage']['total_tokens'],
        );

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.openai.com/v1/responses'
                && $request->hasHeader(
                    'Authorization',
                    'Bearer test-api-key',
                )
                && $request['model'] === 'test-model'
                && $request['input'] === 'The office printer is not working.'
                && $request['instructions']
                === 'Provide a short troubleshooting suggestion.'
                && $request['store'] === false;
        });
    }

    public function test_server_error_is_retryable(): void
    {
        Http::fake([
            '*' => Http::response([], 503),
        ]);

        try {
            app(OpenAiClient::class)->generate(
                new AiRequestData(input: 'Test'),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertSame('openai', $exception->provider);
            $this->assertSame('generate', $exception->operation);
            $this->assertTrue($exception->retryable);
        }
    }

    public function test_rate_limit_error_is_retryable(): void
    {
        Http::fake([
            '*' => Http::response([], 429),
        ]);

        try {
            app(OpenAiClient::class)->generate(
                new AiRequestData(input: 'Test'),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertTrue($exception->retryable);
        }
    }

    public function test_client_error_is_not_retryable(): void
    {
        Http::fake([
            '*' => Http::response([], 400),
        ]);

        try {
            app(OpenAiClient::class)->generate(
                new AiRequestData(input: 'Test'),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertFalse($exception->retryable);
        }
    }

    public function test_missing_configuration_is_not_retryable(): void
    {
        config([
            'integrations.ai.api_key' => null,
        ]);

        try {
            app(OpenAiClient::class)->generate(
                new AiRequestData(input: 'Test'),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertSame('openai', $exception->provider);
            $this->assertSame('generate', $exception->operation);
            $this->assertFalse($exception->retryable);
            $this->assertStringContainsString(
                'api_key',
                $exception->getMessage(),
            );
        }

        Http::assertNothingSent();
    }

    public function test_response_without_generated_text_fails(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'resp_123',
                'model' => 'test-model',
                'output' => [],
            ]),
        ]);

        try {
            app(OpenAiClient::class)->generate(
                new AiRequestData(input: 'Test'),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertSame('openai', $exception->provider);
            $this->assertSame('generate', $exception->operation);
            $this->assertFalse($exception->retryable);
        }
    }
}
