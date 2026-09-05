<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\AI\AiTicketAnalysisService;
use App\Services\AI\AiTicketDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TicketAiController extends Controller
{
    private const UNAVAILABLE_MESSAGE =
        'AI assistance is currently unavailable. Please try again later.';

    public function analyze(
        Request $request,
        Ticket $ticket,
        AiTicketAnalysisService $analysisService
    ): RedirectResponse|JsonResponse {
        $this->authorize('useAi', $ticket);

        try {
            $analysis = $analysisService->analyze($ticket);
        } catch (RuntimeException $exception) {
            report($exception);

            return $this->unavailable($request, $ticket);
        }

        $data = [
            'summary' => $analysis->summary,
            'likely_cause' => $analysis->likelyCause,
            'suggested_actions' => $analysis->suggestedActions,
            'observations' => $analysis->observations,
            'development_context' => $analysis->developmentContext,
            'model' => $analysis->model,
            'external_id' => $analysis->externalId,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'type' => 'analysis',
                'data' => $data,
            ]);
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('ai_analysis', $data);
    }

    public function responseDraft(
        Request $request,
        Ticket $ticket,
        AiTicketDraftService $draftService
    ): RedirectResponse|JsonResponse {
        $this->authorize('useAi', $ticket);

        try {
            $draft = $draftService->generateResponseDraft($ticket);
        } catch (RuntimeException $exception) {
            report($exception);

            return $this->unavailable($request, $ticket);
        }

        return $this->draftResponse(
            request: $request,
            ticket: $ticket,
            content: $draft->content,
            type: $draft->type,
            model: $draft->model,
            externalId: $draft->externalId,
        );
    }

    public function resolutionDraft(
        Request $request,
        Ticket $ticket,
        AiTicketDraftService $draftService
    ): RedirectResponse|JsonResponse {
        $this->authorize('useAi', $ticket);

        try {
            $draft = $draftService->generateResolutionDraft($ticket);
        } catch (RuntimeException $exception) {
            report($exception);

            return $this->unavailable($request, $ticket);
        }

        return $this->draftResponse(
            request: $request,
            ticket: $ticket,
            content: $draft->content,
            type: $draft->type,
            model: $draft->model,
            externalId: $draft->externalId,
        );
    }

    private function draftResponse(
        Request $request,
        Ticket $ticket,
        string $content,
        string $type,
        string $model,
        ?string $externalId,
    ): RedirectResponse|JsonResponse {
        $data = [
            'content' => $content,
            'type' => $type,
            'model' => $model,
            'external_id' => $externalId,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'type' => 'draft',
                'data' => $data,
            ]);
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('ai_draft', $data);
    }

    private function unavailable(
        Request $request,
        Ticket $ticket
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => self::UNAVAILABLE_MESSAGE,
            ], 503);
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('ai_error', self::UNAVAILABLE_MESSAGE);
    }
}
