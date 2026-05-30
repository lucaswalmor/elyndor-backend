<?php

namespace App\Console\Commands;

use App\Services\Bot\CasualSubstitutePairingService;
use App\Services\Bot\RankedSubstitutePairingService;
use App\Services\Match\MatchmakingService;
use Illuminate\Console\Command;

class ProcessarFilaMatchmakingCommand extends Command
{
    protected $signature = 'matchmaking:processar';

    protected $description = 'Tenta parear jogadores nas filas casual e ranqueada (rodar via scheduler a cada poucos segundos).';

    public function handle(
        MatchmakingService $matchmaking,
        RankedSubstitutePairingService $ranqueadaSubstituto,
        CasualSubstitutePairingService $casualSubstituto,
    ): int {
        $matchmaking->tryPairNormal() ?: $casualSubstituto->maybePairStaleSoloHuman();
        $matchmaking->tryPairRanked() ?: $ranqueadaSubstituto->maybePairStaleSoloHumans();

        return self::SUCCESS;
    }
}
