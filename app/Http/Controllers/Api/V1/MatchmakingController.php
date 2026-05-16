<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Match\MatchmakingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MatchmakingController extends Controller
{
    public function __construct(
        private MatchmakingService $matchmaking,
    ) {}

    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'modo' => 'required|in:normal,ranqueada',
            'deck_id' => 'required|integer',
        ]);

        try {
            return response()->json(
                $this->matchmaking->join($request->user(), $request->modo, (int) $request->deck_id)
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function leave(Request $request): JsonResponse
    {
        $this->matchmaking->leave($request->user());

        return response()->json(['message' => 'Saiu da fila']);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json($this->matchmaking->status($request->user()));
    }
}
