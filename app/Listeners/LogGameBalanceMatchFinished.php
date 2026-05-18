<?php

namespace App\Listeners;

use App\Events\MatchFinished;
use App\Services\Logging\GameBalanceMatchTelemetry;

class LogGameBalanceMatchFinished
{
    public function handle(MatchFinished $event): void
    {
        $match = $event->match->fresh();

        if ($match === null) {
            return;
        }

        GameBalanceMatchTelemetry::matchFinished($match, $event->vencedorUserId, $event->motivo);
    }
}
