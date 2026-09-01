<?php

namespace App\Providers;

use App\Contracts\Integrations\JiraClient;
use App\Integrations\Jira\AtlassianJiraClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(JiraClient::class, AtlassianJiraClient::class);
    }

    public function boot(): void
    {
        //
    }
}
