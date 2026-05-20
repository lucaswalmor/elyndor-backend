<?php

namespace Tests\Feature;

use App\Enums\MatchStatus;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchStaleTurnRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_encerra_turno_expirado_e_passa_para_humano(): void
    {
        $humano = User::factory()->create(['nickname' => 'HumanoTeste']);
        $bot = User::factory()->create(['is_bot' => true, 'nickname' => 'BotTeste']);

        $match = GameMatch::create([
            'modo' => 'normal',
            'status' => MatchStatus::EmAndamento,
            'turno' => 20,
            'jogador_da_vez' => 2,
            'turno_deadline_em' => now()->subMinutes(2),
            'estado' => [
                'turno' => 20,
                'jogador_da_vez' => 2,
                'jogadores' => [
                    '1' => [
                        'user_id' => $humano->id,
                        'vida' => 20,
                        'energia_atual' => 10,
                        'energia_maxima' => 10,
                        'energia_reservada' => 0,
                        'ja_atacou_neste_turno' => false,
                        'mao' => [],
                        'deck' => [1, 2],
                        'cemiterio' => [],
                    ],
                    '2' => [
                        'user_id' => $bot->id,
                        'vida' => 16,
                        'energia_atual' => 10,
                        'energia_maxima' => 10,
                        'energia_reservada' => 0,
                        'ja_atacou_neste_turno' => false,
                        'mao' => [],
                        'deck' => [3, 4],
                        'cemiterio' => [],
                    ],
                ],
                'campo' => ['1' => [], '2' => []],
            ],
        ]);

        MatchPlayer::create([
            'match_id' => $match->id,
            'user_id' => $humano->id,
            'player_slot' => 1,
            'is_bot' => false,
        ]);
        MatchPlayer::create([
            'match_id' => $match->id,
            'user_id' => $bot->id,
            'player_slot' => 2,
            'is_bot' => true,
        ]);

        $resposta = $this->actingAs($humano)->getJson("/api/v1/matches/{$match->id}");

        $resposta->assertOk();
        $resposta->assertJsonPath('jogador_da_vez', 1);
        $resposta->assertJsonPath('meu_player_id', 1);

        $match->refresh();
        $this->assertSame(1, (int) $match->jogador_da_vez);
        $this->assertTrue($match->turno_deadline_em->isFuture());
    }
}
