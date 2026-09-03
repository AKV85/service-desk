<?php

namespace App\Data\AI;

readonly class AiTicketContextData
{
    public function __construct(
        public int $id,
        public string $title,
        public string $description,
        public string $status,
        public string $priority,
        public AiUserContextData $creator,
        public ?AiUserContextData $assignee,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $resolvedAt,
        public ?string $closedAt,
    ) {}
}
