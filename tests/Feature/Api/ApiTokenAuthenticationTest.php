<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_api_token_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/tokens', [
            'email' => $user->email,
            'password' => 'password',
            'token_name' => 'test-client',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'token',
                'token_type',
            ])
            ->assertJson([
                'token_type' => 'Bearer',
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'test-client',
        ]);
    }

    public function test_invalid_credentials_cannot_create_api_token(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/tokens', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_bearer_token_can_authenticate_protected_api_request(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-client')->plainTextToken;

        $response = $this
            ->withToken($token)
            ->getJson('/api/tickets');

        $response->assertOk();
    }

    public function test_protected_api_request_without_token_is_unauthenticated(): void
    {
        $this->getJson('/api/tickets')
            ->assertUnauthorized();
    }

    public function test_current_api_token_can_be_revoked(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-client')->plainTextToken;

        $this
            ->withToken($token)
            ->deleteJson('/api/tokens/current')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this
            ->withToken($token)
            ->getJson('/api/tickets')
            ->assertUnauthorized();
    }

    public function test_api_token_endpoint_is_rate_limited(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/tokens', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/tokens', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }
}
