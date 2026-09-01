<?php

namespace App\Models;

use App\Enums\Integrations\IntegrationWebhookStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationWebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'external_event_id',
        'event_type',
        'status',
        'payload',
        'received_at',
        'processed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => IntegrationWebhookStatus::class,
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
