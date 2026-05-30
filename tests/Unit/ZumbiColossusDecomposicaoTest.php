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

class ZumbiColossusDecomposicaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_zumbi_colossus_perde_1_ataque_ao_receber_dano(): void
    {
        $zumbi = Card::query()->create([
            'nome' => 'Zumbi Colossus',
            'slug' => 'zumbi-colossus-teste',
            'linhagem' => 'anhanga',
            'raridade' => 'comum',
            'custo' => 3,
            'ataque' => 2,
            'vida' => 7,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $zumbi->id,
            'nome' => 'Provocar',
            'tipo' => 'passiva',
            'gatilho' => null,
            'ordem' => 0,
            'efeito' => ['tipo' => 'provocar'],
        ]);
        CardSkill::query()->create([
            'card_id' => $zumbi->id,
            'nome' => 'Decomposição',
            'tipo' => 'passiva',
            'gatilho' => null,
            'ordem' => 1,
            'efeito' => ['tipo' => 'perde_ataque_ao_receber_dano', 'valor' => 1],
        ]);

        $atacante = Card::query()->create([
            'nome' => 'Atacante Teste',
            'slug' => 'atacante-zumbi-teste',
            'linhagem' => 'karuna',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 3,
            'vida' => 3,
            'ativo' => true,
        ]);
        CardCatalog::flush();

        $jogadorAtacante = User::factory()->create(['nickname' => 'AtacanteZumbi']);
        $jogadorDefensor = User::factory()->create(['nickname' => 'DefensorZumbi']);

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
                    'vida_atual' => 3,
                    'vida_max' => 3,
                    'bonus_ataque' => 0,
                    'pode_atacar' => true,
                    'foi_invocado_neste_turno' => false,
                    'silenciado' => false,
                    'efeitos' => [],
                    'flags' => [],
                ]],
                '2' => [[
                    'instancia_id' => 'zumbi-1',
                    'card_id' => $zumbi->id,
                    'vida_atual' => 7,
                    'vida_max' => 7,
                    'bonus_ataque' => 0,
                    'pode_atacar' => false,
                    'foi_invocado_neste_turno' => false,
                    'silenciado' => false,
                    'efeitos' => [],
                    'flags' => ['taunt_self' => true],
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
            'alvo_instancia_id' => 'zumbi-1',
        ]);

        $estadoDepois = $partida->fresh()->estado;
        $unidadeZumbi = $estadoDepois['campo']['2'][0];

        $this->assertSame(-1, $unidadeZumbi['bonus_ataque'] ?? null);
        $this->assertSame(
            1,
            $motor->getUnitAttack($estadoDepois, 2, $unidadeZumbi),
            'ATK efetivo deve ser 2 base - 1 decomposição = 1'
        );
        $this->assertSame(4, $unidadeZumbi['vida_atual']);

        $tiposAnimacao = array_column($resultado['animacoes'] ?? [], 'tipo');
        $this->assertContains('debuff_ataque', $tiposAnimacao);
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
