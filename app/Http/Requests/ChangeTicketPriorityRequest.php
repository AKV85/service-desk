<?php

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTicketPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $ticket !== null
            && ($this->user()?->can('changePriority', $ticket) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'priority' => [
                'required',
                Rule::enum(TicketPriority::class),
            ],
        ];
    }
}
