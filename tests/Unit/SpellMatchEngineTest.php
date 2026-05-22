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
use InvalidArgumentException;
use Tests\TestCase;

class SpellMatchEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_spell_com_alvo_causa_efeito_sai_da_mao_e_vai_para_cemiterio(): void
    {
        [$jogador, $oponente] = $this->players();
        $spell = $this->spell('Faísca Arcana Teste', 'spell-faisca-teste', ['tipo' => 'dano_alvo', 'valor' => 2, 'alvo' => 'unidade_inimiga']);
        $unit = $this->unit('Alvo Teste', 'alvo-spell-teste', 1, 4);

        $match = $this->match($jogador, $oponente, [
            '1' => $this->jogadorEstado($jogador->id, 3, [['instancia_id' => 'spell-mao', 'card_id' => $spell->id]]),
            '2' => $this->jogadorEstado($oponente->id),
        ], [
            '1' => [],
            '2' => [$this->campoUnit('alvo-1', $unit->id, 4)],
        ]);

        app(MatchEngine::class)->processAction($match, $jogador, [
            'acao' => 'jogar_feitico',
            'instancia_id' => 'spell-mao',
            'alvo_instancia_id' => 'alvo-1',
        ]);

        $estado = $match->fresh()->estado;
        $this->assertSame([], $estado['jogadores']['1']['mao']);
        $this->assertSame([$spell->id], $estado['jogadores']['1']['cemiterio']);
        $this->assertSame(2, $estado['campo'][2][0]['vida_atual']);
        $this->assertSame([], $estado['campo'][1]);
    }

    public function test_spell_com_alvo_obrigatorio_rejeita_alvo_ausente(): void
    {
        [$jogador, $oponente] = $this->players();
        $spell = $this->spell('Toque Vital Teste', 'spell-toque-teste', ['tipo' => 'cura_alvo', 'valor' => 3, 'alvo' => 'unidade_aliada']);

        $match = $this->match($jogador, $oponente, [
            '1' => $this->jogadorEstado($jogador->id, 3, [['instancia_id' => 'spell-mao', 'card_id' => $spell->id]]),
            '2' => $this->jogadorEstado($oponente->id),
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(MatchEngine::class)->processAction($match, $jogador, [
            'acao' => 'jogar_feitico',
            'instancia_id' => 'spell-mao',
        ]);
    }

    public function test_veu_arcano_bloqueia_spell_hostil_de_controle(): void
    {
        [$jogador, $oponente] = $this->players();
        $spell = $this->spell('Silêncio Teste', 'spell-silencio-teste', ['tipo' => 'silencio', 'duracao' => 1, 'alvo' => 'unidade_inimiga']);
        $unit = $this->unit('Protegido Teste', 'protegido-spell-teste', 2, 4);

        $match = $this->match($jogador, $oponente, [
            '1' => $this->jogadorEstado($jogador->id, 3, [['instancia_id' => 'spell-mao', 'card_id' => $spell->id]]),
            '2' => $this->jogadorEstado($oponente->id),
        ], [
            '1' => [],
            '2' => [$this->campoUnit('alvo-1', $unit->id, 4, ['flags' => ['escudo' => true]])],
        ]);

        app(MatchEngine::class)->processAction($match, $jogador, [
            'acao' => 'jogar_feitico',
            'instancia_id' => 'spell-mao',
            'alvo_instancia_id' => 'alvo-1',
        ]);

        $alvo = $match->fresh()->estado['campo'][2][0];
        $this->assertFalse($alvo['silenciado']);
        $this->assertArrayNotHasKey('escudo', $alvo['flags']);
    }

    public function test_spells_no_cemiterio_nao_voltam_para_o_deck_no_reshuffle(): void
    {
        [$jogador, $oponente] = $this->players();
        $spell = $this->spell('Pulso Teste', 'spell-pulso-teste', ['tipo' => 'cura_todos_aliados', 'valor' => 2]);
        $unit = $this->unit('Carta Reciclável', 'carta-reciclavel-teste', 1, 1);

        $match = $this->match($jogador, $oponente, [
            '1' => $this->jogadorEstado($jogador->id, 3),
            '2' => array_merge($this->jogadorEstado($oponente->id, 3), [
                'deck' => [],
                'cemiterio' => [$spell->id, $unit->id],
            ]),
        ]);

        app(MatchEngine::class)->processAction($match, $jogador, ['acao' => 'finalizar_turno']);

        $estado = $match->fresh()->estado;
        $this->assertSame([], $estado['jogadores']['2']['deck']);
        $this->assertSame($unit->id, $estado['jogadores']['2']['mao'][0]['card_id'] ?? null);
        $this->assertSame([$spell->id], $estado['jogadores']['2']['cemiterio']);
    }

    private function spell(string $nome, string $slug, array $efeito): Card
    {
        $card = Card::query()->create([
            'nome' => $nome,
            'slug' => $slug,
            'linhagem' => 'neutra',
            'classe' => 'Feitiço',
            'raridade' => 'comum',
            'tipo' => 'spell',
            'custo' => 1,
            'ataque' => 0,
            'vida' => 0,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $card->id,
            'nome' => $nome,
            'tipo' => 'spell',
            'gatilho' => 'ao_jogar',
            'ordem' => 0,
            'efeito' => $efeito,
        ]);
        CardCatalog::flush();

        return $card;
    }

    private function unit(string $nome, string $slug, int $ataque, int $vida): Card
    {
        $card = Card::query()->create([
            'nome' => $nome,
            'slug' => $slug,
            'linhagem' => 'karuna',
            'raridade' => 'comum',
            'tipo' => 'unit',
            'custo' => 1,
            'ataque' => $ataque,
            'vida' => $vida,
            'ativo' => true,
        ]);
        CardCatalog::flush();

        return $card;
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function players(): array
    {
        return [
            User::factory()->create(['nickname' => 'spell_player']),
            User::factory()->create(['nickname' => 'spell_opponent']),
        ];
    }

    private function match(User $jogador, User $oponente, array $jogadores, array $campo = ['1' => [], '2' => []]): GameMatch
    {
        $match = GameMatch::query()->create([
            'modo' => 'normal',
            'status' => MatchStatus::EmAndamento,
            'jogador_da_vez' => 1,
            'turno' => 2,
            'estado' => [
                'turno' => 2,
                'jogador_da_vez' => 1,
                'revelacoes' => ['1' => [], '2' => []],
                'jogadores' => $jogadores,
                'campo' => $campo,
            ],
        ]);

        MatchPlayer::query()->create(['match_id' => $match->id, 'user_id' => $jogador->id, 'player_slot' => 1, 'is_bot' => false]);
        MatchPlayer::query()->create(['match_id' => $match->id, 'user_id' => $oponente->id, 'player_slot' => 2, 'is_bot' => false]);

        return $match;
    }

    private function jogadorEstado(int $userId, int $energia = 5, array $mao = []): array
    {
        return [
            'user_id' => $userId,
            'vida' => 20,
            'energia_atual' => $energia,
            'energia_maxima' => 8,
            'energia_reservada' => 0,
            'ja_atacou_neste_turno' => false,
            'mao' => $mao,
            'deck' => [],
            'cemiterio' => [],
            'ressurreicao_usada' => false,
            'ressurreicao_pendente' => false,
            'energia_bonus_turno' => 0,
        ];
    }

    private function campoUnit(string $instanciaId, int $cardId, int $vidaAtual, array $overrides = []): array
    {
        return array_merge([
            'instancia_id' => $instanciaId,
            'card_id' => $cardId,
            'vida_atual' => $vidaAtual,
            'vida_max' => $vidaAtual,
            'bonus_ataque' => 0,
            'bonus_ataque_turno' => 0,
            'pode_atacar' => false,
            'foi_invocado_neste_turno' => false,
            'silenciado' => false,
            'efeitos' => [],
            'flags' => [],
        ], $overrides);
    }
}
