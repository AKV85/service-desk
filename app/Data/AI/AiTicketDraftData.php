<?php

namespace App\Data\AI;

readonly class AiTicketDraftData
{
    public function __construct(
        public string $content,
        public string $type,
        public string $model,
        public ?string $externalId = null,
    ) {}
}
