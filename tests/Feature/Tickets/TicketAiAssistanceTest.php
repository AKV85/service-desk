<?php

namespace Tests\Feature\Tickets;

use App\Data\AI\AiTicketAnalysisData;
use App\Data\AI\AiTicketDraftData;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AI\AiTicketAnalysisService;
use App\Services\AI\AiTicketDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class TicketAiAssistanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_request_ai_ticket_analysis(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::factory()->create();

        $this->mock(
            AiTicketAnalysisService::class,
            function (MockInterface $mock) use ($ticket): void {
                $mock->shouldReceive('analyze')
                    ->once()
                    ->withArgs(
                        fn (Ticket $argument): bool => $argument->is($ticket)
                    )
                    ->andReturn(new AiTicketAnalysisData(
                        summary: 'AI summary',
                        likelyCause: 'Possible configuration issue',
                        suggestedActions: [
                            'Check application configuration.',
                            'Review recent changes.',
                        ],
                        observations: [
                            'The issue appears reproducible.',
                        ],
                        developmentContext: [
                            'Related GitHub issue is available.',
                        ],
                        model: 'test-model',
                        externalId: 'test-response-id',
                    ));
            }
        );

        $response = $this
            ->actingAs($agent)
            ->post(route('tickets.ai.analyze', $ticket));

        $response
            ->assertRedirect(route('tickets.show', $ticket))
            ->assertSessionHas('ai_analysis', function (array $analysis): bool {
                return $analysis['summary'] === 'AI summary'
                    && $analysis['likely_cause'] === 'Possible configuration issue'
                    && $analysis['model'] === 'test-model';
            });
    }

    public function test_admin_can_request_ai_ticket_analysis(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $ticket = Ticket::factory()->create();

        $this->mock(
            AiTicketAnalysisService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('analyze')
                    ->once()
                    ->andReturn(new AiTicketAnalysisData(
                        summary: 'AI summary',
                        likelyCause: null,
                        suggestedActions: [],
                        observations: [],
                        developmentContext: [],
                        model: 'test-model',
                    ));
            }
        );

        $response = $this
            ->actingAs($admin)
            ->post(route('tickets.ai.analyze', $ticket));

        $response
            ->assertRedirect(route('tickets.show', $ticket))
            ->assertSessionHas('ai_analysis');
    }

    public function test_requester_cannot_request_ai_ticket_analysis(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
        ]);

        $this->mock(
            AiTicketAnalysisService::class,
            function (MockInterface $mock): void {
                $mock->shouldNotReceive('analyze');
            }
        );

        $this
            ->actingAs($requester)
            ->post(route('tickets.ai.analyze', $ticket))
            ->assertForbidden();
    }

    public function test_guest_cannot_request_ai_ticket_analysis(): void
    {
        $ticket = Ticket::factory()->create();

        $this
            ->post(route('tickets.ai.analyze', $ticket))
            ->assertRedirect(route('login'));
    }

    public function test_agent_can_generate_ai_response_draft(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::factory()->create();

        $this->mock(
            AiTicketDraftService::class,
            function (MockInterface $mock) use ($ticket): void {
                $mock->shouldReceive('generateResponseDraft')
                    ->once()
                    ->withArgs(
                        fn (Ticket $argument): bool => $argument->is($ticket)
                    )
                    ->andReturn(new AiTicketDraftData(
                        content: 'Suggested response',
                        type: 'response',
                        model: 'test-model',
                        externalId: 'response-id',
                    ));
            }
        );

        $this
            ->actingAs($agent)
            ->post(route('tickets.ai.response-draft', $ticket))
            ->assertRedirect(route('tickets.show', $ticket))
            ->assertSessionHas('ai_draft', function (array $draft): bool {
                return $draft['content'] === 'Suggested response'
                    && $draft['type'] === 'response'
                    && $draft['model'] === 'test-model';
            });
    }

    public function test_agent_can_generate_ai_resolution_draft(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::factory()->create();

        $this->mock(
            AiTicketDraftService::class,
            function (MockInterface $mock) use ($ticket): void {
                $mock->shouldReceive('generateResolutionDraft')
                    ->once()
                    ->withArgs(
                        fn (Ticket $argument): bool => $argument->is($ticket)
                    )
                    ->andReturn(new AiTicketDraftData(
                        content: 'Suggested resolution',
                        type: 'resolution',
                        model: 'test-model',
                        externalId: 'resolution-id',
                    ));
            }
        );

        $this
            ->actingAs($agent)
            ->post(route('tickets.ai.resolution-draft', $ticket))
            ->assertRedirect(route('tickets.show', $ticket))
            ->assertSessionHas('ai_draft', function (array $draft): bool {
                return $draft['content'] === 'Suggested resolution'
                    && $draft['type'] === 'resolution'
                    && $draft['model'] === 'test-model';
            });
    }

    public function test_ai_analysis_result_is_displayed_on_ticket_page(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::factory()->create();

        $response = $this
            ->actingAs($agent)
            ->withSession([
                'ai_analysis' => [
                    'summary' => 'AI summary',
                    'likely_cause' => 'Possible configuration issue',
                    'suggested_actions' => [
                        'Check application configuration.',
                        'Review recent changes.',
                    ],
                    'observations' => [
                        'The issue appears reproducible.',
                    ],
                    'development_context' => [
                        'Related GitHub issue is available.',
                    ],
                    'model' => 'test-model',
                    'external_id' => 'test-response-id',
                ],
            ])
            ->get(route('tickets.show', $ticket));

        $response
            ->assertOk()
            ->assertSee('AI Analysis')
            ->assertSee('AI summary')
            ->assertSee('Possible configuration issue')
            ->assertSee('Check application configuration.')
            ->assertSee('Review recent changes.')
            ->assertSee('The issue appears reproducible.')
            ->assertSee('Related GitHub issue is available.')
            ->assertSee('test-model');
    }

    public function test_ai_draft_result_is_displayed_on_ticket_page(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::factory()->create();

        $response = $this
            ->actingAs($agent)
            ->withSession([
                'ai_draft' => [
                    'content' => 'Suggested response for the requester.',
                    'type' => 'response',
                    'model' => 'test-model',
                    'external_id' => 'test-draft-id',
                ],
            ])
            ->get(route('tickets.show', $ticket));

        $response
            ->assertOk()
            ->assertSee('AI Response Draft')
            ->assertSee('Suggested response for the requester.')
            ->assertSee('test-model');
    }

    public function test_agent_can_see_ai_assistance_on_ticket_page(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::factory()->create();

        $this
            ->actingAs($agent)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('AI Assistance')
            ->assertSee('Analyze ticket')
            ->assertSee('Generate response draft')
            ->assertSee('Generate resolution draft');
    }

    public function test_requester_cannot_see_ai_assistance_on_ticket_page(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
        ]);

        $this
            ->actingAs($requester)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('AI Assistance')
            ->assertDontSee('Analyze ticket')
            ->assertDontSee('Generate response draft')
            ->assertDontSee('Generate resolution draft');
    }

    public function test_ai_disabled_state_returns_user_to_ticket_with_error(): void
    {
        config()->set('integrations.ai.enabled', false);

        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::factory()->create();

        $this
            ->actingAs($agent)
            ->post(route('tickets.ai.analyze', $ticket))
            ->assertRedirect(route('tickets.show', $ticket))
            ->assertSessionHas(
                'ai_error',
                'AI assistance is currently unavailable. Please try again later.'
            );
    }

    public function test_ai_provider_failure_returns_user_to_ticket_with_error(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::factory()->create();

        $this->mock(
            AiTicketAnalysisService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('analyze')
                    ->once()
                    ->andThrow(new RuntimeException('Provider unavailable.'));
            }
        );

        $this
            ->actingAs($agent)
            ->post(route('tickets.ai.analyze', $ticket))
            ->assertRedirect(route('tickets.show', $ticket))
            ->assertSessionHas(
                'ai_error',
                'AI assistance is currently unavailable. Please try again later.'
            );
    }
}
