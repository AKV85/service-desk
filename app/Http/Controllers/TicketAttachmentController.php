<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketAttachmentRequest;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\TicketNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TicketAttachmentController extends Controller
{
    public function store(
        StoreTicketAttachmentRequest $request,
        Ticket $ticket,
        TicketNotificationService $notificationService
    ): RedirectResponse {
        $file = $request->file('attachment');

        $path = $file->store(
            'ticket-attachments/'.$ticket->id,
            'local'
        );

        try {
            $attachment = $ticket->attachments()->create([
                'user_id' => $request->user()->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        $notificationService->attachmentAdded(
            $ticket,
            $attachment,
            $request->user()
        );

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Attachment uploaded successfully.');
    }

    public function download(
        Ticket $ticket,
        TicketAttachment $attachment
    ): StreamedResponse {
        $this->authorize('view', $ticket);

        abort_unless(
            $attachment->ticket_id === $ticket->id,
            404
        );

        abort_unless(
            Storage::disk('local')->exists($attachment->path),
            404
        );

        return Storage::disk('local')->download(
            $attachment->path,
            $attachment->original_name
        );
    }
}
