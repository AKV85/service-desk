<?php

namespace App\Integrations\GitHub\Webhooks;

class GitHubWebhookSignatureVerifier
{
    public function verify(
        string $payload,
        ?string $signature,
        ?string $secret,
    ): bool {
        if (blank($signature) || blank($secret)) {
            return false;
        }

        $expectedSignature = 'sha256='.hash_hmac(
            'sha256',
            $payload,
            $secret,
        );

        return hash_equals($expectedSignature, $signature);
    }
}
