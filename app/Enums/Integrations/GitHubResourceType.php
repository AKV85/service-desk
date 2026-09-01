<?php

namespace App\Enums\Integrations;

enum GitHubResourceType: string
{
    case Issue = 'issue';
    case PullRequest = 'pull_request';
    case Branch = 'branch';
    case Commit = 'commit';
}
