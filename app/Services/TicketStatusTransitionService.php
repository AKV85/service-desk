<?php

namespace App\Services;

use App\Enums\TicketStatus;

class TicketStatusTransitionService
{
    public function canTransition(
        TicketStatus $from,
        TicketStatus $to
    ): bool {
        return in_array(
            $to,
            $this->allowedTransitions($from),
            true
        );
    }

    public function allowedTransitions(TicketStatus $from): array
    {
        return match ($from) {
            TicketStatus::New => [
                TicketStatus::InProgress,
            ],

            TicketStatus::InProgress => [
                TicketStatus::Resolved,
            ],

            TicketStatus::Resolved => [
                TicketStatus::InProgress,
                TicketStatus::Closed,
            ],

            TicketStatus::Closed => [],
        };
    }
}
