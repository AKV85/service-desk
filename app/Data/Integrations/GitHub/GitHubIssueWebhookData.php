<?php

namespace App\Data\Integrations\GitHub;

use DateTimeImmutable;

readonly class GitHubIssueWebhookData
{
    public function __construct(
        public string $action,
        public string $externalId,
        public string $repository,
        public int $resourceNumber,
        public string $url,
        public string $state,
        public DateTimeImmutable $updatedAt,
        public array $metadata = [],
    ) {}
}
