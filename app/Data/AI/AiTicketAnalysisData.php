<?php

namespace App\Data\AI;

readonly class AiTicketAnalysisData
{
    /**
     * @param  list<string>  $suggestedActions
     * @param  list<string>  $observations
     * @param  list<string>  $developmentContext
     */
    public function __construct(
        public string $summary,
        public ?string $likelyCause,
        public array $suggestedActions,
        public array $observations,
        public array $developmentContext,
        public string $model,
        public ?string $externalId = null,
    ) {}
}
