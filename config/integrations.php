<?php

return [
    'jira' => [
        'enabled' => env('JIRA_ENABLED', false),
        'base_url' => env('JIRA_BASE_URL') ?: null,
        'email' => env('JIRA_EMAIL') ?: null,
        'api_token' => env('JIRA_API_TOKEN') ?: null,
        'project_key' => env('JIRA_PROJECT_KEY') ?: null,
        'issue_type_id' => env('JIRA_ISSUE_TYPE_ID') ?: null,
    ],
];
