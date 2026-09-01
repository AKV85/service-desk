<?php

namespace App\Data\Integrations\Jira;

final readonly class JiraIssueData
{
    public function __construct(
        public string $externalId,
        public string $issueKey,
        public string $url,
        public ?string $status = null,
        public ?\DateTimeImmutable $updatedAt = null,
        public array $metadata = [],
    ) {}
}
