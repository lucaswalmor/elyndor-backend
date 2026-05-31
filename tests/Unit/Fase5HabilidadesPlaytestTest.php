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

class Fase5HabilidadesPlaytestTest extends TestCase
{
    use RefreshDatabase;

    public function test_artilharia_eletrica_transfere_overkill_para_inimigo_aleatorio(): void
    {
        [$artilharia, $alvoFraco, $alvoExtra] = $this->criarTrioCombate(
            'artilharia-teste',
            ['tipo' => 'transferir_excesso_dano', 'alvo' => 'inimigo_aleatorio'],
            'ao_matar',
            'gatilho',
            5,
            3,
            4,
            4,
        );

        [$partida, $motor, $atacante] = $this->montarPartidaAtaque(
            $artilharia,
            [
                ['instancia_id' => 'alvo-1', 'card_id' => $alvoFraco->id, 'vida_atual' => 2, 'vida_max' => 2],
                ['instancia_id' => 'alvo-2', 'card_id' => $alvoExtra->id, 'vida_atual' => 4, 'vida_max' => 4],
            ],
            5,
        );

        $motor->processAction($partida, $atacante, [
            'acao' => 'atacar_unidade',
            'instancia_id' => 'atacante-1',
            'alvo_instancia_id' => 'alvo-1',
        ]);

        $estado = $partida->fresh()->estado;
        $sobrevivente = collect($estado['campo']['2'])->firstWhere('instancia_id', 'alvo-2');

        $this->assertNotNull($sobrevivente);
        $this->assertSame(1, $sobrevivente['vida_atual'], 'Overkill de 3 deve reduzir alvo secundário de 4 para 1');
    }

    public function test_pantera_sombria_causa_dano_bonus_contra_alvo_com_hp_cheio(): void
    {
        $pantera = Card::query()->create([
            'nome' => 'Pantera Sombria Teste',
            'slug' => 'pantera-sombria-teste',
            'linhagem' => 'ybyra',
            'raridade' => 'comum',
            'custo' => 3,
            'ataque' => 4,
            'vida' => 2,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $pantera->id,
            'nome' => 'Bote Silencioso',
            'tipo' => 'passiva',
            'gatilho' => null,
            'ordem' => 0,
            'efeito' => ['tipo' => 'dano_bonus_alvo_ileso', 'valor' => 2],
        ]);

        $alvo = Card::query()->create([
            'nome' => 'Alvo Cheio',
            'slug' => 'alvo-cheio-teste',
            'linhagem' => 'ybyra',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 1,
            'vida' => 7,
            'ativo' => true,
        ]);
        CardCatalog::flush();

        [$partida, $motor, $atacante] = $this->montarPartidaAtaque(
            $pantera,
            [['instancia_id' => 'alvo-1', 'card_id' => $alvo->id, 'vida_atual' => 7, 'vida_max' => 7]],
            4,
        );

        $motor->processAction($partida, $atacante, [
            'acao' => 'atacar_unidade',
            'instancia_id' => 'atacante-1',
            'alvo_instancia_id' => 'alvo-1',
        ]);

        $estado = $partida->fresh()->estado;
        $alvoDepois = $estado['campo']['2'][0] ?? null;

        $this->assertSame(4, $motor->getUnitAttack($estado, 1, $estado['campo']['1'][0]) - ($estado['campo']['1'][0]['bonus_ataque'] ?? 0));
        $this->assertNotNull($alvoDepois);
        $this->assertSame(1, $alvoDepois['vida_atual'], '4 ATK + 2 bônus = 6; alvo com 7 HP fica com 1');
    }

