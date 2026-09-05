<?php

namespace Tests\Feature\Services\AI;

use App\Contracts\Integrations\AiClient;
use App\Data\Integrations\AI\AiRequestData;
use App\Data\Integrations\AI\AiResponseData;
use App\Exceptions\Integrations\IntegrationException;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Services\AI\AiTicketDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AiTicketDraftServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('integrations.ai.enabled', true);
    }

    public function test_it_generates_response_draft(): void
    {
        $ticket = Ticket::factory()->create([
            'title' => 'Printer is unavailable',
            'description' => 'The office printer cannot be reached.',
        ]);

        $aiClient = Mockery::mock(AiClient::class);

        $aiClient
            ->shouldReceive('generate')
            ->once()
            ->withArgs(function (AiRequestData $request) use ($ticket): bool {
                $context = json_decode($request->input, true);

                return $context['ticket']['id'] === $ticket->id
                    && $context['ticket']['title'] === 'Printer is unavailable'
                    && is_string($request->instructions)
                    && str_contains(
                        $request->instructions,
                        'professional response draft',
                    );
            })
            ->andReturn($this->aiResponse(
                'We are investigating the printer connectivity issue.',
            ));

        $this->app->instance(AiClient::class, $aiClient);

        $draft = app(AiTicketDraftService::class)
            ->generateResponseDraft($ticket);

        $this->assertSame(
            'We are investigating the printer connectivity issue.',
            $draft->content,
        );
        $this->assertSame('response', $draft->type);
        $this->assertSame('test-model', $draft->model);
        $this->assertSame('response-123', $draft->externalId);
    }

    public function test_it_generates_resolution_draft(): void
    {
        $ticket = Ticket::factory()->create();

        $aiClient = Mockery::mock(AiClient::class);

        $aiClient
            ->shouldReceive('generate')
            ->once()
            ->withArgs(
                fn (AiRequestData $request): bool => is_string(
                    $request->instructions,
                ) && str_contains(
                    $request->instructions,
                    'proposed resolution draft',
                ),
            )
            ->andReturn($this->aiResponse(
                'The available context indicates that the issue was resolved.',
            ));

        $this->app->instance(AiClient::class, $aiClient);

        $draft = app(AiTicketDraftService::class)
            ->generateResolutionDraft($ticket);

        $this->assertSame(
            'The available context indicates that the issue was resolved.',
            $draft->content,
        );
        $this->assertSame('resolution', $draft->type);
    }

    public function test_it_generates_draft_without_optional_integration_context(): void
    {
        $ticket = Ticket::factory()->create();

        $aiClient = Mockery::mock(AiClient::class);

        $aiClient
            ->shouldReceive('generate')
            ->once()
            ->withArgs(function (AiRequestData $request): bool {
                $context = json_decode($request->input, true);

                return $context['jiraIssue'] === null
                    && $context['githubResources'] === [];
            })
            ->andReturn($this->aiResponse(
                'We are reviewing the reported issue.',
            ));

        $this->app->instance(AiClient::class, $aiClient);

        $draft = app(AiTicketDraftService::class)
            ->generateResponseDraft($ticket);

        $this->assertSame('response', $draft->type);
    }

    public function test_resolution_draft_can_express_uncertainty(): void
    {
        $ticket = Ticket::factory()->create();

        $aiClient = Mockery::mock(AiClient::class);

        $aiClient
            ->shouldReceive('generate')
            ->once()
            ->andReturn($this->aiResponse(
                'The available context does not confirm that the issue has been resolved.',
            ));

        $this->app->instance(AiClient::class, $aiClient);

        $draft = app(AiTicketDraftService::class)
            ->generateResolutionDraft($ticket);

        $this->assertSame(
            'The available context does not confirm that the issue has been resolved.',
            $draft->content,
        );
    }

    public function test_it_fails_when_ai_returns_invalid_json(): void
    {
        $ticket = Ticket::factory()->create();

        $aiClient = Mockery::mock(AiClient::class);

        $aiClient
            ->shouldReceive('generate')
            ->once()
            ->andReturn(new AiResponseData(
                content: 'Not JSON.',
                model: 'test-model',
            ));

        $this->app->instance(AiClient::class, $aiClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'AI ticket draft returned invalid JSON.',
        );

        app(AiTicketDraftService::class)
            ->generateResponseDraft($ticket);
    }

    public function test_it_fails_when_ai_returns_invalid_response_structure(): void
    {
        $ticket = Ticket::factory()->create();

        $aiClient = Mockery::mock(AiClient::class);

        $aiClient
            ->shouldReceive('generate')
            ->once()
            ->andReturn(new AiResponseData(
                content: json_encode([
                    'message' => 'Wrong field.',
                ], JSON_THROW_ON_ERROR),
                model: 'test-model',
            ));

        $this->app->instance(AiClient::class, $aiClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'AI ticket draft returned an invalid response structure.',
        );

        app(AiTicketDraftService::class)
            ->generateResponseDraft($ticket);
    }

    public function test_it_propagates_ai_provider_failure(): void
    {
        $ticket = Ticket::factory()->create();

        $exception = new IntegrationException(
            message: 'AI provider unavailable.',
            provider: 'openai',
            operation: 'generate',
            retryable: true,
        );

        $aiClient = Mockery::mock(AiClient::class);

        $aiClient
            ->shouldReceive('generate')
            ->once()
            ->andThrow($exception);

        $this->app->instance(AiClient::class, $aiClient);

        try {
            app(AiTicketDraftService::class)
                ->generateResponseDraft($ticket);

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $thrown) {
            $this->assertSame($exception, $thrown);
            $this->assertSame('openai', $thrown->provider);
            $this->assertSame('generate', $thrown->operation);
            $this->assertTrue($thrown->retryable);
        }
    }

    public function test_draft_generation_does_not_modify_ticket_or_create_comments(): void
    {
        $ticket = Ticket::factory()->highPriority()->create();

        $originalStatus = $ticket->status;
        $originalPriority = $ticket->priority;
        $originalAssigneeId = $ticket->assigned_to_id;
        $originalResolvedAt = $ticket->resolved_at;
        $originalClosedAt = $ticket->closed_at;
        $commentCount = TicketComment::query()
            ->where('ticket_id', $ticket->id)
            ->count();

        $aiClient = Mockery::mock(AiClient::class);

        $aiClient
            ->shouldReceive('generate')
            ->once()
            ->andReturn($this->aiResponse(
                'This is only a draft and must not be published automatically.',
            ));

        $this->app->instance(AiClient::class, $aiClient);

        app(AiTicketDraftService::class)
            ->generateResolutionDraft($ticket);

        $ticket->refresh();

        $this->assertSame($originalStatus, $ticket->status);
        $this->assertSame($originalPriority, $ticket->priority);
        $this->assertSame(
            $originalAssigneeId,
            $ticket->assigned_to_id,
        );
        $this->assertEquals($originalResolvedAt, $ticket->resolved_at);
        $this->assertEquals($originalClosedAt, $ticket->closed_at);

        $this->assertSame(
            $commentCount,
            TicketComment::query()
                ->where('ticket_id', $ticket->id)
                ->count(),
        );
    }

    private function aiResponse(string $content): AiResponseData
    {
        return new AiResponseData(
            content: json_encode([
                'content' => $content,
            ], JSON_THROW_ON_ERROR),
            model: 'test-model',
            externalId: 'response-123',
        );
    }

    public function test_it_fails_when_ai_integration_is_disabled(): void
    {
        config()->set('integrations.ai.enabled', false);

        $ticket = Ticket::factory()->create();

        $aiClient = Mockery::mock(AiClient::class);

        $aiClient
            ->shouldNotReceive('generate');

        $this->app->instance(AiClient::class, $aiClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI integration is disabled.');

        app(AiTicketDraftService::class)
            ->generateResponseDraft($ticket);
    }
}
