<?php

namespace App\Enums\Integrations;

enum IntegrationWebhookStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Failed = 'failed';
    case Ignored = 'ignored';
}
