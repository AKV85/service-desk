<?php

namespace Tests\Feature\Integrations\GitHub;

use App\Integrations\GitHub\Webhooks\GitHubWebhookSignatureVerifier;
use Tests\TestCase;

class GitHubWebhookSignatureVerifierTest extends TestCase
{
    public function test_it_accepts_valid_github_signature(): void
    {
        $verifier = new GitHubWebhookSignatureVerifier;

        $result = $verifier->verify(
            payload: 'Hello, World!',
            signature: 'sha256=757107ea0eb2509fc211221cce984b8a37570b6d7586c22c46f4379c8b043e17',
            secret: "It's a Secret to Everybody",
        );

        $this->assertTrue($result);
    }

    public function test_it_rejects_invalid_signature(): void
    {
        $verifier = new GitHubWebhookSignatureVerifier;

        $result = $verifier->verify(
            payload: 'Hello, World!',
            signature: 'sha256=invalid',
            secret: "It's a Secret to Everybody",
        );

        $this->assertFalse($result);
    }

    public function test_it_rejects_missing_signature(): void
    {
        $verifier = new GitHubWebhookSignatureVerifier;

        $result = $verifier->verify(
            payload: 'Hello, World!',
            signature: null,
            secret: "It's a Secret to Everybody",
        );

        $this->assertFalse($result);
    }

    public function test_it_rejects_missing_secret(): void
    {
        $verifier = new GitHubWebhookSignatureVerifier;

        $result = $verifier->verify(
            payload: 'Hello, World!',
            signature: 'sha256=757107ea0eb2509fc211221cce984b8a37570b6d7586c22c46f4379c8b043e17',
            secret: null,
        );

        $this->assertFalse($result);
    }

    public function test_it_rejects_signature_when_payload_was_modified(): void
    {
        $verifier = new GitHubWebhookSignatureVerifier;

        $result = $verifier->verify(
            payload: 'Hello, World?',
            signature: 'sha256=757107ea0eb2509fc211221cce984b8a37570b6d7586c22c46f4379c8b043e17',
            secret: "It's a Secret to Everybody",
        );

        $this->assertFalse($result);
    }
}
