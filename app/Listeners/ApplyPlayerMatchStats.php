<?php

namespace App\Listeners;

use App\Events\MatchFinished;
use App\Services\Player\PlayerMatchStatsService;

class ApplyPlayerMatchStats
{
    public function __construct(
        private PlayerMatchStatsService $stats,
    ) {}

    public function handle(MatchFinished $event): void
    {
        $this->stats->applyIfNotYet($event->match);
    }
}
