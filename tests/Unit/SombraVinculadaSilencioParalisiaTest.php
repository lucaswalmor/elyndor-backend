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

class SombraVinculadaSilencioParalisiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_habilidade_ativa_aplica_silencio_e_paralisia_no_alvo(): void
    {
        $sombra = Card::query()->create([
            'nome' => 'Sombra Vinculada',
            'slug' => 'sombra-vinculada-teste',
            'linhagem' => 'anhanga',
            'raridade' => 'comum',
            'custo' => 4,
            'ataque' => 2,
            'vida' => 5,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $sombra->id,
            'nome' => 'Maldição Vinculada',
            'tipo' => 'ativa',
            'gatilho' => null,
            'ordem' => 0,
            'efeito' => [
                'tipo' => 'silencio_paralisia',
                'custo_energia' => 1,
                'alvo' => 'unidade_inimiga',
                'duracao' => 1,
            ],
        ]);

        $alvo = Card::query()->create([
            'nome' => 'Alvo Teste',
            'slug' => 'alvo-teste-sombra',
            'linhagem' => 'karuna',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 2,
            'vida' => 4,
            'ativo' => true,
        ]);
        CardCatalog::flush();

        $jogador = User::factory()->create(['nickname' => 'AtacanteSombra']);
        $oponente = User::factory()->create(['nickname' => 'OponenteSombra']);

        $estado = [
            'turno' => 2,
            'jogador_da_vez' => 1,
            'revelacoes' => ['1' => [], '2' => []],
            'jogadores' => [
                '1' => $this->jogadorMinimo($jogador->id, 5),
                '2' => $this->jogadorMinimo($oponente->id),
            ],
            'campo' => [
                '1' => [[
                    'instancia_id' => 'sombra-1',
                    'card_id' => $sombra->id,
                    'vida_atual' => 5,
                    'vida_max' => 5,
                    'pode_atacar' => false,
                    'foi_invocado_neste_turno' => false,
                    'silenciado' => false,
                    'efeitos' => [],
                    'flags' => [],
                ]],
                '2' => [[
                    'instancia_id' => 'alvo-1',
                    'card_id' => $alvo->id,
                    'vida_atual' => 4,
                    'vida_max' => 4,
                    'pode_atacar' => true,
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
        $motor->processAction($partida, $jogador, [
            'acao' => 'habilidade',
            'instancia_id' => 'sombra-1',
            'alvo_instancia_id' => 'alvo-1',
        ]);

        $alvoDepois = $partida->fresh()->estado['campo']['2'][0];
        $tiposEfeito = array_column($alvoDepois['efeitos'] ?? [], 'tipo');

        $this->assertContains('silencio', $tiposEfeito);
        $this->assertContains('paralisia', $tiposEfeito);
        $this->assertTrue($alvoDepois['silenciado'] ?? false);
        $this->assertFalse($alvoDepois['pode_atacar'] ?? true);
        $this->assertSame(4, $partida->fresh()->estado['jogadores']['1']['energia_atual']);
    }

    /** @return array<string, mixed> */
    private function jogadorMinimo(int $userId, int $energia = 3): array
    {
        return [
            'user_id' => $userId,
            'vida' => 20,
            'energia_atual' => $energia,
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
