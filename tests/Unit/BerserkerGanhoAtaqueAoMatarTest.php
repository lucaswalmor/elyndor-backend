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

class BerserkerGanhoAtaqueAoMatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_berserker_ganha_ataque_permanente_ao_matar_inimigo(): void
    {
        $berserker = Card::query()->create([
            'nome' => 'Berserker das Brasas',
            'slug' => 'berserker-das-brasas-teste',
            'linhagem' => 'karuna',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 3,
            'vida' => 1,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $berserker->id,
            'nome' => 'Sede de Sangue',
            'tipo' => 'gatilho',
            'gatilho' => 'ao_matar',
            'ordem' => 0,
            'efeito' => ['tipo' => 'ganho_ataque_ao_matar', 'valor' => 1],
        ]);

        $alvo = Card::query()->create([
            'nome' => 'Alvo Frágil',
            'slug' => 'alvo-fragil-teste',
            'linhagem' => 'karuna',
            'raridade' => 'comum',
            'custo' => 1,
            'ataque' => 1,
            'vida' => 2,
            'ativo' => true,
        ]);
        CardCatalog::flush();

        $atacante = User::factory()->create(['nickname' => 'AtacanteBerserker']);
        $defensor = User::factory()->create(['nickname' => 'DefensorBerserker']);

        $estado = [
            'turno' => 3,
            'jogador_da_vez' => 1,
            'revelacoes' => ['1' => [], '2' => []],
            'jogadores' => [
                '1' => $this->jogadorMinimo($atacante->id),
                '2' => $this->jogadorMinimo($defensor->id),
            ],
            'campo' => [
                '1' => [[
                    'instancia_id' => 'berserker-1',
                    'card_id' => $berserker->id,
                    'vida_atual' => 1,
                    'vida_max' => 1,
                    'bonus_ataque' => 0,
                    'pode_atacar' => true,
                    'foi_invocado_neste_turno' => false,
                    'silenciado' => false,
                    'efeitos' => [],
                    'flags' => [],
                ]],
                '2' => [[
                    'instancia_id' => 'alvo-1',
                    'card_id' => $alvo->id,
                    'vida_atual' => 2,
                    'vida_max' => 2,
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
            'user_id' => $atacante->id,
            'player_slot' => 1,
            'is_bot' => false,
        ]);
        MatchPlayer::query()->create([
            'match_id' => $partida->id,
            'user_id' => $defensor->id,
            'player_slot' => 2,
            'is_bot' => false,
        ]);

        $motor = app(MatchEngine::class);
        $resultado = $motor->processAction($partida, $atacante, [
            'acao' => 'atacar_unidade',
            'instancia_id' => 'berserker-1',
            'alvo_instancia_id' => 'alvo-1',
        ]);

        $estadoDepois = $partida->fresh()->estado;
        $unidadeBerserker = $estadoDepois['campo']['1'][0];

        $this->assertSame(1, $unidadeBerserker['bonus_ataque'] ?? null);
        $this->assertSame(
            4,
            $motor->getUnitAttack($estadoDepois, 1, $unidadeBerserker),
            'ATK efetivo deve ser 3 base + 1 permanente'
        );

        $tiposAnimacao = array_column($resultado['animacoes'] ?? [], 'tipo');
        $this->assertContains('buff_ataque', $tiposAnimacao);
        $this->assertContains('morte', $tiposAnimacao);
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
