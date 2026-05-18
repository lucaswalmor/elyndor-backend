<?php

namespace App\Jobs;

use App\Enums\MatchStatus;
use App\Models\GameMatch;
use App\Models\User;
use App\Services\Bot\RankedSubstituteBrain;
use App\Services\Game\MatchEngine;
use App\Services\Logging\GameBalanceMatchTelemetry;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use InvalidArgumentException;

final class PlayRankedBotTurnJob
{
    use Dispatchable;
    use Queueable;

    public function __construct(public int $matchId) {}

    public function handle(MatchEngine $engine, RankedSubstituteBrain $brain): void
    {
        if (! config('game.bots.enabled')) {
            return;
        }

        $minMs = (int) config('game.bots.disguise.think_delay_min_ms', 2000);
        $maxMs = (int) config('game.bots.disguise.think_delay_max_ms', 8000);
        $loMicro = min($minMs, $maxMs) * 1000;
        $hiMicro = max($minMs, $maxMs) * 1000;
        usleep(random_int(max(1, $loMicro), max($loMicro, $hiMicro)));

        for ($guard = 0; $guard < 45; $guard++) {
            $match = GameMatch::with('players.user')->find($this->matchId);
            if (! $match || $match->status !== MatchStatus::EmAndamento || ! is_array($match->estado)) {
                return;
            }

            $slot = (int) ($match->estado['jogador_da_vez'] ?? 1);
            $playerRow = $match->players->firstWhere('player_slot', $slot);
            if (! $playerRow || ! $playerRow->is_bot) {
                return;
            }

            $botUser = User::find($playerRow->user_id);
            if (! $botUser) {
                return;
            }

            $payload = $brain->nextPayload($match, $slot);
            if ($payload === null) {
                return;
            }

            try {
                $engine->processAction($match, $botUser, $payload);
            } catch (\Throwable $e1) {
                $match->refresh();
                if ($e1 instanceof InvalidArgumentException) {
                    GameBalanceMatchTelemetry::actionRejected($match, $botUser->id, $payload, $e1->getMessage());
                }
                if (($payload['acao'] ?? '') === 'finalizar_turno') {
                    return;
                }
                try {
                    $match->refresh();
                    $engine->processAction($match, $botUser, ['acao' => 'finalizar_turno']);
                } catch (\Throwable) {
                    return;
                }
            }

            $match->refresh()->load('players.user');
            if ($match->status !== MatchStatus::EmAndamento) {
                return;
            }
            $nextSlot = (int) (($match->estado ?? [])['jogador_da_vez'] ?? $slot);
            if (! ($match->players->firstWhere('player_slot', $nextSlot)?->is_bot ?? false)) {
                return;
            }
        }
    }
}