    public function test_sacerdotisa_aplica_veu_arcano_no_aliado_selecionado(): void
    {
        $sacerdotisa = Card::query()->create([
            'nome' => 'Sacerdotisa Teste',
            'slug' => 'sacerdotisa-teste',
            'linhagem' => 'karuna',
            'raridade' => 'rara',
            'custo' => 5,
            'ataque' => 2,
            'vida' => 5,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $sacerdotisa->id,
            'nome' => 'Ritual de Fogo',
            'tipo' => 'ativa',
            'gatilho' => null,
            'ordem' => 0,
            'efeito' => ['tipo' => 'veu_arcano', 'custo_energia' => 2, 'alvo' => 'unidade_aliada'],
        ]);

        $aliado = Card::query()->create([
            'nome' => 'Aliado Teste',
            'slug' => 'aliado-teste-sacerdotisa',
            'linhagem' => 'karuna',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 2,
            'vida' => 3,
            'ativo' => true,
        ]);
        CardCatalog::flush();

        $jogador = User::factory()->create(['nickname' => 'CurandeiroTeste']);
        $oponente = User::factory()->create(['nickname' => 'OponenteTeste']);

        $estado = [
            'turno' => 5,
            'jogador_da_vez' => 1,
            'revelacoes' => ['1' => [], '2' => []],
            'jogadores' => [
                '1' => $this->jogadorMinimo($jogador->id, 5),
                '2' => $this->jogadorMinimo($oponente->id),
            ],
            'campo' => [
                '1' => [
                    [
                        'instancia_id' => 'sacerdotisa-1',
                        'card_id' => $sacerdotisa->id,
                        'vida_atual' => 5,
                        'vida_max' => 5,
                        'bonus_ataque' => 0,
                        'pode_atacar' => false,
                        'foi_invocado_neste_turno' => false,
                        'silenciado' => false,
                        'efeitos' => [],
                        'flags' => [],
                    ],
                    [
                        'instancia_id' => 'aliado-1',
                        'card_id' => $aliado->id,
                        'vida_atual' => 3,
                        'vida_max' => 3,
                        'bonus_ataque' => 0,
                        'pode_atacar' => false,
                        'foi_invocado_neste_turno' => false,
                        'silenciado' => false,
                        'efeitos' => [],
                        'flags' => [],
                    ],
                ],
                '2' => [],
            ],
        ];

        $partida = $this->criarPartida($estado, $jogador, $oponente);
        $motor = app(MatchEngine::class);

        $motor->processAction($partida, $jogador, [
            'acao' => 'habilidade',
            'instancia_id' => 'sacerdotisa-1',
            'alvo_instancia_id' => 'aliado-1',
        ]);

        $estadoDepois = $partida->fresh()->estado;
        $sacerdotisaDepois = collect($estadoDepois['campo']['1'])->firstWhere('instancia_id', 'sacerdotisa-1');
        $aliadoDepois = collect($estadoDepois['campo']['1'])->firstWhere('instancia_id', 'aliado-1');

        $this->assertFalse($sacerdotisaDepois['flags']['escudo'] ?? false);
        $this->assertTrue($aliadoDepois['flags']['escudo'] ?? false);
    }

