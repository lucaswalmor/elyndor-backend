<?php

namespace Tests\Unit;

use App\Enums\MatchStatus;
use App\Models\Card;
use App\Models\CardSkill;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Services\Game\CardCatalog;
use App\Services\Game\MatchEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalamandraReflexoDanoTest extends TestCase
{
    use RefreshDatabase;

    public function test_salamandra_causa_reflexo_mais_retaliacao_ao_ser_atacada(): void
    {
        $salamandra = Card::query()->create([
            'nome' => 'Salamandra de Chamas',
            'slug' => 'salamandra-de-chamas-teste',
            'linhagem' => 'karuna',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 2,
            'vida' => 3,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $salamandra->id,
            'nome' => 'Pele Incandescente',
            'tipo' => 'passiva',
            'gatilho' => null,
            'ordem' => 0,
            'efeito' => ['tipo' => 'reflexo_dano', 'valor' => 1],
        ]);

        $atacante = Card::query()->create([
            'nome' => 'Atacante Teste',
            'slug' => 'atacante-salamandra-teste',
            'linhagem' => 'karuna',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 2,
            'vida' => 4,
            'ativo' => true,
        ]);
        CardCatalog::flush();

        $jogadorAtacante = User::factory()->create(['nickname' => 'AtacanteSalamandra']);
        $jogadorDefensor = User::factory()->create(['nickname' => 'DefensorSalamandra']);

        $estado = [
            'turno' => 3,
            'jogador_da_vez' => 1,
            'revelacoes' => ['1' => [], '2' => []],
            'jogadores' => [
                '1' => $this->jogadorMinimo($jogadorAtacante->id),
                '2' => $this->jogadorMinimo($jogadorDefensor->id),
            ],
            'campo' => [
                '1' => [[
                    'instancia_id' => 'atacante-1',
                    'card_id' => $atacante->id,
                    'vida_atual' => 4,
                    'vida_max' => 4,
                    'bonus_ataque' => 0,
                    'pode_atacar' => true,
                    'foi_invocado_neste_turno' => false,
                    'silenciado' => false,
                    'efeitos' => [],
                    'flags' => [],
                ]],
                '2' => [[
                    'instancia_id' => 'salamandra-1',
                    'card_id' => $salamandra->id,
                    'vida_atual' => 3,
                    'vida_max' => 3,
                    'bonus_ataque' => 0,
                    'pode_atacar' => false,
                    'foi_invocado_neste_turno' => false,
                    'silenciado' => false,
                    'efeitos' => [],
                    'flags' => [],
                ]],
            ],
        ];

        $partida = GameMatch::query()->create([
            'modo' => 'normal',
            'status' => MatchStatus::EmAndamento,
            'jogador_da_vez' => 1,
            'turno' => 3,
            'estado' => $estado,
        ]);

        MatchPlayer::query()->create([
            'match_id' => $partida->id,
            'user_id' => $jogadorAtacante->id,
            'player_slot' => 1,
            'is_bot' => false,
        ]);
        MatchPlayer::query()->create([
            'match_id' => $partida->id,
            'user_id' => $jogadorDefensor->id,
            'player_slot' => 2,
            'is_bot' => false,
        ]);

        $motor = app(MatchEngine::class);
        $resultado = $motor->processAction($partida, $jogadorAtacante, [
            'acao' => 'atacar_unidade',
            'instancia_id' => 'atacante-1',
            'alvo_instancia_id' => 'salamandra-1',
        ]);

        $estadoDepois = $partida->fresh()->estado;
        $unidadeAtacante = $estadoDepois['campo']['1'][0];
        $unidadeSalamandra = $estadoDepois['campo']['2'][0];

        $this->assertSame(1, $unidadeAtacante['vida_atual'], 'Atacante deve levar 1 reflexo + 2 retaliação = 3 de dano (4→1)');
        $this->assertSame(1, $unidadeSalamandra['vida_atual'], 'Salamandra deve ficar com 1 HP após receber 2 de dano');

        $tiposAnimacao = array_column($resultado['animacoes'] ?? [], 'tipo');
        $this->assertContains('reflexo_dano', $tiposAnimacao);
    }

    /** @return array<string, mixed> */
    private function jogadorMinimo(int $userId): array
    {
        return [
            'user_id' => $userId,
            'vida' => 20,
            'energia_atual' => 5,
            'energia_maxima' => 5,
            'energia_reservada' => 0,
            'ja_atacou_neste_turno' => false,
            'mao' => [],
            'deck' => [],
            'cemiterio' => [],
            'ressurreicao_usada' => false,
            'ressurreicao_pendente' => false,
            'efeitos' => [],
        ];
    }
}
