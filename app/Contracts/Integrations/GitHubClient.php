<?php

namespace App\Contracts\Integrations;

use App\Data\Integrations\GitHub\CreateGitHubIssueData;
use App\Data\Integrations\GitHub\GitHubResourceData;

interface GitHubClient
{
    public function createIssue(
        CreateGitHubIssueData $data
    ): GitHubResourceData;
}
