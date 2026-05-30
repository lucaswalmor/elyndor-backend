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

class TitaMagmaticoImuneSilencioTest extends TestCase
{
    use RefreshDatabase;

    public function test_tita_magmatico_nao_recebe_silencio_ao_ser_atacado_por_monge_apodrecido(): void
    {
        $monge = Card::query()->create([
            'nome' => 'Monge Apodrecido',
            'slug' => 'monge-apodrecido-teste',
            'linhagem' => 'anhanga',
            'raridade' => 'comum',
            'custo' => 4,
            'ataque' => 2,
            'vida' => 6,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $monge->id,
            'nome' => 'Silêncio',
            'tipo' => 'gatilho',
            'gatilho' => 'ao_atacar',
            'ordem' => 0,
            'efeito' => ['tipo' => 'silencio', 'duracao' => 1, 'respeita_imune_controle' => true],
        ]);

        $tita = Card::query()->create([
            'nome' => 'Titã Magmático',
            'slug' => 'tita-magmatico-teste',
            'linhagem' => 'karuna',
            'raridade' => 'lendaria',
            'custo' => 7,
            'ataque' => 6,
            'vida' => 10,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $tita->id,
            'nome' => 'Corpo Colossal',
            'tipo' => 'passiva',
            'gatilho' => null,
            'ordem' => 0,
            'efeito' => ['tipo' => 'imune_remocao_direta'],
        ]);
        CardCatalog::flush();

        $jogadorAtacante = User::factory()->create(['nickname' => 'AtacanteTita']);
        $jogadorDefensor = User::factory()->create(['nickname' => 'DefensorTita']);

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
                    'instancia_id' => 'monge-1',
                    'card_id' => $monge->id,
                    'vida_atual' => 6,
                    'vida_max' => 6,
                    'bonus_ataque' => 0,
                    'pode_atacar' => true,
                    'foi_invocado_neste_turno' => false,
                    'silenciado' => false,
                    'efeitos' => [],
                    'flags' => [],
                ]],
                '2' => [[
                    'instancia_id' => 'tita-1',
                    'card_id' => $tita->id,
                    'vida_atual' => 10,
                    'vida_max' => 10,
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
            'instancia_id' => 'monge-1',
            'alvo_instancia_id' => 'tita-1',
        ]);

        $estadoDepois = $partida->fresh()->estado;
        $unidadeTita = $estadoDepois['campo']['2'][0];

        $this->assertFalse($unidadeTita['silenciado'] ?? false);
        $this->assertEmpty(
            array_filter($unidadeTita['efeitos'] ?? [], fn ($efeito) => ($efeito['tipo'] ?? '') === 'silencio'),
        );

        $tiposAnimacao = array_column($resultado['animacoes'] ?? [], 'tipo');
        $this->assertNotContains('silencio', $tiposAnimacao);
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
