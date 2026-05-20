<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\RankedMatchOutcome;
use App\Models\User;
use App\Services\Ranked\RankedOutcomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankedOutcomeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_aplica_pontos_fase12_em_partida_ranqueada(): void
    {
        $vencedor = User::factory()->create(['ranked_points' => 50, 'nickname' => 'vitor_ferro']);
        $perdedor = User::factory()->create(['ranked_points' => 300, 'nickname' => 'derrota_prata']);

        $partida = GameMatch::query()->create([
            'modo' => 'ranqueada',
            'status' => 'finalizada',
            'vencedor_id' => $vencedor->id,
            'turno' => 1,
            'jogador_da_vez' => 1,
            'estado' => [],
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

        app(RankedOutcomeService::class)->applyIfRanked($partida, $vencedor->id);

        $vencedor->refresh();
        $perdedor->refresh();

        $this->assertSame(80, $vencedor->ranked_points);
        $this->assertSame(270, $perdedor->ranked_points);

        $outcomeVencedor = RankedMatchOutcome::query()
            ->where('match_id', $partida->id)
            ->where('user_id', $vencedor->id)
            ->first();
        $this->assertNotNull($outcomeVencedor);
        $this->assertTrue($outcomeVencedor->venceu);
        $this->assertSame(30, $outcomeVencedor->delta);
    }
}
