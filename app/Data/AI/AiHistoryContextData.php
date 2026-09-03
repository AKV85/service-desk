<?php

namespace App\Data\AI;

readonly class AiHistoryContextData
{
    public function __construct(
        public int $id,
        public string $action,
        public ?AiUserContextData $user,
        public ?array $oldValues,
        public ?array $newValues,
        public ?string $createdAt,
    ) {}
}
