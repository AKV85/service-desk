<?php

namespace App\Data\AI;

readonly class AiGitHubContextData
{
    public function __construct(
        public string $type,
        public ?string $externalId,
        public string $repository,
        public ?int $resourceNumber,
        public ?string $reference,
        public ?string $url,
        public ?string $state,
        public ?string $externalUpdatedAt,
        public ?string $lastSyncedAt,
    ) {}
}
