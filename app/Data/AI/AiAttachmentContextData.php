<?php

namespace App\Data\AI;

readonly class AiAttachmentContextData
{
    public function __construct(
        public int $id,
        public string $originalName,
        public ?string $mimeType,
        public int $size,
        public AiUserContextData $uploadedBy,
        public ?string $createdAt,
    ) {}
}
