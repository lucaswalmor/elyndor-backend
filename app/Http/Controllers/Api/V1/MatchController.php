<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MatchStatus;
use App\Events\MatchFinished;
use App\Events\MatchMessage;
use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Services\Game\MatchEngine;
use App\Services\Game\MatchViewBuilder;
use App\Services\Logging\GameBalanceMatchTelemetry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MatchController extends Controller
{
    public function __construct(
        private MatchEngine $engine,
        private MatchViewBuilder $viewBuilder,
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $match = GameMatch::with('players.user.avatar')->findOrFail($id);
        $this->authorizeMatch($match, $request->user()->id);
        $this->engine->recoverMatchOnRead($match);
        $match->refresh();
        $match->load('players.user.avatar');
        $view = $this->viewBuilder->forUser($match, $request->user());

        return response()->json($view);
    }

    public function action(Request $request, int $id): JsonResponse
    {
        $match = GameMatch::with('players.user.avatar')->findOrFail($id);
        $this->authorizeMatch($match, $request->user()->id);

        try {
            $result = $this->engine->processAction($match, $request->user(), $request->all());
            $view = $this->viewBuilder->forUser($match, $request->user());

            return response()->json([
                'sucesso' => true,
                'estado_atualizado' => $view,
                'animacoes' => $result['animacoes'],
            ]);
        } catch (InvalidArgumentException $e) {
            GameBalanceMatchTelemetry::actionRejected($match, $request->user()->id, $request->all(), $e->getMessage());

            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function reconnect(Request $request, int $id): JsonResponse
    {
        $match = GameMatch::with('players.user.avatar')->findOrFail($id);
        $this->authorizeMatch($match, $request->user()->id);
        $this->engine->recoverMatchOnRead($match);
        $match->refresh();
        $match->load('players.user.avatar');

        return response()->json([
            'estado_completo' => $this->viewBuilder->forUser($match, $request->user()),
        ]);
    }

    /**
     * Render / abandono — o jogador que chama perde, o oponente vence.
     * Disponível a qualquer momento (não precisa ser o seu turno).
     * - POST /matches/{id}/surrender  → render voluntário
     * - POST /matches/{id}/abandon    → fechar aba (via fetch keepalive)
     */
    public function surrender(Request $request, int $id): JsonResponse
    {
        $isAbandon = str_ends_with($request->path(), '/abandon');
        $userId = $request->user()->id;
        $motivo = $isAbandon ? 'abandon' : 'render';

        $match = GameMatch::with('players.user.avatar')->findOrFail($id);
        $this->authorizeMatch($match, $userId);

        if ($match->status !== MatchStatus::EmAndamento) {
            GameBalanceMatchTelemetry::flowRejected($match, $userId, 'surrender', 'match_not_in_progress');

            return response()->json(['message' => 'Partida não está em andamento'], 400);
        }

        $slot = $match->players->first(fn ($p) => $p->user_id === $userId)?->player_slot;
        $opp = $slot === 1 ? 2 : 1;
        $estado = $match->estado;
        $winner = $estado['jogadores'][(string) $opp]['user_id'] ?? null;

        if (! $winner) {
            GameBalanceMatchTelemetry::flowRejected($match, $userId, 'surrender', 'winner_not_resolvable');

            return response()->json(['message' => 'Erro interno ao processar render'], 500);
        }

        $status = $isAbandon
            ? MatchStatus::Abandonada
            : MatchStatus::Finalizada;

        $match->update([
            'status' => $status,
            'vencedor_id' => $winner,
            'finalizada_em' => now(),
        ]);

        defer(function () use ($match, $winner, $motivo) {
            event(new MatchFinished($match, $winner, $motivo));
        });

        return response()->json(['sucesso' => true, 'motivo' => $motivo]);
    }

    public function chat(Request $request, int $id): JsonResponse
    {
        $match = GameMatch::with('players.user.avatar')->findOrFail($id);
        $user = $request->user();
        $this->authorizeMatch($match, $user->id);

        if ($user->chat_banned_until && $user->chat_banned_until->isFuture()) {
            return response()->json([
                'message' => 'Seu chat foi suspenso por violação das regras da comunidade até ' . $user->chat_banned_until->format('d/m/Y H:i')
            ], 403);
        }

        $validated = $request->validate([
            'texto' => 'required|string|max:150',
        ]);

        $time = now()->format('H:i');

        defer(function () use ($match, $user, $validated, $time) {
            broadcast(new MatchMessage($match, $user->id, $validated['texto'], $time))->toOthers();
        });

        return response()->json(['sucesso' => true, 'time' => $time]);
    }

    public function report(Request $request, int $id): JsonResponse
    {
        $match = GameMatch::with('players.user.avatar')->findOrFail($id);
        $user = $request->user();
        $this->authorizeMatch($match, $user->id);

        $validated = $request->validate([
            'reported_id' => 'required|integer',
            'reason' => 'required|string',
            'details' => 'nullable|string|max:300',
        ]);

        \Illuminate\Support\Facades\Log::info('Player Report submitted', [
            'match_id' => $match->id,
            'reporter_id' => $user->id,
            'reported_id' => $validated['reported_id'],
            'reason' => $validated['reason'],
            'details' => $validated['details'] ?? '',
        ]);

        return response()->json(['sucesso' => true]);
    }

    private function authorizeMatch(GameMatch $match, int $userId): void
    {
        // Usa a coleção já carregada — sem query adicional
        if (! $match->players->contains('user_id', $userId)) {
            abort(403, 'Você não participa desta partida');
        }
    }
}
