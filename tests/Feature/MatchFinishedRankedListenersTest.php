<?php

namespace Tests\Feature;

use App\Events\MatchFinished;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\PlayerLevel;
use App\Models\RankedMatchOutcome;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchFinishedRankedListenersTest extends TestCase
{
    use RefreshDatabase;

    public function test_listeners_aplicam_progressao_e_pontos_ranqueados(): void
    {
        $vencedor = User::factory()->create(['ranked_points' => 50, 'cristais' => 0, 'nickname' => 'vitor_rk']);
        $perdedor = User::factory()->create(['ranked_points' => 300, 'cristais' => 0, 'nickname' => 'derrota_rk']);
        PlayerLevel::query()->create(['user_id' => $vencedor->id, 'nivel' => 25, 'xp_atual' => 0]);
        PlayerLevel::query()->create(['user_id' => $perdedor->id, 'nivel' => 25, 'xp_atual' => 0]);

        $partida = GameMatch::query()->create([
            'modo' => 'ranqueada',
            'status' => 'finalizada',
            'vencedor_id' => $vencedor->id,
            'turno' => 1,
            'jogador_da_vez' => 1,
            'estado' => [],
            'iniciada_em' => now()->subMinutes(5),
            'finalizada_em' => now(),
        ]);

        MatchPlayer::query()->create([
            'match_id' => $partida->id,
            'user_id' => $vencedor->id,
            'player_slot' => 1,
            'is_bot' => false,
        ]);
        MatchPlayer::query()->create([
            'match_id' => $partida->id,
            'user_id' => $perdedor->id,
            'player_slot' => 2,
            'is_bot' => false,
        ]);

        event(new MatchFinished($partida, $vencedor->id, 'vida_zerada'));

        $vencedor->refresh();
        $perdedor->refresh();

        $this->assertSame(80, $vencedor->ranked_points);
        $this->assertSame(270, $perdedor->ranked_points);
        $this->assertGreaterThan(0, (int) $vencedor->cristais);

        $this->assertTrue(
            RankedMatchOutcome::query()->where('match_id', $partida->id)->where('user_id', $vencedor->id)->exists()
        );
        $this->assertTrue(
            RankedMatchOutcome::query()->where('match_id', $partida->id)->where('user_id', $perdedor->id)->exists()
        );
    }
}
