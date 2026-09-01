<?php

namespace App\Enums\Integrations;

enum IntegrationSyncStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
}
