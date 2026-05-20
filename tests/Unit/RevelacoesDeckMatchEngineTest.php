<?php

namespace Tests\Unit;

use App\Enums\MatchStatus;
use App\Models\Card;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Services\Game\MatchEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevelacoesDeckMatchEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_revelacoes_somem_ao_finalizar_turno_de_quem_viu(): void
    {
        $cartaRevelada = Card::query()->create([
            'nome' => 'Carta Topo',
            'slug' => 'carta-topo-teste',
            'faccao' => 'natureza',
            'raridade' => 'comum',
            'custo' => 1,
            'ataque' => 1,
            'vida' => 1,
        ]);

        $jogador = User::factory()->create(['nickname' => 'VidenteTeste']);
        $oponente = User::factory()->create(['nickname' => 'OponenteTeste']);

        $estadoBase = [
            'turno' => 4,
            'jogador_da_vez' => 1,
            'revelacoes' => [
                '1' => [$cartaRevelada->id],
                '2' => [],
            ],
            'jogadores' => [
                '1' => [
                    'user_id' => $jogador->id,
                    'vida' => 20,
                    'energia_atual' => 5,
                    'energia_maxima' => 8,
                    'energia_reservada' => 0,
                    'ja_atacou_neste_turno' => false,
                    'mao' => [],
                    'deck' => [],
                    'cemiterio' => [],
                ],
                '2' => [
                    'user_id' => $oponente->id,
                    'vida' => 20,
                    'energia_atual' => 5,
                    'energia_maxima' => 8,
                    'energia_reservada' => 0,
                    'ja_atacou_neste_turno' => false,
                    'mao' => [],
                    'deck' => [$cartaRevelada->id, 999],
                    'cemiterio' => [],
                ],
            ],
            'campo' => ['1' => [], '2' => []],
        ];

        $partida = GameMatch::query()->create([
            'modo' => 'normal',
            'status' => MatchStatus::EmAndamento,
            'turno' => 4,
            'jogador_da_vez' => 1,
            'estado' => $estadoBase,
        ]);

        MatchPlayer::query()->create([
            'match_id' => $partida->id,
            'user_id' => $jogador->id,
            'player_slot' => 1,
            'is_bot' => false,
        ]);
        MatchPlayer::query()->create([
            'match_id' => $partida->id,
            'user_id' => $oponente->id,
            'player_slot' => 2,
            'is_bot' => false,
        ]);

        app(MatchEngine::class)->processAction($partida, $jogador, ['acao' => 'finalizar_turno']);

        $partida->refresh();
        $estado = $partida->estado;

        $this->assertSame([], $estado['revelacoes']['1'] ?? null);
        $this->assertSame(2, (int) $estado['jogador_da_vez']);
    }
}
