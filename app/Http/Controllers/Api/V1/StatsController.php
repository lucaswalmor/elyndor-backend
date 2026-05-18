<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function onlinePlayers(): JsonResponse
    {
        $window = max(60, (int) config('game.stats.online_presence_seconds', 120));
        $since = now()->subSeconds($window);

        $count = (int) DB::table('user_sessions')
            ->join('users', 'users.id', '=', 'user_sessions.user_id')
            ->where('user_sessions.last_seen_at', '>=', $since)
            ->where(function ($q) {
                $q->where('users.is_bot', false)->orWhereNull('users.is_bot');
            })
            ->selectRaw('COUNT(DISTINCT user_sessions.user_id) as c')
            ->value('c');

        return response()->json([
            'data' => [
                'jogadores_online' => $count,
                'janela_segundos' => $window,
            ],
        ]);
    }
}
