<?php

namespace Tests\Feature\Models;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_attachment_relationships_work(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Attachment ticket',
            'description' => 'Test',
        ]);

        $attachment = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'original_name' => 'error.png',
            'path' => 'ticket-attachments/error.png',
            'mime_type' => 'image/png',
            'size' => 12345,
        ]);

        $this->assertTrue($attachment->ticket->is($ticket));
        $this->assertTrue($attachment->user->is($user));

        $this->assertTrue(
            $ticket->attachments->contains($attachment)
        );

        $this->assertTrue(
            $user->ticketAttachments->contains($attachment)
        );
    }

    public function test_attachments_are_deleted_when_ticket_is_deleted(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Attachment ticket',
            'description' => 'Test',
        ]);

        $attachment = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'original_name' => 'log.txt',
            'path' => 'ticket-attachments/log.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
        ]);

        $ticket->delete();

        $this->assertDatabaseMissing('ticket_attachments', [
            'id' => $attachment->id,
        ]);
    }
}