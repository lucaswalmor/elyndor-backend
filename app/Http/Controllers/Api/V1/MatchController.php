<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Services\Game\MatchEngine;
use App\Services\Game\MatchViewBuilder;
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
        $t0    = hrtime(true);
        $match = GameMatch::with('players.user')->findOrFail($id);
        $t1    = hrtime(true);
        $this->authorizeMatch($match, $request->user()->id);
        $view  = $this->viewBuilder->forUser($match, $request->user());
        $t2    = hrtime(true);

        \Illuminate\Support\Facades\Log::info('[show] timings (ms)', [
            'db_load'   => round(($t1 - $t0) / 1e6, 2),
            'viewbuild' => round(($t2 - $t1) / 1e6, 2),
            'total'     => round(($t2 - $t0) / 1e6, 2),
        ]);

        return response()->json($view);
    }

    public function action(Request $request, int $id): JsonResponse
    {
        $t0 = hrtime(true);

        // Carrega players.user de uma vez — evita N+1 no MatchViewBuilder e playerSlot
        $match = GameMatch::with('players.user')->findOrFail($id);

        $t1 = hrtime(true);

        $this->authorizeMatch($match, $request->user()->id);

        try {
            $result = $this->engine->processAction($match, $request->user(), $request->all());

            $t2 = hrtime(true);

            // processAction já salvou $match->estado em memória e no banco.
            // players.user já está carregado — não precisamos de refresh() nem nova query.
            $view = $this->viewBuilder->forUser($match, $request->user());

            $t3 = hrtime(true);

            \Illuminate\Support\Facades\Log::info('[action] timings controller (ms)', [
                'db_load'   => round(($t1 - $t0) / 1e6, 2),
                'engine'    => round(($t2 - $t1) / 1e6, 2),
                'viewbuild' => round(($t3 - $t2) / 1e6, 2),
                'total'     => round(($t3 - $t0) / 1e6, 2),
            ]);

            return response()->json([
                'sucesso'          => true,
                'estado_atualizado'=> $view,
                'animacoes'        => $result['animacoes'],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function reconnect(Request $request, int $id): JsonResponse
    {
        $match = GameMatch::with('players.user')->findOrFail($id);
        $this->authorizeMatch($match, $request->user()->id);

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

        $match = GameMatch::with('players.user')->findOrFail($id);
        $this->authorizeMatch($match, $request->user()->id);

        if ($match->status !== \App\Enums\MatchStatus::EmAndamento) {
            return response()->json(['message' => 'Partida não está em andamento'], 400);
        }

        $slot   = $match->players->first(fn ($p) => $p->user_id === $request->user()->id)?->player_slot;
        $opp    = $slot === 1 ? 2 : 1;
        $estado = $match->estado;
        $winner = $estado['jogadores'][(string) $opp]['user_id'];
        $motivo = $isAbandon ? 'abandon' : 'render';
        $status = $isAbandon
            ? \App\Enums\MatchStatus::Abandonada
            : \App\Enums\MatchStatus::Finalizada;

        $match->update([
            'status'        => $status,
            'vencedor_id'   => $winner,
            'finalizada_em' => now(),
        ]);

        defer(function () use ($match, $winner, $motivo) {
            broadcast(new \App\Events\MatchFinished($match, $winner, $motivo))->toOthers();
        });

        return response()->json(['sucesso' => true, 'motivo' => $motivo]);
    }

    private function authorizeMatch(GameMatch $match, int $userId): void
    {
        // Usa a coleção já carregada — sem query adicional
        if (! $match->players->contains('user_id', $userId)) {
            abort(403, 'Você não participa desta partida');
        }
    }
}
