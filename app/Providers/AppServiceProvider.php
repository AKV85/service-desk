<?php

namespace App\Providers;

use App\Contracts\Integrations\AiClient;
use App\Contracts\Integrations\GitHubClient;
use App\Contracts\Integrations\JiraClient;
use App\Integrations\AI\GroqAiClient;
use App\Integrations\AI\OpenAiClient;
use App\Integrations\GitHub\GitHubApiClient;
use App\Integrations\Jira\AtlassianJiraClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            JiraClient::class,
            AtlassianJiraClient::class,
        );

        $this->app->bind(
            GitHubClient::class,
            GitHubApiClient::class,
        );

        $this->app->bind(
            AiClient::class,
            function ($app): AiClient {
                return match (config('integrations.ai.provider')) {
                    'openai' => $app->make(OpenAiClient::class),
                    'groq' => $app->make(GroqAiClient::class),
                    default => throw new \RuntimeException(
                        'Unsupported AI provider: '.config('integrations.ai.provider'),
                    ),
                };
            },
        );
    }

    public function boot(): void
    {
        //
    }
}
