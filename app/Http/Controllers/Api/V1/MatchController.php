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
        $match = GameMatch::with('players.user')->findOrFail($id);
        $this->authorizeMatch($match, $request->user()->id);

        return response()->json($this->viewBuilder->forUser($match, $request->user()));
    }

    public function action(Request $request, int $id): JsonResponse
    {
        $match = GameMatch::with('players')->findOrFail($id);
        $this->authorizeMatch($match, $request->user()->id);

        try {
            $result = $this->engine->processAction($match, $request->user(), $request->all());
            $match->refresh();

            return response()->json([
                'sucesso' => true,
                'estado_atualizado' => $this->viewBuilder->forUser($match, $request->user()),
                'animacoes' => $result['animacoes'],
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

    private function authorizeMatch(GameMatch $match, int $userId): void
    {
        if (! $match->players()->where('user_id', $userId)->exists()) {
            abort(403, 'Você não participa desta partida');
        }
    }
}
