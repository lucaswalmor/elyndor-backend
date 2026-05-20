<?php

namespace Tests\Unit;

use App\Models\Card;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Services\Bot\SubstituteBrain;
use App\Services\Game\CardCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubstituteBrainTest extends TestCase
{
    use RefreshDatabase;

    public function test_prioriza_lethal_no_rosto(): void
    {
        $card = Card::query()->create([
            'nome' => 'Teste Bot',
            'slug' => 'teste-bot-unit',
            'faccao' => 'natureza',
            'raridade' => 'comum',
            'custo' => 3,
            'ataque' => 5,
            'vida' => 3,
        ]);
        CardCatalog::flush();

        $bot = User::factory()->create(['is_bot' => true, 'nickname' => 'bot_teste']);
        $human = User::factory()->create(['nickname' => 'humano_teste']);

        $partida = GameMatch::query()->create([
            'modo' => 'normal',
            'status' => 'em_andamento',
            'turno' => 3,
            'jogador_da_vez' => 1,
            'estado' => [
                'turno' => 3,
                'jogador_da_vez' => 1,
                'jogadores' => [
                    '1' => ['user_id' => $bot->id, 'vida' => 12, 'energia_atual' => 5, 'mao' => []],
                    '2' => ['user_id' => $human->id, 'vida' => 2, 'energia_atual' => 5, 'mao' => []],
                ],
                'campo' => [
                    '1' => [[
                        'instancia_id' => 'atk-1',
                        'card_id' => $card->id,
                        'vida_atual' => 3,
                        'vida_max' => 3,
                        'bonus_ataque' => 0,
                        'pode_atacar' => true,
                        'foi_invocado_neste_turno' => false,
                        'silenciado' => false,
                        'efeitos' => [],
                        'flags' => [],
                    ]],
                    '2' => [],
                ],
            ],
        ]);

        MatchPlayer::query()->create([
            'match_id' => $partida->id,
            'user_id' => $bot->id,
            'player_slot' => 1,
            'is_bot' => true,
        ]);
        MatchPlayer::query()->create([
            'match_id' => $partida->id,
            'user_id' => $human->id,
            'player_slot' => 2,
            'is_bot' => false,
        ]);

        $partida->load('players');

        $payload = app(SubstituteBrain::class)->nextPayload($partida, 1);

        $this->assertSame('atacar_jogador', $payload['acao'] ?? null);
        $this->assertSame('atk-1', $payload['instancia_id'] ?? null);
    }
}