    public function test_fungo_devorador_aplica_veneno_em_todos_inimigos_ao_morrer(): void
    {
        $fungo = Card::query()->create([
            'nome' => 'Fungo Teste',
            'slug' => 'fungo-teste',
            'linhagem' => 'ybyra',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 1,
            'vida' => 4,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $fungo->id,
            'nome' => 'Esporos Tóxicos',
            'tipo' => 'gatilho',
            'gatilho' => 'ao_morrer',
            'ordem' => 0,
            'efeito' => ['tipo' => 'veneno_todas_inimigas', 'valor' => 1, 'duracao' => 2],
        ]);

        $inimigo = Card::query()->create([
            'nome' => 'Inimigo Teste',
            'slug' => 'inimigo-fungo-teste',
            'linhagem' => 'karuna',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 5,
            'vida' => 3,
            'ativo' => true,
        ]);
        CardCatalog::flush();

        $atacante = User::factory()->create(['nickname' => 'AtacanteFungo']);
        $defensor = User::factory()->create(['nickname' => 'DefensorFungo']);

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
                    'instancia_id' => 'atacante-1',
                    'card_id' => $inimigo->id,
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
                    'instancia_id' => 'fungo-1',
                    'card_id' => $fungo->id,
                    'vida_atual' => 1,
                    'vida_max' => 4,
                    'bonus_ataque' => 0,
                    'pode_atacar' => false,
                    'foi_invocado_neste_turno' => false,
                    'silenciado' => false,
                    'efeitos' => [],
                    'flags' => [],
                ]],
            ],
        ];

        $partida = $this->criarPartida($estado, $atacante, $defensor);
        $motor = app(MatchEngine::class);

        $motor->processAction($partida, $atacante, [
            'acao' => 'atacar_unidade',
            'instancia_id' => 'atacante-1',
            'alvo_instancia_id' => 'fungo-1',
        ]);

        $estadoDepois = $partida->fresh()->estado;
        $atacanteDepois = $estadoDepois['campo']['1'][0];

        $this->assertEmpty($estadoDepois['campo']['2']);
        $this->assertTrue(
            collect($atacanteDepois['efeitos'])->contains(fn ($efeito) => ($efeito['tipo'] ?? '') === 'veneno'),
        );
    }

    public function test_cultista_do_nexus_sacrifica_hp_e_buffa_aliado_selecionado(): void
    {
        $cultista = Card::query()->create([
            'nome' => 'Cultista Teste',
            'slug' => 'cultista-teste',
            'linhagem' => 'karuna',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 1,
            'vida' => 3,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $cultista->id,
            'nome' => 'Oferenda de Sangue',
            'tipo' => 'ativa',
            'gatilho' => null,
            'ordem' => 0,
            'efeito' => [
                'tipo' => 'sacrificio_buff_aliado_turno',
                'custo_energia' => 1,
                'custo_vida' => 2,
                'valor' => 2,
                'alvo' => 'unidade_aliada',
            ],
        ]);

        $aliado = Card::query()->create([
            'nome' => 'Aliado Cultista',
            'slug' => 'aliado-cultista-teste',
            'linhagem' => 'karuna',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 2,
            'vida' => 4,
            'ativo' => true,
        ]);
        CardCatalog::flush();

        $jogador = User::factory()->create(['nickname' => 'CultistaJogador']);
        $oponente = User::factory()->create(['nickname' => 'CultistaOponente']);

        $estado = [
            'turno' => 3,
            'jogador_da_vez' => 1,
            'revelacoes' => ['1' => [], '2' => []],
            'jogadores' => [
                '1' => $this->jogadorMinimo($jogador->id, 3),
                '2' => $this->jogadorMinimo($oponente->id),
            ],
            'campo' => [
                '1' => [
                    $this->unidadeCampo('cultista-1', $cultista->id, 3, 3),
                    $this->unidadeCampo('aliado-1', $aliado->id, 4, 4),
                ],
                '2' => [],
            ],
        ];

        $partida = $this->criarPartida($estado, $jogador, $oponente);
        $motor = app(MatchEngine::class);

        $motor->processAction($partida, $jogador, [
            'acao' => 'habilidade',
            'instancia_id' => 'cultista-1',
            'alvo_instancia_id' => 'aliado-1',
        ]);

        $estadoDepois = $partida->fresh()->estado;
        $cultistaDepois = collect($estadoDepois['campo']['1'])->firstWhere('instancia_id', 'cultista-1');
        $aliadoDepois = collect($estadoDepois['campo']['1'])->firstWhere('instancia_id', 'aliado-1');

        $this->assertSame(1, $cultistaDepois['vida_atual'], 'Cultista sacrifica 2 HP (3 → 1, mínimo 1)');
        $this->assertSame(2, $aliadoDepois['bonus_ataque_turno'] ?? 0);
        $this->assertSame(2, $estadoDepois['jogadores']['1']['energia_atual']);
    }

    public function test_engenheiro_chefe_reconstroi_ferroveu_do_cemiterio_com_metade_hp(): void
    {
        $engenheiro = Card::query()->create([
            'nome' => 'Engenheiro Chefe Teste',
            'slug' => 'engenheiro-chefe-teste',
            'linhagem' => 'ferroveu',
            'raridade' => 'rara',
            'custo' => 5,
            'ataque' => 2,
            'vida' => 5,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $engenheiro->id,
            'nome' => 'Linha de Montagem',
            'tipo' => 'ativa',
            'gatilho' => null,
            'ordem' => 0,
            'efeito' => [
                'tipo' => 'reviver_ultimo_aliado_linhagem',
                'linhagem' => 'ferroveu',
                'hp_percentual' => 50,
                'custo_energia' => 1,
            ],
        ]);

        $ferroveuMorto = Card::query()->create([
            'nome' => 'Tanque Morto',
            'slug' => 'tanque-morto-teste',
            'linhagem' => 'ferroveu',
            'raridade' => 'comum',
            'custo' => 4,
            'ataque' => 3,
            'vida' => 6,
            'ativo' => true,
        ]);
        CardCatalog::flush();

        $jogador = User::factory()->create(['nickname' => 'EngenheiroJogador']);
        $oponente = User::factory()->create(['nickname' => 'EngenheiroOponente']);

        $jogadorEstado = $this->jogadorMinimo($jogador->id, 3);
        $jogadorEstado['cemiterio'] = [$ferroveuMorto->id];

        $estado = [
            'turno' => 6,
            'jogador_da_vez' => 1,
            'revelacoes' => ['1' => [], '2' => []],
            'jogadores' => [
                '1' => $jogadorEstado,
                '2' => $this->jogadorMinimo($oponente->id),
            ],
            'campo' => [
                '1' => [$this->unidadeCampo('engenheiro-1', $engenheiro->id, 5, 5)],
                '2' => [],
            ],
        ];

        $partida = $this->criarPartida($estado, $jogador, $oponente);
        $motor = app(MatchEngine::class);

        $motor->processAction($partida, $jogador, [
            'acao' => 'habilidade',
            'instancia_id' => 'engenheiro-1',
        ]);

        $estadoDepois = $partida->fresh()->estado;

        $this->assertCount(2, $estadoDepois['campo']['1']);
        $reconstruida = collect($estadoDepois['campo']['1'])
            ->first(fn ($unidade) => $unidade['instancia_id'] !== 'engenheiro-1');

        $this->assertNotNull($reconstruida);
        $this->assertSame($ferroveuMorto->id, $reconstruida['card_id']);
        $this->assertSame(3, $reconstruida['vida_atual'], '50% de 6 HP máximo = 3');
        $this->assertSame(6, $reconstruida['vida_max']);
        $this->assertFalse($reconstruida['pode_atacar']);
    }

    public function test_tecnico_de_campo_restaura_hp_de_aliado_alvo(): void
    {
        $tecnico = Card::query()->create([
            'nome' => 'Técnico Teste',
            'slug' => 'tecnico-teste',
            'linhagem' => 'ferroveu',
            'raridade' => 'comum',
            'custo' => 3,
            'ataque' => 1,
            'vida' => 4,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $tecnico->id,
            'nome' => 'Reparo Rápido',
            'tipo' => 'ativa',
            'gatilho' => null,
            'ordem' => 0,
            'efeito' => [
                'tipo' => 'cura_alvo',
                'custo_energia' => 1,
                'valor' => 2,
                'alvo' => 'unidade_aliada',
            ],
        ]);

        $aliado = Card::query()->create([
            'nome' => 'Aliado Ferido',
            'slug' => 'aliado-ferido-teste',
            'linhagem' => 'ferroveu',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 2,
            'vida' => 5,
            'ativo' => true,
        ]);
        CardCatalog::flush();

        $jogador = User::factory()->create(['nickname' => 'TecnicoJogador']);
        $oponente = User::factory()->create(['nickname' => 'TecnicoOponente']);

        $estado = [
            'turno' => 4,
            'jogador_da_vez' => 1,
            'revelacoes' => ['1' => [], '2' => []],
            'jogadores' => [
                '1' => $this->jogadorMinimo($jogador->id, 3),
                '2' => $this->jogadorMinimo($oponente->id),
            ],
            'campo' => [
                '1' => [
                    $this->unidadeCampo('tecnico-1', $tecnico->id, 4, 4),
                    $this->unidadeCampo('aliado-1', $aliado->id, 2, 5),
                ],
                '2' => [],
            ],
        ];

        $partida = $this->criarPartida($estado, $jogador, $oponente);
        $motor = app(MatchEngine::class);

        $motor->processAction($partida, $jogador, [
            'acao' => 'habilidade',
            'instancia_id' => 'tecnico-1',
            'alvo_instancia_id' => 'aliado-1',
        ]);

        $estadoDepois = $partida->fresh()->estado;
        $aliadoDepois = collect($estadoDepois['campo']['1'])->firstWhere('instancia_id', 'aliado-1');

        $this->assertSame(4, $aliadoDepois['vida_atual'], 'Aliado com 2 HP recebe +2 de cura');
        $this->assertSame(2, $estadoDepois['jogadores']['1']['energia_atual']);
    }

    public function test_escudo_automato_aplica_veu_arcano_em_aliado_ao_invocar(): void
    {
        $escudo = Card::query()->create([
            'nome' => 'Escudo Autômato Teste',
            'slug' => 'escudo-automato-teste',
            'linhagem' => 'ferroveu',
            'raridade' => 'comum',
            'custo' => 3,
            'ataque' => 1,
            'vida' => 6,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $escudo->id,
            'nome' => 'Barreira Protetora',
            'tipo' => 'batalha_cry',
            'gatilho' => 'ao_invocar',
            'ordem' => 0,
            'efeito' => ['tipo' => 'veu_arcano_aliado_aleatorio', 'cargas' => 1],
        ]);

        $aliado = Card::query()->create([
            'nome' => 'Aliado Protegido',
            'slug' => 'aliado-protegido-teste',
            'linhagem' => 'ferroveu',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 2,
            'vida' => 4,
            'ativo' => true,
        ]);
        CardCatalog::flush();

        $jogador = User::factory()->create(['nickname' => 'EscudoJogador']);
        $oponente = User::factory()->create(['nickname' => 'EscudoOponente']);

        $jogadorEstado = $this->jogadorMinimo($jogador->id, 5);
        $jogadorEstado['mao'] = [[
            'instancia_id' => 'mao-escudo-1',
            'card_id' => $escudo->id,
        ]];

        $estado = [
            'turno' => 3,
            'jogador_da_vez' => 1,
            'revelacoes' => ['1' => [], '2' => []],
            'jogadores' => [
                '1' => $jogadorEstado,
                '2' => $this->jogadorMinimo($oponente->id),
            ],
            'campo' => [
                '1' => [$this->unidadeCampo('aliado-1', $aliado->id, 4, 4)],
                '2' => [],
            ],
        ];

        $partida = $this->criarPartida($estado, $jogador, $oponente);
        $motor = app(MatchEngine::class);

        $motor->processAction($partida, $jogador, [
            'acao' => 'invocar',
            'instancia_id' => 'mao-escudo-1',
        ]);

        $estadoDepois = $partida->fresh()->estado;
        $aliadoDepois = collect($estadoDepois['campo']['1'])->firstWhere('instancia_id', 'aliado-1');
        $escudoInvocado = collect($estadoDepois['campo']['1'])
            ->first(fn ($unidade) => $unidade['instancia_id'] !== 'aliado-1');

        $this->assertNotNull($escudoInvocado);
        $this->assertTrue($aliadoDepois['flags']['escudo'] ?? false, 'Aliado existente recebe Véu Arcano');
        $this->assertFalse($escudoInvocado['flags']['escudo'] ?? false, 'Escudo não aplica em si quando há outro aliado');
    }

    public function test_bomba_andante_causa_dano_ao_jogador_inimigo_ao_morrer(): void
    {
        $bomba = Card::query()->create([
            'nome' => 'Bomba Andante Teste',
            'slug' => 'bomba-andante-teste',
            'linhagem' => 'ferroveu',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 1,
            'vida' => 3,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $bomba->id,
            'nome' => 'Detonação',
            'tipo' => 'gatilho',
            'gatilho' => 'ao_morrer',
            'ordem' => 0,
            'efeito' => ['tipo' => 'dano_jogador_inimigo', 'valor' => 1],
        ]);

        $atacante = Card::query()->create([
            'nome' => 'Atacante Bomba',
            'slug' => 'atacante-bomba-teste',
            'linhagem' => 'karuna',
            'raridade' => 'comum',
            'custo' => 3,
            'ataque' => 5,
            'vida' => 3,
            'ativo' => true,
        ]);
        CardCatalog::flush();

        [$partida, $motor, $jogadorAtacante] = $this->montarPartidaAtaque(
            $atacante,
            [['instancia_id' => 'bomba-1', 'card_id' => $bomba->id, 'vida_atual' => 3, 'vida_max' => 3]],
        );

        $motor->processAction($partida, $jogadorAtacante, [
            'acao' => 'atacar_unidade',
            'instancia_id' => 'atacante-1',
            'alvo_instancia_id' => 'bomba-1',
        ]);

        $estadoDepois = $partida->fresh()->estado;

        $this->assertEmpty($estadoDepois['campo']['2']);
        $this->assertSame(19, $estadoDepois['jogadores']['1']['vida'], 'Atacante (slot 1) recebe 1 de dano ao matar a Bomba');
    }

    /** @return array<string, mixed> */
    private function unidadeCampo(
        string $instanciaId,
        int $cardId,
        int $vidaAtual,
        int $vidaMaxima,
        bool $podeAtacar = false,
    ): array {
        return [
            'instancia_id' => $instanciaId,
            'card_id' => $cardId,
            'vida_atual' => $vidaAtual,
            'vida_max' => $vidaMaxima,
            'bonus_ataque' => 0,
            'bonus_ataque_turno' => 0,
            'pode_atacar' => $podeAtacar,
            'foi_invocado_neste_turno' => false,
            'silenciado' => false,
            'efeitos' => [],
            'flags' => [],
        ];
    }

    /** @return array{0: Card, 1: Card, 2: Card} */
    private function criarTrioCombate(
        string $slugAtacante,
        array $efeitoSkill,
        string $gatilho,
        string $tipoSkill,
        int $atkAtacante,
        int $hpAlvo1,
        int $hpAlvo2,
        int $hpAlvo2Max,
    ): array {
        $atacante = Card::query()->create([
            'nome' => 'Atacante '.$slugAtacante,
            'slug' => $slugAtacante,
            'linhagem' => 'ferroveu',
            'raridade' => 'rara',
            'custo' => 5,
            'ataque' => $atkAtacante,
            'vida' => 5,
            'ativo' => true,
        ]);
        CardSkill::query()->create([
            'card_id' => $atacante->id,
            'nome' => 'Skill Teste',
            'tipo' => $tipoSkill,
            'gatilho' => $gatilho,
            'ordem' => 0,
            'efeito' => $efeitoSkill,
        ]);

        $alvoFraco = Card::query()->create([
            'nome' => 'Alvo Fraco',
            'slug' => $slugAtacante.'-fraco',
            'linhagem' => 'ferroveu',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 1,
            'vida' => $hpAlvo1,
            'ativo' => true,
        ]);

        $alvoExtra = Card::query()->create([
            'nome' => 'Alvo Extra',
            'slug' => $slugAtacante.'-extra',
            'linhagem' => 'ferroveu',
            'raridade' => 'comum',
            'custo' => 3,
            'ataque' => 2,
            'vida' => $hpAlvo2Max,
            'ativo' => true,
        ]);

        CardCatalog::flush();

        return [$atacante, $alvoFraco, $alvoExtra];
    }

    /** @return array{0: GameMatch, 1: MatchEngine, 2: User} */
    private function montarPartidaAtaque(Card $atacanteCard, array $defensores, int $atkEsperado = 0): array
    {
        $atacanteUser = User::factory()->create(['nickname' => 'Atacante'.$atacanteCard->id]);
        $defensorUser = User::factory()->create(['nickname' => 'Defensor'.$atacanteCard->id]);

        $campoDefensor = [];
        foreach ($defensores as $defensor) {
            $campoDefensor[] = array_merge([
                'bonus_ataque' => 0,
                'pode_atacar' => false,
                'foi_invocado_neste_turno' => false,
                'silenciado' => false,
                'efeitos' => [],
                'flags' => [],
            ], $defensor);
        }

        $estado = [
            'turno' => 5,
            'jogador_da_vez' => 1,
            'revelacoes' => ['1' => [], '2' => []],
            'jogadores' => [
                '1' => $this->jogadorMinimo($atacanteUser->id),
                '2' => $this->jogadorMinimo($defensorUser->id),
            ],
            'campo' => [
                '1' => [[
                    'instancia_id' => 'atacante-1',
                    'card_id' => $atacanteCard->id,
                    'vida_atual' => 5,
                    'vida_max' => 5,
                    'bonus_ataque' => 0,
                    'pode_atacar' => true,
                    'foi_invocado_neste_turno' => false,
                    'silenciado' => false,
                    'efeitos' => [],
                    'flags' => [],
                ]],
                '2' => $campoDefensor,
            ],
        ];

        $partida = $this->criarPartida($estado, $atacanteUser, $defensorUser);

        return [$partida, app(MatchEngine::class), $atacanteUser];
    }

    private function criarPartida(array $estado, User $jogador1, User $jogador2): GameMatch
    {
        $partida = GameMatch::query()->create([
            'modo' => 'normal',
            'status' => MatchStatus::EmAndamento,
            'jogador_da_vez' => 1,
            'turno' => $estado['turno'] ?? 3,
            'estado' => $estado,
        ]);

        MatchPlayer::query()->create([
            'match_id' => $partida->id,
            'user_id' => $jogador1->id,
            'player_slot' => 1,
            'is_bot' => false,
        ]);
        MatchPlayer::query()->create([
            'match_id' => $partida->id,
            'user_id' => $jogador2->id,
            'player_slot' => 2,
            'is_bot' => false,
        ]);

        return $partida;
    }

    /** @return array<string, mixed> */
    private function jogadorMinimo(int $userId, int $energia = 5): array
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
