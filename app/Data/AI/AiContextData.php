<?php

namespace App\Data\AI;

readonly class AiContextData
{
    /**
     * @param  list<AiCommentContextData>  $comments
     * @param  list<AiHistoryContextData>  $history
     * @param  list<AiAttachmentContextData>  $attachments
     * @param  list<AiGitHubContextData>  $githubResources
     */
    public function __construct(
        public AiTicketContextData $ticket,
        public array $comments,
        public array $history,
        public array $attachments,
        public ?AiJiraContextData $jiraIssue,
        public array $githubResources,
    ) {}
}
