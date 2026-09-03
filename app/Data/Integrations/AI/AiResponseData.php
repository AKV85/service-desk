<?php

namespace App\Data\Integrations\AI;

readonly class AiResponseData
{
    public function __construct(
        public string $content,
        public string $model,
        public ?string $externalId = null,
        public array $metadata = [],
    ) {}
}
