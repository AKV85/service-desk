<?php

namespace App\Services;

use App\Enums\TicketStatus;

class TicketStatusTransitionService
{
    public function canTransition(
        TicketStatus $from,
        TicketStatus $to
    ): bool {
        return match ($from) {
            TicketStatus::New => $to === TicketStatus::InProgress,

            TicketStatus::InProgress => $to === TicketStatus::Resolved,

            TicketStatus::Resolved => in_array($to, [
                TicketStatus::InProgress,
                TicketStatus::Closed,
            ], true),

            TicketStatus::Closed => false,
        };
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
