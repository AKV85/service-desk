<?php

namespace Tests\Feature\Integrations;

use App\Contracts\Integrations\AiClient;
use App\Integrations\AI\GroqAiClient;
use App\Integrations\AI\OpenAiClient;
use RuntimeException;
use Tests\TestCase;

class AiClientBindingTest extends TestCase
{
    public function test_ai_client_resolves_to_openai_client(): void
    {
        config([
            'integrations.ai.provider' => 'openai',
        ]);

        $client = app(AiClient::class);

        $this->assertInstanceOf(OpenAiClient::class, $client);
    }

    public function test_ai_client_resolves_to_groq_client(): void
    {
        config([
            'integrations.ai.provider' => 'groq',
        ]);

        $client = app(AiClient::class);

        $this->assertInstanceOf(GroqAiClient::class, $client);
    }

    public function test_unsupported_ai_provider_fails_explicitly(): void
    {
        config([
            'integrations.ai.provider' => 'unsupported',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Unsupported AI provider: unsupported',
        );

        app(AiClient::class);
    }
}
