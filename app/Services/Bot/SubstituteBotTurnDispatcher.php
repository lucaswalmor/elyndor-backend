<?php

namespace App\Services\Bot;

use App\Enums\MatchStatus;
use App\Jobs\PlaySubstituteBotTurnJob;
use App\Models\GameMatch;
use App\Models\MatchPlayer;

class SubstituteBotTurnDispatcher
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

        $modo = trim((string) $match->modo);
        if (! in_array($modo, ['normal', 'ranqueada'], true)) {
            return;
        }

        /** @var MatchPlayer|null $matchPlayer */
        $matchPlayer = $match->players->firstWhere('player_slot', $slot);
        if (! $matchPlayer || ! $matchPlayer->is_bot) {
            return;
        }

        PlaySubstituteBotTurnJob::dispatch($matchId);
    }
}
