<?php

namespace Tests\Unit;

use App\Enums\MatchStatus;
use App\Models\Card;
use App\Models\CardSkill;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Services\Game\MatchEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaoVulcanicoBonusAtaqueTurnoTest extends TestCase
{
    use RefreshDatabase;

    public function test_bonus_ataque_charge_some_ao_finalizar_turno(): void
    {
        $cao = Card::query()->create([
            'nome' => 'Cão Vulcânico',
            'slug' => 'cao-vulcanico-teste',
            'linhagem' => 'karuna',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 2,
            'vida' => 2,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $cao->id,
            'nome' => 'Fúria Inicial',
            'tipo' => 'grito_guerra',
            'ordem' => 0,
            'efeito' => ['tipo' => 'charge', 'bonus_ataque' => 1, 'pode_atacar_imediato' => true],
        ]);

        $jogador = User::factory()->create(['nickname' => 'TesteCao']);
        $oponente = User::factory()->create(['nickname' => 'OponenteCao']);

        $unidadeId = 'inst-cao-teste';
        $estado = [
            'turno' => 2,
            'jogador_da_vez' => 1,
            'revelacoes' => ['1' => [], '2' => []],
            'jogadores' => [
                '1' => $this->jogadorMinimo($jogador->id),
                '2' => $this->jogadorMinimo($oponente->id),
            ],
            'campo' => [
                '1' => [[
                    'instancia_id' => $unidadeId,
                    'card_id' => $cao->id,
                    'vida_atual' => 2,
                    'vida_max' => 2,
                    'bonus_ataque' => 0,
                    'bonus_ataque_turno' => 1,
                    'pode_atacar' => true,
                    'foi_invocado_neste_turno' => false,
                    'silenciado' => false,
                    'efeitos' => [],
                    'flags' => [],
                ]],
                '2' => [],
            ],
        ];

        $partida = GameMatch::query()->create([
            'modo' => 'normal',
            'status' => MatchStatus::EmAndamento,
            'jogador_da_vez' => 1,
            'turno' => 2,
            'estado' => $estado,
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

        $motor = app(MatchEngine::class);
        $ataqueComBonus = $motor->getUnitAttack($estado, 1, $estado['campo'][1][0]);
        $this->assertSame(3, $ataqueComBonus, 'Com bonus_ataque_turno o ATK deve ser 2+1');

        $motor->processAction($partida, $jogador, ['acao' => 'finalizar_turno']);
        $estadoDepois = $partida->fresh()->estado;
        $unidadeDepois = $estadoDepois['campo'][1][0];

        $this->assertSame(0, $unidadeDepois['bonus_ataque_turno'] ?? -1);
        $ataqueSemBonus = $motor->getUnitAttack($estadoDepois, 1, $unidadeDepois);
        $this->assertSame(2, $ataqueSemBonus, 'Após o fim do turno o bônus de charge não deve persistir');
    }

    public function test_charge_aplica_bonus_ataque_turno_e_nao_bonus_ataque_permanente(): void
    {
        $cao = Card::query()->create([
            'nome' => 'Cão Vulcânico',
            'slug' => 'cao-vulcanico-charge',
            'linhagem' => 'karuna',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 3,
            'vida' => 2,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $cao->id,
            'nome' => 'Fúria Inicial',
            'tipo' => 'grito_guerra',
            'ordem' => 0,
            'efeito' => ['tipo' => 'charge', 'bonus_ataque' => 1, 'pode_atacar_imediato' => true],
        ]);

        $estado = [
            'turno' => 1,
            'jogador_da_vez' => 1,
            'revelacoes' => ['1' => [], '2' => []],
            'jogadores' => [
                '1' => $this->jogadorMinimo(1),
                '2' => $this->jogadorMinimo(2),
            ],
            'campo' => ['1' => [], '2' => []],
        ];

        $unidade = [
            'instancia_id' => 'u1',
            'card_id' => $cao->id,
            'vida_atual' => 2,
            'vida_max' => 2,
            'bonus_ataque' => 0,
            'bonus_ataque_turno' => 0,
            'pode_atacar' => false,
            'foi_invocado_neste_turno' => true,
            'silenciado' => false,
            'efeitos' => [],
            'flags' => [],
        ];

        $resolver = app(\App\Services\Game\EffectResolver::class);
        $resolver->bindEngine(app(MatchEngine::class));
        $animacoes = [];
        $resolver->apply($estado, 1, $unidade, ['tipo' => 'charge', 'bonus_ataque' => 1, 'pode_atacar_imediato' => true], $animacoes);

        $this->assertSame(1, $unidade['bonus_ataque_turno'] ?? 0);
        $this->assertSame(0, $unidade['bonus_ataque'] ?? -1);
    }

    /** @return array<string, mixed> */
    private function jogadorMinimo(int $userId): array
    {
        return [
            'user_id' => $userId,
            'vida' => 20,
            'energia_atual' => 5,
            'energia_maxima' => 8,
            'energia_reservada' => 0,
            'ja_atacou_neste_turno' => false,
            'mao' => [],
            'deck' => [],
            'cemiterio' => [],
            'ressurreicao_usada' => false,
            'ressurreicao_pendente' => false,
            'energia_bonus_turno' => 0,
        ];
    }
}
