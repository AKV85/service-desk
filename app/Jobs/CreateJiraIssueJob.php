<?php

namespace App\Jobs;

use App\Exceptions\Integrations\IntegrationException;
use App\Models\Ticket;
use App\Services\Integrations\JiraIntegrationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateJiraIssueJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $ticketId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->ticketId;
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(JiraIntegrationService $jiraIntegrationService): void
    {
        $ticket = Ticket::query()->find($this->ticketId);

        if ($ticket === null) {
            return;
        }

        try {
            $jiraIntegrationService->createIssueForTicket($ticket);
        } catch (IntegrationException $exception) {
            if (! $exception->retryable) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }
}
