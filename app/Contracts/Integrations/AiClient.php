<?php

namespace App\Contracts\Integrations;

use App\Data\Integrations\AI\AiRequestData;
use App\Data\Integrations\AI\AiResponseData;

interface AiClient
{
    public function generate(AiRequestData $request): AiResponseData;
}
