<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Ticket $ticket */
        $ticket = $this->route('ticket');

        return $this->user()->can('view', $ticket);
    }

    public function rules(): array
    {
        return [
            'attachment' => [
                'required',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,pdf,txt,log',
            ],
        ];
    }
}
