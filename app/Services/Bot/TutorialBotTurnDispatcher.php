<?php

namespace App\Services\Bot;

use App\Enums\MatchStatus;
use App\Jobs\PlayTutorialBotPassTurnJob;
use App\Models\GameMatch;

class TutorialBotTurnDispatcher
{
    public function notify(int $matchId, int $slot): void
    {
        $match = GameMatch::query()->find($matchId);
        if (! $match || $match->status !== MatchStatus::EmAndamento || $match->modo !== 'tutorial') {
            return;
        }

        if ($slot !== 2) {
            return;
        }

        PlayTutorialBotPassTurnJob::dispatch($matchId)->delay(now()->addMilliseconds(400));
    }
}
