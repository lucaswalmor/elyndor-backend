<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Auth\UserSessionTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function __construct(
        private UserSessionTracker $sessions,
    ) {}

    /** Mantém o jogador na contagem online enquanto a app está aberta. */
    public function heartbeat(Request $request): JsonResponse
    {
        $this->sessions->touch($request, $request->user());

        return response()->json(['ok' => true]);
    }

    /** Chamado ao fechar aba/app (fetch keepalive) — remove da contagem sem invalidar o token. */
    public function leave(Request $request): JsonResponse
    {
        $this->sessions->endSession($request->user());

        return response()->json(['ok' => true]);
    }
}
