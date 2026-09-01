<?php

namespace App\Models;

use App\Enums\Integrations\IntegrationSyncStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JiraIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'external_id',
        'issue_key',
        'url',
        'external_status',
        'sync_status',
        'external_updated_at',
        'last_synced_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sync_status' => IntegrationSyncStatus::class,
            'external_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
