<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\CardSkill;
use Illuminate\Database\Seeder;

class CartasSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->cards() as $c) {
            $card = Card::updateOrCreate(
                ['slug' => $c['slug']],
                [
                    'nome'        => $c['nome'],
                    'descricao'   => $c['descricao'] ?? null,
                    'faccao'      => $c['faccao'],
                    'classe'      => $c['classe'] ?? null,
                    'raridade'    => $c['raridade'],
                    'tipo'        => $c['tipo'] ?? 'unidade',
                    'custo'       => $c['custo'],
                    'ataque'      => $c['ataque'] ?? 0,
                    'vida'        => $c['vida'] ?? 0,
                    'imagem'      => $c['imagem'] ?? $c['slug'],
                    'imagem_path' => $c['imagem_path'] ?? null,
                    'ativo'       => true,
                    'colecionavel'=> true,
                ]
            );

            $card->skills()->delete();

            foreach ($c['habilidades'] ?? [] as $h) {
                CardSkill::create([
                    'card_id' => $card->id,
                    'nome'    => $h['nome'],
                    'tipo'    => $h['tipo'],
                    'gatilho' => $h['gatilho'] ?? null,
                    'efeito'  => $h['efeito'],
                ]);
            }
        }
    }

    private function cards(): array
    {
        return [
            // ── Infernais ─────────────────────────────────────────────────────
            [
                'nome' => 'Carniceiro de Brasas', 'slug' => 'carniceiro-de-brasas',
                'faccao' => 'infernais', 'classe' => 'Demônio', 'raridade' => 'rara',
                'tipo' => 'unidade', 'custo' => 5, 'ataque' => 4, 'vida' => 6,
                'imagem' => 'carniceiro-de-brasas', 'imagem_path' => 'infernais/carniceiro_de_brasas.png',
                'descricao' => 'Ao morrer, causa 2 de dano a todas as unidades inimigas em campo.',
                'habilidades' => [['nome' => 'Morte Explosiva', 'tipo' => 'gatilho', 'gatilho' => 'ao_morrer',
                    'efeito' => ['tipo' => 'dano_todas_inimigas', 'valor' => 2]]],
            ],
            [
                'nome' => 'Cão Vulcânico', 'slug' => 'cao-vulcanico',
                'faccao' => 'infernais', 'classe' => 'Fera Infernal', 'raridade' => 'comum',
                'tipo' => 'unidade', 'custo' => 2, 'ataque' => 3, 'vida' => 2,
                'imagem' => 'cao-vulcanico', 'imagem_path' => 'infernais/cao_vulcanico.png',
                'descricao' => 'Pode atacar no turno em que é invocado. Recebe +1 ATK neste turno.',
                'habilidades' => [['nome' => 'Fúria Inicial', 'tipo' => 'batalha_cry', 'gatilho' => 'ao_invocar',
                    'efeito' => ['tipo' => 'charge', 'bonus_ataque' => 1, 'pode_atacar_imediato' => true]]],
            ],
            [
                'nome' => 'Bruxa Cinzenta', 'slug' => 'bruxa-cinzenta',
                'faccao' => 'infernais', 'classe' => 'Bruxa', 'raridade' => 'comum',
                'tipo' => 'unidade', 'custo' => 3, 'ataque' => 2, 'vida' => 3,
                'imagem' => 'bruxa-cinzenta', 'imagem_path' => 'infernais/bruxa_cinzenta.png',
                'descricao' => 'Ao atacar, o alvo recebe -1 ATK por 2 turnos.',
                'habilidades' => [['nome' => 'Maldição Sombria', 'tipo' => 'gatilho', 'gatilho' => 'ao_atacar',
                    'efeito' => ['tipo' => 'debuff_ataque', 'valor' => 1, 'duracao' => 2]]],
            ],
            [
                'nome' => 'Titã Magmático', 'slug' => 'tita-magmatico',
                'faccao' => 'infernais', 'classe' => 'Titã', 'raridade' => 'epica',
                'tipo' => 'unidade', 'custo' => 7, 'ataque' => 6, 'vida' => 10,
                'imagem' => 'tita-magmatico', 'imagem_path' => 'infernais/tita_magmatico.png',
                'descricao' => 'Não pode ser alvo de habilidades de remoção direta (Silêncio, destruição instantânea).',
                'habilidades' => [['nome' => 'Corpo Colossal', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'imune_remocao_direta']]],
            ],
            [
                'nome' => 'Morcego Ígneo', 'slug' => 'morcego-igneo',
                'faccao' => 'infernais', 'classe' => 'Fera Voadora', 'raridade' => 'comum',
                'tipo' => 'unidade', 'custo' => 1, 'ataque' => 1, 'vida' => 1,
                'imagem' => 'morcego-igneo', 'imagem_path' => 'infernais/morcego_igneo.png',
                'descricao' => 'Pode atacar o jogador inimigo diretamente mesmo com unidades inimigas em campo.',
                'habilidades' => [['nome' => 'Ataque Direto', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'ataque_direto_jogador']]],
            ],
            [
                'nome' => 'Rei das Correntes', 'slug' => 'rei-das-correntes',
                'faccao' => 'infernais', 'classe' => 'Senhor Infernal', 'raridade' => 'rara',
                'tipo' => 'unidade', 'custo' => 6, 'ataque' => 5, 'vida' => 7,
                'imagem' => 'rei-das-correntes', 'imagem_path' => 'infernais/rei_das_correntes.png',
                'descricao' => 'Ativo (1 energia): força uma unidade inimiga a atacar o Rei das Correntes neste turno.',
                'habilidades' => [['nome' => 'Gancho Infernal', 'tipo' => 'ativa', 'gatilho' => null,
                    'efeito' => ['tipo' => 'forcar_ataque_a_si', 'custo_energia' => 1, 'alvo' => 'unidade_inimiga']]],
            ],

            // ── Natureza ──────────────────────────────────────────────────────
            [
                'nome' => 'Guardião do Musgo', 'slug' => 'guardiao-do-musgo',
                'faccao' => 'natureza', 'classe' => 'Guardião', 'raridade' => 'rara',
                'tipo' => 'unidade', 'custo' => 5, 'ataque' => 2, 'vida' => 9,
                'imagem' => 'guardiao-do-musgo', 'imagem_path' => 'natureza/guardiao_do_musgo_card_v2.png',
                'descricao' => 'Recebe -1 de dano de todos os ataques (mínimo 1).',
                'habilidades' => [['nome' => 'Casca Viva', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'reducao_dano', 'valor' => 1, 'minimo_dano' => 1]]],
            ],
            [
                'nome' => 'Aranha Lunar', 'slug' => 'aranha-lunar',
                'faccao' => 'natureza', 'classe' => 'Aracnídeo', 'raridade' => 'comum',
                'tipo' => 'unidade', 'custo' => 3, 'ataque' => 2, 'vida' => 4,
                'imagem' => 'aranha-lunar', 'imagem_path' => 'natureza/aranha_lunar.png',
                'descricao' => 'Ao atacar, o alvo não pode atacar no próximo turno.',
                'habilidades' => [['nome' => 'Teia Prisional', 'tipo' => 'gatilho', 'gatilho' => 'ao_atacar',
                    'efeito' => ['tipo' => 'nao_pode_atacar', 'duracao' => 1]]],
            ],
            [
                'nome' => 'Espírito da Raiz', 'slug' => 'espirito-da-raiz',
                'faccao' => 'natureza', 'classe' => 'Espírito', 'raridade' => 'comum',
                'tipo' => 'unidade', 'custo' => 4, 'ataque' => 1, 'vida' => 5,
                'imagem' => 'espirito-da-raiz', 'imagem_path' => 'natureza/espirito_da_raiz.png',
                'descricao' => 'No início de cada turno aliado, cura 2 HP de um aliado aleatório em campo.',
                'habilidades' => [['nome' => 'Cura Natural', 'tipo' => 'gatilho', 'gatilho' => 'inicio_turno_aliado',
                    'efeito' => ['tipo' => 'cura_aleatorio_aliado', 'valor' => 2]]],
            ],
            [
                'nome' => 'Sapo Tóxico', 'slug' => 'sapo-toxico',
                'faccao' => 'natureza', 'classe' => 'Anfíbio', 'raridade' => 'comum',
                'tipo' => 'unidade', 'custo' => 2, 'ataque' => 2, 'vida' => 4,
                'imagem' => 'sapo-toxico', 'imagem_path' => 'natureza/sapo_toxico.png',
                'descricao' => 'Ao atacar, aplica Veneno (1 dano por turno por 2 turnos).',
                'habilidades' => [['nome' => 'Veneno Persistente', 'tipo' => 'gatilho', 'gatilho' => 'ao_atacar',
                    'efeito' => ['tipo' => 'veneno', 'valor' => 1, 'duracao' => 2]]],
            ],
            [
                'nome' => 'Cervo Fantasma', 'slug' => 'cervo-fantasma',
                'faccao' => 'natureza', 'classe' => 'Espírito Animal', 'raridade' => 'comum',
                'tipo' => 'unidade', 'custo' => 4, 'ataque' => 3, 'vida' => 3,
                'imagem' => 'cervo-fantasma', 'imagem_path' => 'natureza/cervo_fantasma.png',
                'descricao' => 'Ignora o primeiro ataque ou habilidade recebida após ser invocado.',
                'habilidades' => [['nome' => 'Esquiva Etérea', 'tipo' => 'batalha_cry', 'gatilho' => 'ao_invocar',
                    'efeito' => ['tipo' => 'escudo_primeiro_golpe', 'cargas' => 1]]],
            ],
            [
                'nome' => 'Hidra do Pântano', 'slug' => 'hidra-do-pantano',
                'faccao' => 'natureza', 'classe' => 'Monstro', 'raridade' => 'rara',
                'tipo' => 'unidade', 'custo' => 6, 'ataque' => 4, 'vida' => 8,
                'imagem' => 'hidra-do-pantano', 'imagem_path' => 'natureza/hidra_do_pantano.png',
                'descricao' => 'Contra-ataca automaticamente uma vez por turno quando sobrevive a um ataque.',
                'habilidades' => [['nome' => 'Múltiplas Cabeças', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'contra_ataque_extra', 'limite_por_turno' => 1]]],
            ],

            // ── Mecânicos ─────────────────────────────────────────────────────
            [
                'nome' => 'Drone Sentinela', 'slug' => 'drone-sentinela',
                'faccao' => 'mecanicos', 'classe' => 'Drone', 'raridade' => 'comum',
                'tipo' => 'unidade', 'custo' => 2, 'ataque' => 2, 'vida' => 2,
                'imagem' => 'drone-sentinela', 'imagem_path' => 'mecanicos/drone_sentinela.png',
                'descricao' => 'Ao atacar, não recebe contra-ataque da unidade atacada.',
                'habilidades' => [['nome' => 'Tiro Longo', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'ataque_sem_retaliacao']]],
            ],
            [
                'nome' => 'Executor de Ferro', 'slug' => 'executor-de-ferro',
                'faccao' => 'mecanicos', 'classe' => 'Máquina', 'raridade' => 'rara',
                'tipo' => 'unidade', 'custo' => 5, 'ataque' => 5, 'vida' => 6,
                'imagem' => 'executor-de-ferro', 'imagem_path' => 'mecanicos/executor_de_ferro.png',
                'descricao' => 'Causa +2 dano adicional contra unidades com redução de dano.',
                'habilidades' => [['nome' => 'Quebra-Armadura', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'dano_bonus_vs_reducao', 'valor' => 2]]],
            ],
            [
                'nome' => 'Engenheira Tesla', 'slug' => 'engenheira-tesla',
                'faccao' => 'mecanicos', 'classe' => 'Inventora', 'raridade' => 'rara',
                'tipo' => 'unidade', 'custo' => 4, 'ataque' => 2, 'vida' => 4,
                'imagem' => 'engenheira-tesla', 'imagem_path' => 'mecanicos/engenheira_tesla.png',
                'descricao' => 'Enquanto estiver em campo, unidades Mecânicas aliadas recebem +1 ATK.',
                'habilidades' => [['nome' => 'Sobrecarga', 'tipo' => 'aura', 'gatilho' => null,
                    'efeito' => ['tipo' => 'aura_buff_ataque', 'valor' => 1, 'filtro_faccao' => 'mecanicos']]],
            ],
            [
                'nome' => 'Aranha de Sucata', 'slug' => 'aranha-de-sucata',
                'faccao' => 'mecanicos', 'classe' => 'Máquina', 'raridade' => 'comum',
                'tipo' => 'unidade', 'custo' => 3, 'ataque' => 2, 'vida' => 5,
                'imagem' => 'aranha-de-sucata', 'imagem_path' => 'mecanicos/aranha_de_sucata.png',
                'descricao' => 'Uma vez por partida, ao chegar a 0 HP, renasce com 1 HP em campo.',
                'habilidades' => [['nome' => 'Reconstrução', 'tipo' => 'gatilho', 'gatilho' => 'ao_morrer',
                    'efeito' => ['tipo' => 'ressurreicao_unica', 'vida' => 1, 'limite_partida' => 1]]],
            ],
            [
                'nome' => 'Tremor MK-II', 'slug' => 'tremor-mk-ii',
                'faccao' => 'mecanicos', 'classe' => 'Perfuração', 'raridade' => 'epica',
                'tipo' => 'unidade', 'custo' => 6, 'ataque' => 5, 'vida' => 5,
                'imagem' => 'tremor-mk-ii', 'imagem_path' => 'mecanicos/tremor_mk_ii.png',
                'descricao' => 'Pode atacar no turno em que é invocado (Charge).',
                'habilidades' => [['nome' => 'Escavação', 'tipo' => 'batalha_cry', 'gatilho' => 'ao_invocar',
                    'efeito' => ['tipo' => 'charge', 'pode_atacar_imediato' => true]]],
            ],
            [
                'nome' => 'Núcleo Autômato', 'slug' => 'nucleo-automato',
                'faccao' => 'mecanicos', 'classe' => 'Núcleo', 'raridade' => 'epica',
                'tipo' => 'unidade', 'custo' => 5, 'ataque' => 1, 'vida' => 4,
                'imagem' => 'nucleo-automato', 'imagem_path' => 'mecanicos/nucleo_automato_card.png',
                'descricao' => 'No início de cada turno aliado, concede +1 energia temporária (não acumula).',
                'habilidades' => [['nome' => 'Gerador', 'tipo' => 'gatilho', 'gatilho' => 'inicio_turno_aliado',
                    'efeito' => ['tipo' => 'energia_temporaria', 'valor' => 1, 'acumula' => false]]],
            ],

            // ── Mortos-Vivos ──────────────────────────────────────────────────
            [
                'nome' => 'Cavaleiro Sem Face', 'slug' => 'cavaleiro-sem-face',
                'faccao' => 'mortos_vivos', 'classe' => 'Morto-vivo', 'raridade' => 'rara',
                'tipo' => 'unidade', 'custo' => 4, 'ataque' => 4, 'vida' => 5,
                'imagem' => 'cavaleiro-sem-face', 'imagem_path' => 'mortos_vivos/cavaleiro_sem_face.png',
                'descricao' => 'Imune a Silêncio, Teia Prisional e Confusão.',
                'habilidades' => [['nome' => 'Sem Medo', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'imune_controle', 'efeitos' => ['silencio', 'nao_pode_atacar', 'confusao']]]],
            ],
            [
                'nome' => 'Costureira Macabra', 'slug' => 'costureira-macabra',
                'faccao' => 'mortos_vivos', 'classe' => 'Necromante', 'raridade' => 'rara',
                'tipo' => 'unidade', 'custo' => 5, 'ataque' => 3, 'vida' => 5,
                'imagem' => 'costureira-macabra', 'imagem_path' => 'mortos_vivos/costureira_macabra.png',
                'descricao' => 'Ao atacar, recupera HP igual ao dano causado (máximo 3 por ataque).',
                'habilidades' => [['nome' => 'Roubo Vital', 'tipo' => 'gatilho', 'gatilho' => 'ao_atacar',
                    'efeito' => ['tipo' => 'cura_por_dano', 'maximo' => 3]]],
            ],
            [
                'nome' => 'Corvo Funerário', 'slug' => 'corvo-funerario',
                'faccao' => 'mortos_vivos', 'classe' => 'Ave Sombria', 'raridade' => 'comum',
                'tipo' => 'unidade', 'custo' => 1, 'ataque' => 1, 'vida' => 1,
                'imagem' => 'corvo-funerario', 'imagem_path' => 'mortos_vivos/corvo_funerario.png',
                'descricao' => 'Ao ser invocado, revela a próxima carta do deck inimigo.',
                'habilidades' => [['nome' => 'Observador', 'tipo' => 'batalha_cry', 'gatilho' => 'ao_invocar',
                    'efeito' => ['tipo' => 'revelar_proxima_carta_deck', 'quantidade' => 1, 'alvo' => 'deck_inimigo']]],
            ],
            [
                'nome' => 'Monge Apodrecido', 'slug' => 'monge-apodrecido',
                'faccao' => 'mortos_vivos', 'classe' => 'Monge', 'raridade' => 'comum',
                'tipo' => 'unidade', 'custo' => 4, 'ataque' => 2, 'vida' => 6,
                'imagem' => 'monge-apodrecido', 'imagem_path' => 'mortos_vivos/monge_apodrecido.png',
                'descricao' => 'Ao atacar, aplica Silêncio por 1 turno. Não funciona contra Sem Medo.',
                'habilidades' => [['nome' => 'Silêncio', 'tipo' => 'gatilho', 'gatilho' => 'ao_atacar',
                    'efeito' => ['tipo' => 'silencio', 'duracao' => 1, 'respeita_imune_controle' => true]]],
            ],
            [
                'nome' => 'Gigante Ossuário', 'slug' => 'gigante-ossuario',
                'faccao' => 'mortos_vivos', 'classe' => 'Colosso', 'raridade' => 'epica',
                'tipo' => 'unidade', 'custo' => 7, 'ataque' => 5, 'vida' => 10,
                'imagem' => 'gigante-ossuario', 'imagem_path' => 'mortos_vivos/gigante_ossuario.png',
                'descricao' => 'Ganha +1 redução de dano permanente a cada ataque recebido (máximo +3).',
                'habilidades' => [['nome' => 'Ossos Reforçados', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'reducao_dano_acumulativa', 'valor_por_golpe' => 1, 'maximo' => 3]]],
            ],
            [
                'nome' => 'Criança do Véu', 'slug' => 'crianca-do-veu',
                'faccao' => 'mortos_vivos', 'classe' => 'Fantasma', 'raridade' => 'comum',
                'tipo' => 'unidade', 'custo' => 3, 'ataque' => 1, 'vida' => 4,
                'imagem' => 'crianca-do-veu', 'imagem_path' => 'mortos_vivos/crianca_do_veu.png',
                'descricao' => 'Ao atacar, o alvo tem 50% de chance de errar o próximo ataque.',
                'habilidades' => [['nome' => 'Confusão', 'tipo' => 'gatilho', 'gatilho' => 'ao_atacar',
                    'efeito' => ['tipo' => 'confusao', 'chance' => 50, 'duracao' => 1, 'respeita_imune_controle' => true]]],
            ],

            // ── Void / Celestiais ─────────────────────────────────────────────
            [
                'nome' => 'Oráculo Solar', 'slug' => 'oraculo-solar',
                'faccao' => 'void', 'classe' => 'Celestial', 'raridade' => 'comum',
                'tipo' => 'unidade', 'custo' => 4, 'ataque' => 2, 'vida' => 4,
                'imagem' => 'oraculo-solar', 'imagem_path' => 'celestiais/oraculo_solar.png',
                'descricao' => 'Ativo (1 energia): revela as 3 próximas cartas do deck inimigo.',
                'habilidades' => [['nome' => 'Profecia', 'tipo' => 'ativa', 'gatilho' => null,
                    'efeito' => ['tipo' => 'revelar_proxima_carta_deck', 'quantidade' => 3, 'alvo' => 'deck_inimigo', 'custo_energia' => 1]]],
            ],
            [
                'nome' => 'Aberração do Vazio', 'slug' => 'aberracao-do-vazio',
                'faccao' => 'void', 'classe' => 'Void', 'raridade' => 'rara',
                'tipo' => 'unidade', 'custo' => 5, 'ataque' => 4, 'vida' => 5,
                'imagem' => 'aberracao-do-vazio', 'imagem_path' => 'celestiais/aberracao_do_vazio_card_fixed.png',
                'descricao' => 'Ao ser invocado, destrói uma unidade inimiga aleatória (sem disparar ao morrer).',
                'habilidades' => [['nome' => 'Dobra Espacial', 'tipo' => 'batalha_cry', 'gatilho' => 'ao_invocar',
                    'efeito' => ['tipo' => 'destruir_aleatorio_inimigo', 'dispara_ao_morrer' => false]]],
            ],
            [
                'nome' => 'Serafim Partido', 'slug' => 'serafim-partido',
                'faccao' => 'void', 'classe' => 'Anjo', 'raridade' => 'epica',
                'tipo' => 'unidade', 'custo' => 6, 'ataque' => 3, 'vida' => 7,
                'imagem' => 'serafim-partido', 'imagem_path' => 'celestiais/serafim_partido.png',
                'descricao' => 'Ativo (2 energia): revive a última unidade aliada destruída com metade do HP máximo.',
                'habilidades' => [['nome' => 'Renascimento', 'tipo' => 'ativa', 'gatilho' => null,
                    'efeito' => ['tipo' => 'reviver_ultimo_aliado', 'hp_percentual' => 50, 'custo_energia' => 2]]],
            ],
            [
                'nome' => 'Eclipse Vivo', 'slug' => 'eclipse-vivo',
                'faccao' => 'void', 'classe' => 'Entidade', 'raridade' => 'rara',
                'tipo' => 'unidade', 'custo' => 5, 'ataque' => 2, 'vida' => 8,
                'imagem' => 'eclipse-vivo', 'imagem_path' => 'celestiais/eclipse_vivo.png',
                'descricao' => 'Enquanto estiver em campo, todas as unidades inimigas têm -1 ATK.',
                'habilidades' => [['nome' => 'Escuridão Total', 'tipo' => 'aura', 'gatilho' => null,
                    'efeito' => ['tipo' => 'aura_debuff_ataque', 'valor' => 1, 'alvo' => 'inimigos']]],
            ],
            [
                'nome' => 'Navegante Astral', 'slug' => 'navegante-astral',
                'faccao' => 'void', 'classe' => 'Mago Astral', 'raridade' => 'comum',
                'tipo' => 'unidade', 'custo' => 4, 'ataque' => 3, 'vida' => 4,
                'imagem' => 'navegante-astral', 'imagem_path' => 'celestiais/navegante_astral.png',
                'descricao' => 'Ao ser invocado, retorna um aliado em campo para a mão (ou cemitério se mão cheia).',
                'habilidades' => [['nome' => 'Troca Dimensional', 'tipo' => 'batalha_cry', 'gatilho' => 'ao_invocar',
                    'efeito' => ['tipo' => 'retornar_aliado_mao', 'selecao' => 'aliado_campo', 'se_mao_cheia' => 'cemiterio']]],
            ],
            [
                'nome' => 'Devorador de Estrelas', 'slug' => 'devorador-de-estrelas',
                'faccao' => 'void', 'classe' => 'Entidade Cósmica', 'raridade' => 'lendaria',
                'tipo' => 'unidade', 'custo' => 8, 'ataque' => 6, 'vida' => 8,
                'imagem' => 'devorador-de-estrelas', 'imagem_path' => 'celestiais/devorador_de_estrelas_card_fixed.png',
                'descricao' => 'Quando qualquer unidade morre, ganha +1 ATK e +1 HP permanentes (máximo +3/+3).',
                'habilidades' => [['nome' => 'Consumo Cósmico', 'tipo' => 'gatilho', 'gatilho' => 'qualquer_unidade_morre',
                    'efeito' => ['tipo' => 'crescimento_por_morte', 'bonus_ataque' => 1, 'bonus_vida' => 1, 'maximo' => 3]]],
            ],
        ];
    }
}
