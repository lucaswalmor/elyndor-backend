<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Match\MatchmakingService;
use Illuminate\Http\JsonResponse;

class DevController extends Controller
{
    public function pairQueue(MatchmakingService $matchmaking): JsonResponse
    {
        if (! app()->environment('local')) {
            abort(404);
        }

        $id = $matchmaking->tryPair('normal');

        return response()->json([
            'message' => $id ? 'Partida criada' : 'Fila insuficiente (precisa 2 jogadores)',
            'match_id' => $id,
        ]);
    }
}
