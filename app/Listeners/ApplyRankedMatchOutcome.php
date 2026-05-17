<?php

namespace App\Listeners;

use App\Events\MatchFinished;
use App\Services\Ranked\RankedOutcomeService;

class ApplyRankedMatchOutcome
{
    public function __construct(
        private RankedOutcomeService $rankedOutcomes,
    ) {}

    public function handle(MatchFinished $event): void
    {
        $match = $event->match->fresh();
        $this->rankedOutcomes->applyIfRanked($match, $event->vencedorUserId);
    }
}
