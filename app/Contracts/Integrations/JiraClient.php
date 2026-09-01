<?php

namespace App\Contracts\Integrations;

use App\Data\Integrations\Jira\CreateJiraIssueData;
use App\Data\Integrations\Jira\JiraIssueData;

interface JiraClient
{
    public function createIssue(CreateJiraIssueData $data): JiraIssueData;

    public function getIssue(string $externalId): JiraIssueData;
}
