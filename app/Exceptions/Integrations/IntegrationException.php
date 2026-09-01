<?php

namespace App\Exceptions\Integrations;

use RuntimeException;
use Throwable;

class IntegrationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly string $operation,
        public readonly bool $retryable,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
