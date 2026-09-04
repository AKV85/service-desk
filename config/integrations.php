<?php

return [
    'jira' => [
        'enabled' => env('JIRA_ENABLED', false),
        'base_url' => env('JIRA_BASE_URL'),
        'email' => env('JIRA_EMAIL'),
        'api_token' => env('JIRA_API_TOKEN'),
        'project_key' => env('JIRA_PROJECT_KEY'),
        'issue_type_id' => env('JIRA_ISSUE_TYPE_ID'),
    ],

    'github' => [
        'enabled' => env('GITHUB_INTEGRATION_ENABLED', false),
        'token' => env('GITHUB_TOKEN'),
        'repository' => env('GITHUB_REPOSITORY'),
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    ],

    'ai' => [
        'enabled' => env('AI_ENABLED', false),
        'provider' => env('AI_PROVIDER', 'openai'),

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL'),
        ],

        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL'),
        ],
    ],
];
