<?php

namespace App\Data\AI;

readonly class AiCommentContextData
{
    public function __construct(
        public int $id,
        public string $body,
        public AiUserContextData $author,
        public ?string $createdAt,
    ) {}
}
