<?php

namespace App\Listeners;

use App\Events\MatchFinished;
use App\Services\Economy\MatchProgressionService;

class ApplyMatchProgressionRewards
{
    public function __construct(
        private MatchProgressionService $progression,
    ) {}

    public function handle(MatchFinished $event): void
    {
        $match = $event->match->fresh();
        $this->progression->applyIfNotYet($match, $event->vencedorUserId);
    }
}
