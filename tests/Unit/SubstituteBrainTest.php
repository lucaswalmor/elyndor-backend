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
            'linhagem' => 'ybyra',
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

    public function test_prioriza_invocar_antes_de_atacar_quando_ha_energia(): void
    {
        $cartaBarata = Card::query()->create([
            'nome' => 'Bot Invocador',
            'slug' => 'bot-invocador',
            'linhagem' => 'ybyra',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 2,
            'vida' => 2,
        ]);
        $cartaCara = Card::query()->create([
            'nome' => 'Bot Pesado',
            'slug' => 'bot-pesado',
            'linhagem' => 'ybyra',
            'raridade' => 'comum',
            'custo' => 4,
            'ataque' => 4,
            'vida' => 4,
        ]);
        CardCatalog::flush();

        $bot = User::factory()->create(['is_bot' => true, 'nickname' => 'bot_invocador']);
        $humano = User::factory()->create(['nickname' => 'humano_invocador']);

        $partida = GameMatch::query()->create([
            'modo' => 'normal',
            'status' => 'em_andamento',
            'turno' => 4,
            'jogador_da_vez' => 1,
            'estado' => [
                'turno' => 4,
                'jogador_da_vez' => 1,
                'jogadores' => [
                    '1' => [
                        'user_id' => $bot->id,
                        'vida' => 12,
                        'energia_atual' => 6,
                        'mao' => [
                            ['instancia_id' => 'mao-1', 'card_id' => $cartaBarata->id],
                            ['instancia_id' => 'mao-2', 'card_id' => $cartaCara->id],
                        ],
                    ],
                    '2' => ['user_id' => $humano->id, 'vida' => 20, 'energia_atual' => 5, 'mao' => []],
                ],
                'campo' => [
                    '1' => [[
                        'instancia_id' => 'atk-1',
                        'card_id' => $cartaBarata->id,
                        'vida_atual' => 2,
                        'vida_max' => 2,
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
            'user_id' => $humano->id,
            'player_slot' => 2,
            'is_bot' => false,
        ]);

        $partida->load('players');

        $payload = app(SubstituteBrain::class)->nextPayload($partida, 1);

        $this->assertSame('invocar', $payload['acao'] ?? null);
        $this->assertContains($payload['instancia_id'] ?? '', ['mao-1', 'mao-2']);
    }
}
