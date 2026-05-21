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

class SapoVenenoAtaqueJogadorTest extends TestCase
{
    use RefreshDatabase;

    public function test_veneno_aplica_no_jogador_ao_atacar_rosto(): void
    {
        $sapo = Card::query()->create([
            'nome' => 'Sapo Tóxico',
            'slug' => 'sapo-toxico-teste',
            'linhagem' => 'ybyra',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 2,
            'vida' => 4,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $sapo->id,
            'nome' => 'Veneno Persistente',
            'tipo' => 'gatilho',
            'gatilho' => 'ao_atacar',
            'ordem' => 0,
            'efeito' => ['tipo' => 'veneno', 'valor' => 1, 'duracao' => 2],
        ]);
        CardCatalog::flush();

        $atacante = User::factory()->create(['nickname' => 'AtacanteSapo']);
        $defensor = User::factory()->create(['nickname' => 'DefensorSapo']);

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
                    'instancia_id' => 'sapo-1',
                    'card_id' => $sapo->id,
                    'vida_atual' => 4,
                    'vida_max' => 4,
                    'bonus_ataque' => 0,
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
        $motor->processAction($partida, $atacante, [
            'acao' => 'atacar_jogador',
            'instancia_id' => 'sapo-1',
        ]);

        $estadoDepois = $partida->fresh()->estado;
        $efeitosOponente = $estadoDepois['jogadores']['2']['efeitos'] ?? [];

        $this->assertNotEmpty($efeitosOponente);
        $this->assertSame('veneno', $efeitosOponente[0]['tipo'] ?? null);
        $this->assertSame(1, $efeitosOponente[0]['valor'] ?? null);
        $this->assertSame(2, $efeitosOponente[0]['duracao'] ?? null);
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
            'energia_bonus_turno' => 0,
            'efeitos' => [],
        ];
    }
}
