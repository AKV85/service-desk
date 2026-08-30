<?php

namespace Tests\Unit\Services;

use App\Enums\TicketStatus;
use App\Services\TicketStatusTransitionService;
use PHPUnit\Framework\TestCase;

class TicketStatusTransitionServiceTest extends TestCase
{
    private TicketStatusTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TicketStatusTransitionService();
    }

    public function test_new_can_transition_to_in_progress(): void
    {
        $this->assertTrue(
            $this->service->canTransition(
                TicketStatus::New,
                TicketStatus::InProgress
            )
        );
    }

    public function test_in_progress_can_transition_to_resolved(): void
    {
        $this->assertTrue(
            $this->service->canTransition(
                TicketStatus::InProgress,
                TicketStatus::Resolved
            )
        );
    }

    public function test_resolved_can_transition_to_in_progress(): void
    {
        $this->assertTrue(
            $this->service->canTransition(
                TicketStatus::Resolved,
                TicketStatus::InProgress
            )
        );
    }

    public function test_resolved_can_transition_to_closed(): void
    {
        $this->assertTrue(
            $this->service->canTransition(
                TicketStatus::Resolved,
                TicketStatus::Closed
            )
        );
    }

    public function test_new_cannot_transition_directly_to_resolved(): void
    {
        $this->assertFalse(
            $this->service->canTransition(
                TicketStatus::New,
                TicketStatus::Resolved
            )
        );
    }

    public function test_new_cannot_transition_directly_to_closed(): void
    {
        $this->assertFalse(
            $this->service->canTransition(
                TicketStatus::New,
                TicketStatus::Closed
            )
        );
    }

    public function test_in_progress_cannot_transition_to_new(): void
    {
        $this->assertFalse(
            $this->service->canTransition(
                TicketStatus::InProgress,
                TicketStatus::New
            )
        );
    }

    public function test_closed_cannot_transition_to_any_status(): void
    {
        foreach (TicketStatus::cases() as $status) {
            $this->assertFalse(
                $this->service->canTransition(
                    TicketStatus::Closed,
                    $status
                )
            );
        }
    }
}