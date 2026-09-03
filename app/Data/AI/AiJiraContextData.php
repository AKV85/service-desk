<?php

namespace App\Data\AI;

readonly class AiJiraContextData
{
    public function __construct(
        public ?string $externalId,
        public ?string $issueKey,
        public ?string $url,
        public ?string $status,
        public ?string $externalUpdatedAt,
        public ?string $lastSyncedAt,
    ) {}
}
