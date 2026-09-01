<?php

namespace App\Data\Integrations\Jira;

final readonly class CreateJiraIssueData
{
    public function __construct(
        public string $projectKey,
        public string $issueTypeId,
        public string $summary,
        public string $description,
    ) {}
}
