<?php

namespace App\Data\Integrations\GitHub;

readonly class CreateGitHubIssueData
{
    public function __construct(
        public string $repository,
        public string $title,
        public string $body,
    ) {}
}
