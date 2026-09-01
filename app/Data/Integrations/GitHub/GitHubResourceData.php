<?php

namespace App\Data\Integrations\GitHub;

use App\Enums\Integrations\GitHubResourceType;
use DateTimeImmutable;

readonly class GitHubResourceData
{
    public function __construct(
        public GitHubResourceType $type,
        public string $externalId,
        public string $repository,
        public ?int $resourceNumber,
        public ?string $reference,
        public string $url,
        public ?string $title,
        public ?string $state,
        public ?DateTimeImmutable $updatedAt,
        public array $metadata = [],
    ) {}
}
