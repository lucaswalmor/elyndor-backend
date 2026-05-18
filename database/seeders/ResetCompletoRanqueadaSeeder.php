<?php

namespace Database\Seeders;

use App\Models\MatchmakingQueue;
use App\Models\RankedMatchOutcome;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Zera o estado ranqueado de **todas** as contas (como no primeiro dia na ranked).
 *
 * Limpeza:
 * - todas as linhas em `ranked_match_outcomes` (histórico / perfil público ranqueado);
 * - entradas da fila `matchmaking_queue` com `modo = ranqueada`;
 * - por utilizador: `ranked_points`, `ranked_wins`, `ranked_losses` → 0;
 * - ajusta `match_mode_counts.ranqueada` e reduz `total_matches_played` pelo número de jogos ranqueados contabilizados nesse mapa.
 *
 * Não apaga partidas em `matches` (casual/modo continua no histórico bruto de partida se existir); só o rasto de elo e o histórico ranqueado da API.
 *
 * Uso em fase de teste:
 *   php artisan db:seed --class=ResetCompletoRanqueadaSeeder
 *
 * Depois, para repor os substitutos por divisão (bots internos):
 *   php artisan db:seed --class=ContasSubstitutasRanqueadaSeeder
 */
class ResetCompletoRanqueadaSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            RankedMatchOutcome::query()->delete();

            MatchmakingQueue::query()->where('modo', 'ranqueada')->delete();

            User::query()->orderBy('id')->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $counts = is_array($user->match_mode_counts) ? [...$user->match_mode_counts] : [];
                    $rankedPlayed = (int) ($counts['ranqueada'] ?? 0);

                    if ($rankedPlayed > 0) {
                        $counts['ranqueada'] = 0;
                        $user->match_mode_counts = $counts;
                        $user->total_matches_played = max(0, (int) $user->total_matches_played - $rankedPlayed);
                    }

                    $user->ranked_points = 0;
                    $user->ranked_wins = 0;
                    $user->ranked_losses = 0;
                    $user->save();
                }
            });
        });

        $this->command?->info('Ranqueada resetada. Opcional: php artisan db:seed --class=ContasSubstitutasRanqueadaSeeder');
    }
}
