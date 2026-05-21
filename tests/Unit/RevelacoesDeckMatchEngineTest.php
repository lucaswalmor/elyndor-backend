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

    public function test_revelacoes_persistem_apos_finalizar_turno_enquanto_timer_ativo(): void
    {
        $cartaRevelada = Card::query()->create([
            'nome' => 'Carta Topo',
            'slug' => 'carta-topo-teste',
            'linhagem' => 'ybyra',
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
            'revelacoes_expira_em' => [
                '1' => now()->addSeconds(60)->toIso8601String(),
                '2' => null,
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

        $this->assertSame([$cartaRevelada->id], $estado['revelacoes']['1'] ?? []);
        $this->assertNotEmpty($estado['revelacoes_expira_em']['1'] ?? null);
        $this->assertSame(2, (int) $estado['jogador_da_vez']);
    }

    public function test_revelacoes_somem_quando_timer_expira(): void
    {
        $cartaRevelada = Card::query()->create([
            'nome' => 'Carta Expirada',
            'slug' => 'carta-expirada-teste',
            'linhagem' => 'ybyra',
            'raridade' => 'comum',
            'custo' => 1,
            'ataque' => 1,
            'vida' => 1,
        ]);

        $estado = [
            'turno' => 2,
            'jogador_da_vez' => 1,
            'revelacoes' => ['1' => [$cartaRevelada->id], '2' => []],
            'revelacoes_expira_em' => [
                '1' => now()->subSecond()->toIso8601String(),
                '2' => null,
            ],
            'jogadores' => [
                '1' => ['user_id' => 1, 'vida' => 20, 'energia_atual' => 5, 'energia_maxima' => 8, 'deck' => [], 'mao' => [], 'cemiterio' => []],
                '2' => ['user_id' => 2, 'vida' => 20, 'energia_atual' => 5, 'energia_maxima' => 8, 'deck' => [], 'mao' => [], 'cemiterio' => []],
            ],
            'campo' => ['1' => [], '2' => []],
        ];

        $motor = app(MatchEngine::class);
        $motor->purgeExpiredRevelacoes($estado);

        $this->assertSame([], $estado['revelacoes']['1']);
        $this->assertNull($estado['revelacoes_expira_em']['1']);
    }
}
