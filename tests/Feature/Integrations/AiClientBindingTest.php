<?php

namespace Tests\Feature\Integrations;

use App\Contracts\Integrations\AiClient;
use App\Integrations\AI\OpenAiClient;
use Tests\TestCase;

class AiClientBindingTest extends TestCase
{
    public function test_ai_client_resolves_to_openai_client(): void
    {
        $client = app(AiClient::class);

        $this->assertInstanceOf(OpenAiClient::class, $client);
    }
}
