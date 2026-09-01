<?php

namespace App\Jobs;

use App\Exceptions\Integrations\IntegrationException;
use App\Models\Ticket;
use App\Services\Integrations\GitHubIntegrationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateGitHubIssueJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $ticketId,
        public readonly string $repository,
    ) {}

    public function uniqueId(): string
    {
        return $this->ticketId.':'.$this->repository;
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(
        GitHubIntegrationService $githubIntegrationService
    ): void {
        $ticket = Ticket::query()->find($this->ticketId);

        if ($ticket === null) {
            return;
        }

        try {
            $githubIntegrationService->createIssueForTicket($ticket);
        } catch (IntegrationException $exception) {
            if (! $exception->retryable) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }
}
