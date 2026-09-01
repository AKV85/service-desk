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
];