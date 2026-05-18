<?php

namespace App\Listeners;

use App\Events\MatchFinished;
use App\Services\Logging\GameBalanceMatchTelemetry;
use Illuminate\Support\Facades\Cache;

class LogGameBalanceMatchFinished
{
    public function handle(MatchFinished $event): void
    {
        $match = $event->match->fresh();

        if ($match === null) {
            return;
        }

        $finishedAt = $match->finalizada_em?->getTimestamp() ?? 0;
        $cacheKey = sprintf(
            'game_balance:match_finished:%d:%d:%s:%d',
            $match->id,
            $event->vencedorUserId,
            $event->motivo,
            $finishedAt
        );

        if (! Cache::add($cacheKey, 1, now()->addMinutes(15))) {
            return;
        }

        GameBalanceMatchTelemetry::matchFinished($match, $event->vencedorUserId, $event->motivo);
    }
}
