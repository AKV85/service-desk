<?php

namespace Tests\Feature\Integrations\AI;

use App\Data\Integrations\AI\AiRequestData;
use App\Exceptions\Integrations\IntegrationException;
use App\Integrations\AI\GroqAiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqAiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'integrations.ai.groq.api_key' => 'test-groq-api-key',
            'integrations.ai.groq.model' => 'openai/gpt-oss-20b',
        ]);

        Http::preventStrayRequests();
    }

    public function test_it_generates_ai_response(): void
    {
        Http::fake([
            'api.groq.com/openai/v1/responses' => Http::response([
                'id' => 'resp_groq_123',
                'model' => 'openai/gpt-oss-20b',
                'output' => [
                    [
                        'type' => 'message',
                        'id' => 'msg_123',
                        'status' => 'completed',
                        'role' => 'assistant',
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

        $client = app(GroqAiClient::class);

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
        $this->assertSame('openai/gpt-oss-20b', $result->model);
        $this->assertSame('resp_groq_123', $result->externalId);
        $this->assertSame(
            30,
            $result->metadata['usage']['total_tokens'],
        );

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.groq.com/openai/v1/responses'
                && $request->hasHeader(
                    'Authorization',
                    'Bearer test-groq-api-key',
                )
                && $request['model'] === 'openai/gpt-oss-20b'
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
            app(GroqAiClient::class)->generate(
                new AiRequestData(input: 'Test'),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertSame('groq', $exception->provider);
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
            app(GroqAiClient::class)->generate(
                new AiRequestData(input: 'Test'),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertSame('groq', $exception->provider);
            $this->assertSame('generate', $exception->operation);
            $this->assertTrue($exception->retryable);
        }
    }

    public function test_client_error_is_not_retryable(): void
    {
        Http::fake([
            '*' => Http::response([], 400),
        ]);

        try {
            app(GroqAiClient::class)->generate(
                new AiRequestData(input: 'Test'),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertSame('groq', $exception->provider);
            $this->assertSame('generate', $exception->operation);
            $this->assertFalse($exception->retryable);
        }
    }

    public function test_missing_api_key_is_not_retryable(): void
    {
        config([
            'integrations.ai.groq.api_key' => null,
        ]);

        try {
            app(GroqAiClient::class)->generate(
                new AiRequestData(input: 'Test'),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertSame('groq', $exception->provider);
            $this->assertSame('generate', $exception->operation);
            $this->assertFalse($exception->retryable);
            $this->assertStringContainsString(
                'api_key',
                $exception->getMessage(),
            );
        }

        Http::assertNothingSent();
    }

    public function test_missing_model_is_not_retryable(): void
    {
        config([
            'integrations.ai.groq.model' => null,
        ]);

        try {
            app(GroqAiClient::class)->generate(
                new AiRequestData(input: 'Test'),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertSame('groq', $exception->provider);
            $this->assertSame('generate', $exception->operation);
            $this->assertFalse($exception->retryable);
            $this->assertStringContainsString(
                'model',
                $exception->getMessage(),
            );
        }

        Http::assertNothingSent();
    }

    public function test_response_without_generated_text_fails(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'resp_groq_123',
                'model' => 'openai/gpt-oss-20b',
                'output' => [],
            ]),
        ]);

        try {
            app(GroqAiClient::class)->generate(
                new AiRequestData(input: 'Test'),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertSame('groq', $exception->provider);
            $this->assertSame('generate', $exception->operation);
            $this->assertFalse($exception->retryable);
        }
    }
}
