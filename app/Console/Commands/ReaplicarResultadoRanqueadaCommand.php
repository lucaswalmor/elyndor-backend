<?php

namespace App\Console\Commands;

use App\Enums\MatchStatus;
use App\Models\GameMatch;
use App\Models\RankedMatchOutcome;
use App\Services\Ranked\RankedOutcomeService;
use Illuminate\Console\Command;

class ReaplicarResultadoRanqueadaCommand extends Command
{
    protected $signature = 'ranked:reaplicar-resultado {match_id : ID da partida finalizada}';

    protected $description = 'Reaplica pontos ranqueados e histórico se a partida finalizou sem ranked_match_outcomes';

    public function handle(RankedOutcomeService $rankedOutcomes): int
    {
        $matchId = (int) $this->argument('match_id');
        $match = GameMatch::query()->find($matchId);

        if (! $match) {
            $this->error("Partida {$matchId} não encontrada.");

            return self::FAILURE;
        }

        if ($match->modo !== 'ranqueada') {
            $this->error('Partida não é ranqueada (modo='.$match->modo.').');

            return self::FAILURE;
        }

        if (! in_array($match->status, [MatchStatus::Finalizada, MatchStatus::Abandonada], true)) {
            $this->error('Partida ainda não está finalizada (status='.$match->status->value.').');

            return self::FAILURE;
        }

        if (! $match->vencedor_id) {
            $this->error('Partida sem vencedor_id.');

            return self::FAILURE;
        }

        if (RankedMatchOutcome::where('match_id', $match->id)->exists()) {
            $this->warn('Já existem registros em ranked_match_outcomes. Nada a fazer.');

            return self::SUCCESS;
        }

        $rankedOutcomes->applyIfRanked($match, (int) $match->vencedor_id);

        $count = RankedMatchOutcome::where('match_id', $match->id)->count();
        if ($count === 0) {
            $this->error('Nenhum outcome criado — verifique jogadores da partida.');

            return self::FAILURE;
        }

        $this->info("Resultado ranqueado reaplicado ({$count} registro(s)).");

        return self::SUCCESS;
    }
}
