<?php

namespace Tests\Unit;

use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Services\Economy\MatchProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MatchProgressionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_desafio_nao_concede_xp_nem_cristais(): void
    {
        $winner = User::factory()->create(['cristais' => 0, 'nickname' => 'vencedor_teste']);
        $loser = User::factory()->create(['cristais' => 0, 'nickname' => 'perdedor_teste']);

        $match = GameMatch::query()->create([
            'modo' => 'desafio',
            'status' => 'finalizada',
            'vencedor_id' => $winner->id,
            'turno' => 1,
            'jogador_da_vez' => 1,
            'estado' => [],
        ]);

        MatchPlayer::query()->create([
            'match_id' => $match->id,
            'user_id' => $winner->id,
            'player_slot' => 1,
            'is_bot' => false,
        ]);
        MatchPlayer::query()->create([
            'match_id' => $match->id,
            'user_id' => $loser->id,
            'player_slot' => 2,
            'is_bot' => false,
        ]);

        app(MatchProgressionService::class)->applyIfNotYet($match, (int) $winner->id);

        $this->assertTrue(
            DB::table('match_progression_applied')->where('match_id', $match->id)->exists()
        );
        $this->assertSame(0, (int) $winner->fresh()->cristais);
        $this->assertSame(0, (int) $loser->fresh()->cristais);
    }
}
