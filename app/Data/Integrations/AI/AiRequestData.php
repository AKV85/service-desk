<?php

namespace App\Data\Integrations\AI;

readonly class AiRequestData
{
    public function __construct(
        public string $input,
        public ?string $instructions = null,
    ) {}
}
