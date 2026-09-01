<?php

namespace App\Models;

use App\Enums\Integrations\GitHubResourceType;
use App\Enums\Integrations\IntegrationSyncStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GitHubResource extends Model
{
    use HasFactory;

    protected $table = 'github_resources';

    protected $fillable = [
        'ticket_id',
        'resource_type',
        'external_id',
        'repository',
        'resource_number',
        'reference',
        'url',
        'external_state',
        'external_updated_at',
        'sync_status',
        'last_synced_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'resource_type' => GitHubResourceType::class,
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
