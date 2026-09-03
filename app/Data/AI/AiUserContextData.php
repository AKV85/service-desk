<?php

namespace App\Data\AI;

readonly class AiUserContextData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $role = null,
    ) {}
}
