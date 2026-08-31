<?php

namespace Tests\Feature\Tickets;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Notifications\TicketAttachmentAddedNotification;
use Illuminate\Support\Facades\Notification;

class TicketAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_upload_attachment(): void
    {
        Storage::fake('local');

        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Attachment ticket',
            'description' => 'Test',
        ]);

        $file = UploadedFile::fake()->create(
            'error-log.txt',
            100,
            'text/plain'
        );

        $response = $this
            ->actingAs($requester)
            ->post(route('tickets.attachments.store', $ticket), [
                'attachment' => $file,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $attachment = $ticket->attachments()->first();

        $this->assertNotNull($attachment);

        $this->assertSame(
            'error-log.txt',
            $attachment->original_name
        );

        $this->assertSame(
            $requester->id,
            $attachment->user_id
        );

        $this->assertSame(
            $ticket->id,
            $attachment->ticket_id
        );

        $this->assertSame(
            'text/plain',
            $attachment->mime_type
        );

        Storage::disk('local')->assertExists($attachment->path);

        $response->assertSessionHas(
            'success',
            'Attachment uploaded successfully.'
        );
    }

    public function test_user_cannot_upload_attachment_to_ticket_they_cannot_view(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $otherRequester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $owner->id,
            'title' => 'Private ticket',
            'description' => 'Test',
        ]);

        $file = UploadedFile::fake()->create(
            'secret.txt',
            10,
            'text/plain'
        );

        $response = $this
            ->actingAs($otherRequester)
            ->post(route('tickets.attachments.store', $ticket), [
                'attachment' => $file,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseCount('ticket_attachments', 0);
    }

    public function test_attachment_is_required(): void
    {
        Storage::fake('local');

        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Attachment ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($requester)
            ->post(route('tickets.attachments.store', $ticket), []);

        $response->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('ticket_attachments', 0);
    }

    public function test_attachment_larger_than_ten_megabytes_is_rejected(): void
    {
        Storage::fake('local');

        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Attachment ticket',
            'description' => 'Test',
        ]);

        $file = UploadedFile::fake()->create(
            'large.pdf',
            10241,
            'application/pdf'
        );

        $response = $this
            ->actingAs($requester)
            ->post(route('tickets.attachments.store', $ticket), [
                'attachment' => $file,
            ]);

        $response->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('ticket_attachments', 0);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        Storage::fake('local');

        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Attachment ticket',
            'description' => 'Test',
        ]);

        $file = UploadedFile::fake()->create(
            'script.exe',
            100,
            'application/x-msdownload'
        );

        $response = $this
            ->actingAs($requester)
            ->post(route('tickets.attachments.store', $ticket), [
                'attachment' => $file,
            ]);

        $response->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('ticket_attachments', 0);
    }

    public function test_authorized_user_can_download_attachment(): void
    {
        Storage::fake('local');

        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Attachment ticket',
            'description' => 'Test',
        ]);

        $file = UploadedFile::fake()->create(
            'report.txt',
            10,
            'text/plain'
        );

        $path = $file->store(
            'ticket-attachments/' . $ticket->id,
            'local'
        );

        $attachment = $ticket->attachments()->create([
            'user_id' => $requester->id,
            'original_name' => 'report.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => $file->getSize(),
        ]);

        $response = $this
            ->actingAs($requester)
            ->get(route('tickets.attachments.download', [
                $ticket,
                $attachment,
            ]));

        $response->assertOk();

        $response->assertDownload('report.txt');
    }

    public function test_user_cannot_download_attachment_from_ticket_they_cannot_view(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $otherRequester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $owner->id,
            'title' => 'Private ticket',
            'description' => 'Test',
        ]);

        $file = UploadedFile::fake()->create(
            'secret.txt',
            10,
            'text/plain'
        );

        $path = $file->store(
            'ticket-attachments/' . $ticket->id,
            'local'
        );

        $attachment = $ticket->attachments()->create([
            'user_id' => $owner->id,
            'original_name' => 'secret.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => $file->getSize(),
        ]);

        $response = $this
            ->actingAs($otherRequester)
            ->get(route('tickets.attachments.download', [
                $ticket,
                $attachment,
            ]));

        $response->assertForbidden();
    }

    public function test_attachment_cannot_be_downloaded_through_another_ticket(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $requester = User::factory()->create();

        $firstTicket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'First ticket',
            'description' => 'Test',
        ]);

        $secondTicket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Second ticket',
            'description' => 'Test',
        ]);

        $file = UploadedFile::fake()->create(
            'report.txt',
            10,
            'text/plain'
        );

        $path = $file->store(
            'ticket-attachments/' . $firstTicket->id,
            'local'
        );

        $attachment = $firstTicket->attachments()->create([
            'user_id' => $requester->id,
            'original_name' => 'report.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => $file->getSize(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('tickets.attachments.download', [
                $secondTicket,
                $attachment,
            ]));

        $response->assertNotFound();
    }

    public function test_agent_uploading_attachment_notifies_requester_but_not_agent(): void
    {
        Notification::fake();
        Storage::fake('local');

        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'assigned_to_id' => $agent->id,
            'title' => 'Attachment ticket',
            'description' => 'Test',
        ]);

        $file = UploadedFile::fake()->create(
            'report.pdf',
            100,
            'application/pdf'
        );

        $response = $this
            ->actingAs($agent)
            ->post(route('tickets.attachments.store', $ticket), [
                'attachment' => $file,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $attachment = $ticket->attachments()->firstOrFail();

        Notification::assertSentTo(
            $requester,
            TicketAttachmentAddedNotification::class,
            function (TicketAttachmentAddedNotification $notification) use (
                $ticket,
                $attachment,
                $requester
            ) {
                $data = $notification->toArray($requester);

                return $data['ticket_id'] === $ticket->id
                    && $data['attachment_id'] === $attachment->id
                    && $data['original_name'] === 'report.pdf';
            }
        );

        Notification::assertNotSentTo(
            $agent,
            TicketAttachmentAddedNotification::class
        );
    }

    public function test_requester_uploading_attachment_notifies_assignee_but_not_requester(): void
    {
        Notification::fake();
        Storage::fake('local');

        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'assigned_to_id' => $agent->id,
            'title' => 'Attachment ticket',
            'description' => 'Test',
        ]);

        $file = UploadedFile::fake()->create(
            'document.txt',
            10,
            'text/plain'
        );

        $response = $this
            ->actingAs($requester)
            ->post(route('tickets.attachments.store', $ticket), [
                'attachment' => $file,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $attachment = $ticket->attachments()->firstOrFail();

        Notification::assertSentTo(
            $agent,
            TicketAttachmentAddedNotification::class,
            function (TicketAttachmentAddedNotification $notification) use (
                $ticket,
                $attachment,
                $agent
            ) {
                $data = $notification->toArray($agent);

                return $data['ticket_id'] === $ticket->id
                    && $data['attachment_id'] === $attachment->id
                    && $data['original_name'] === 'document.txt';
            }
        );

        Notification::assertNotSentTo(
            $requester,
            TicketAttachmentAddedNotification::class
        );
    }
}
