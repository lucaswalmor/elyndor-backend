<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Services\Auth\UserSessionTracker;
use App\Services\Match\MatchmakingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MatchmakingController extends Controller
{
    public function __construct(
        private MatchmakingService $matchmaking,
        private UserSessionTracker $sessions,
    ) {}

    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'modo' => 'required|in:normal,ranqueada',
            'deck_id' => 'required|integer',
            'device_id' => ['nullable', 'string', 'max:80'],
            'client_type' => ['nullable', 'in:web,desktop'],
        ]);

        try {
            $this->sessions->touch($request, $request->user());

            return response()->json(
                $this->matchmaking->join(
                    $request->user(),
                    $request->modo,
                    (int) $request->deck_id,
                    [
                        'device_id' => $request->input('device_id'),
                        'client_type' => $request->input('client_type'),
                    ]
                )
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
        $this->sessions->touch($request, $request->user());

        return response()->json($this->matchmaking->status($request->user()));
    }

    public function accept(Request $request, GameMatch $match): JsonResponse
    {
        try {
            return response()->json($this->matchmaking->acceptOffer($request->user(), $match->id));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function decline(Request $request, GameMatch $match): JsonResponse
    {
        try {
            return response()->json($this->matchmaking->declineOffer($request->user(), $match->id));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
