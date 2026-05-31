<?php

namespace App\Jobs;

use App\Enums\MatchStatus;
use App\Models\GameMatch;
use App\Models\User;
use App\Services\Game\MatchEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * No tutorial, o turno do bot encerra imediatamente sem jogadas.
 */
class PlayTutorialBotPassTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $matchId,
    ) {}

    public function handle(MatchEngine $motor): void
    {
        $match = GameMatch::query()->with('players.user')->find($this->matchId);
        if (! $match || $match->status !== MatchStatus::EmAndamento || $match->modo !== 'tutorial') {
            return;
        }

        $slotBot = (int) $match->jogador_da_vez;
        if ($slotBot !== 2) {
            return;
        }

        /** @var User|null $botUser */
        $botUser = $match->players->firstWhere('player_slot', $slotBot)?->user;
        if (! $botUser) {
            return;
        }

        $motor->processAction($match, $botUser, ['acao' => 'finalizar_turno']);
    }
}
