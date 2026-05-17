<?php

namespace App\Services\Bot;

use App\Enums\MatchStatus;
use App\Jobs\PlayRankedBotTurnJob;
use App\Models\GameMatch;
use App\Models\MatchPlayer;

class RankedBotTurnDispatcher
{
    /** TurnChanged foi emitido para o slot $slot; se for substituto, agenda jogada. */
    public function notify(int $matchId, int $slot): void
    {
        if (! config('game.bots.enabled')) {
            return;
        }

        $match = GameMatch::query()->with(['players.user'])->find($matchId);
        if (! $match || $match->status !== MatchStatus::EmAndamento) {
            return;
        }

        if (trim((string) $match->modo) !== 'ranqueada') {
            return;
        }

        /** @var MatchPlayer|null $mp */
        $mp = $match->players->firstWhere('player_slot', $slot);
        if (! $mp || ! $mp->is_bot) {
            return;
        }

        PlayRankedBotTurnJob::dispatch($matchId);
    }
}
