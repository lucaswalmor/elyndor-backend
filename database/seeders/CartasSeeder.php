<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\CardSkill;
use App\Services\Game\CardCatalog;
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
                    'linhagem'    => $c['linhagem'],
                    'classe'      => $c['classe'] ?? null,
                    'raridade'    => $c['raridade'],
                    'tipo'        => $c['tipo'] ?? 'unit',
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

        CardCatalog::flush();
    }

    private function cards(): array
    {
        $cards = [
            // ── Infernais ─────────────────────────────────────────────────────
            [
                'nome' => 'Carniceiro de Brasas', 'slug' => 'carniceiro-de-brasas',
                'linhagem' => 'karuna', 'classe' => 'Demônio', 'raridade' => 'rara',
                'tipo' => 'unit', 'custo' => 5, 'ataque' => 4, 'vida' => 6,
                'imagem' => 'carniceiro-de-brasas', 'imagem_path' => 'karuna/carniceiro_de_brasas.png',
                'descricao' => 'Ao morrer, causa 2 de dano a todas as unidades inimigas em campo.',
                'habilidades' => [['nome' => 'Morte Explosiva', 'tipo' => 'gatilho', 'gatilho' => 'ao_morrer',
                    'efeito' => ['tipo' => 'dano_todas_inimigas', 'valor' => 2]]],
            ],
            [
                'nome' => 'Cão Vulcânico', 'slug' => 'cao-vulcanico',
                'linhagem' => 'karuna', 'classe' => 'Fera Infernal', 'raridade' => 'comum',
                'tipo' => 'unit', 'custo' => 2, 'ataque' => 3, 'vida' => 2,
                'imagem' => 'cao-vulcanico', 'imagem_path' => 'karuna/cao_vulcanico.png',
                'descricao' => 'Pode atacar no turno em que é invocado. Recebe +1 ATK neste turno.',
                'habilidades' => [['nome' => 'Fúria Inicial', 'tipo' => 'batalha_cry', 'gatilho' => 'ao_invocar',
                    'efeito' => ['tipo' => 'charge', 'bonus_ataque' => 1, 'pode_atacar_imediato' => true]]],
            ],
            [
                'nome' => 'Bruxa Cinzenta', 'slug' => 'bruxa-cinzenta',
                'linhagem' => 'karuna', 'classe' => 'Bruxa', 'raridade' => 'comum',
                'tipo' => 'unit', 'custo' => 3, 'ataque' => 2, 'vida' => 3,
                'imagem' => 'bruxa-cinzenta', 'imagem_path' => 'karuna/bruxa_cinzenta_card.png',
                'descricao' => 'Ao atacar, o alvo recebe -1 ATK por 2 turnos.',
                'habilidades' => [['nome' => 'Maldição Sombria', 'tipo' => 'gatilho', 'gatilho' => 'ao_atacar',
                    'efeito' => ['tipo' => 'debuff_ataque', 'valor' => 1, 'duracao' => 2]]],
            ],
            [
                'nome' => 'Titã Magmático', 'slug' => 'tita-magmatico',
                'linhagem' => 'karuna', 'classe' => 'Titã', 'raridade' => 'epica',
                'tipo' => 'unit', 'custo' => 7, 'ataque' => 6, 'vida' => 10,
                'imagem' => 'tita-magmatico', 'imagem_path' => 'karuna/tita_magmatico_card.png',
                'descricao' => 'Não pode ser alvo de habilidades de remoção direta (Silêncio, destruição instantânea).',
                'habilidades' => [['nome' => 'Corpo Colossal', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'imune_remocao_direta']]],
            ],
            [
                'nome' => 'Morcego Ígneo', 'slug' => 'morcego-igneo',
                'linhagem' => 'karuna', 'classe' => 'Fera Voadora', 'raridade' => 'comum',
                'tipo' => 'unit', 'custo' => 1, 'ataque' => 1, 'vida' => 1,
                'imagem' => 'morcego-igneo', 'imagem_path' => 'karuna/morcego_igneo_card.png',
                'descricao' => 'Pode atacar o jogador inimigo diretamente mesmo com unidades inimigas em campo.',
                'habilidades' => [['nome' => 'Ataque Direto', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'ataque_direto_jogador']]],
            ],
            [
                'nome' => 'Rei das Correntes', 'slug' => 'rei-das-correntes',
                'linhagem' => 'karuna', 'classe' => 'Senhor Infernal', 'raridade' => 'rara',
                'tipo' => 'unit', 'custo' => 6, 'ataque' => 5, 'vida' => 7,
                'imagem' => 'rei-das-correntes', 'imagem_path' => 'karuna/rei_das_correntes_card.png',
                'descricao' => 'Ativo (1 energia): força uma unidade inimiga a atacar o Rei das Correntes neste turno.',
                'habilidades' => [['nome' => 'Gancho Infernal', 'tipo' => 'ativa', 'gatilho' => null,
                    'efeito' => ['tipo' => 'forcar_ataque_a_si', 'custo_energia' => 1, 'alvo' => 'unidade_inimiga']]],
            ],

            // ── Natureza ──────────────────────────────────────────────────────
            [
                'nome' => 'Guardião do Musgo', 'slug' => 'guardiao-do-musgo',
                'linhagem' => 'ybyra', 'classe' => 'Guardião', 'raridade' => 'rara',
                'tipo' => 'unit', 'custo' => 5, 'ataque' => 2, 'vida' => 9,
                'imagem' => 'guardiao-do-musgo', 'imagem_path' => 'ybyra/guardiao_do_musgo_card_v2.png',
                'descricao' => 'Recebe -1 de dano de todos os ataques (mínimo 1).',
                'habilidades' => [['nome' => 'Casca Viva', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'reducao_dano', 'valor' => 1, 'minimo_dano' => 1]]],
            ],
            [
                'nome' => 'Aranha Lunar', 'slug' => 'aranha-lunar',
                'linhagem' => 'ybyra', 'classe' => 'Aracnídeo', 'raridade' => 'comum',
                'tipo' => 'unit', 'custo' => 3, 'ataque' => 2, 'vida' => 4,
                'imagem' => 'aranha-lunar', 'imagem_path' => 'ybyra/aranha_lunar_card.png',
                'descricao' => 'Ao atacar, o alvo não pode atacar no próximo turno.',
                'habilidades' => [['nome' => 'Teia Prisional', 'tipo' => 'gatilho', 'gatilho' => 'ao_atacar',
                    'efeito' => ['tipo' => 'nao_pode_atacar', 'duracao' => 1]]],
            ],
            [
                'nome' => 'Espírito da Raiz', 'slug' => 'espirito-da-raiz',
                'linhagem' => 'ybyra', 'classe' => 'Espírito', 'raridade' => 'comum',
                'tipo' => 'unit', 'custo' => 4, 'ataque' => 1, 'vida' => 5,
                'imagem' => 'espirito-da-raiz', 'imagem_path' => 'ybyra/espirito_da_raiz_card.png',
                'descricao' => 'No início de cada turno aliado, cura 2 HP de um aliado aleatório em campo.',
                'habilidades' => [['nome' => 'Cura Natural', 'tipo' => 'gatilho', 'gatilho' => 'inicio_turno_aliado',
                    'efeito' => ['tipo' => 'cura_aleatorio_aliado', 'valor' => 2]]],
            ],
            [
                'nome' => 'Sapo Tóxico', 'slug' => 'sapo-toxico',
                'linhagem' => 'ybyra', 'classe' => 'Anfíbio', 'raridade' => 'comum',
                'tipo' => 'unit', 'custo' => 2, 'ataque' => 2, 'vida' => 4,
                'imagem' => 'sapo-toxico', 'imagem_path' => 'ybyra/sapo_toxico_card.png',
                'descricao' => 'Ao atacar, aplica Veneno (1 dano por turno por 2 turnos).',
                'habilidades' => [['nome' => 'Veneno Persistente', 'tipo' => 'gatilho', 'gatilho' => 'ao_atacar',
                    'efeito' => ['tipo' => 'veneno', 'valor' => 1, 'duracao' => 2]]],
            ],
            [
                'nome' => 'Cervo Fantasma', 'slug' => 'cervo-fantasma',
                'linhagem' => 'ybyra', 'classe' => 'Espírito Animal', 'raridade' => 'comum',
                'tipo' => 'unit', 'custo' => 4, 'ataque' => 3, 'vida' => 3,
                'imagem' => 'cervo-fantasma', 'imagem_path' => 'ybyra/cervo_fantasma_card.png',
                'descricao' => 'Ignora o primeiro ataque ou habilidade recebida após ser invocado.',
                'habilidades' => [['nome' => 'Esquiva Etérea', 'tipo' => 'batalha_cry', 'gatilho' => 'ao_invocar',
                    'efeito' => ['tipo' => 'escudo_primeiro_golpe', 'cargas' => 1]]],
            ],
            [
                'nome' => 'Hidra do Pântano', 'slug' => 'hidra-do-pantano',
                'linhagem' => 'ybyra', 'classe' => 'Monstro', 'raridade' => 'rara',
                'tipo' => 'unit', 'custo' => 6, 'ataque' => 4, 'vida' => 8,
                'imagem' => 'hidra-do-pantano', 'imagem_path' => 'ybyra/hidra_do_pantano_card.png',
                'descricao' => 'Contra-ataca automaticamente uma vez por turno quando sobrevive a um ataque.',
                'habilidades' => [['nome' => 'Múltiplas Cabeças', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'contra_ataque_extra', 'limite_por_turno' => 1]]],
            ],

            // ── Mecânicos ─────────────────────────────────────────────────────
            [
                'nome' => 'Drone Sentinela', 'slug' => 'drone-sentinela',
                'linhagem' => 'ferroveu', 'classe' => 'Drone', 'raridade' => 'comum',
                'tipo' => 'unit', 'custo' => 2, 'ataque' => 2, 'vida' => 2,
                'imagem' => 'drone-sentinela', 'imagem_path' => 'ferroveu/drone_sentinela_card.png',
                'descricao' => 'Ao atacar, não recebe contra-ataque da unidade atacada.',
                'habilidades' => [['nome' => 'Tiro Longo', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'ataque_sem_retaliacao']]],
            ],
            [
                'nome' => 'Executor de Ferro', 'slug' => 'executor-de-ferro',
                'linhagem' => 'ferroveu', 'classe' => 'Máquina', 'raridade' => 'rara',
                'tipo' => 'unit', 'custo' => 5, 'ataque' => 5, 'vida' => 6,
                'imagem' => 'executor-de-ferro', 'imagem_path' => 'ferroveu/executor_de_ferro_card.png',
                'descricao' => 'Causa +2 dano adicional contra unidades com redução de dano.',
                'habilidades' => [['nome' => 'Quebra-Armadura', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'dano_bonus_vs_reducao', 'valor' => 2]]],
            ],
            [
                'nome' => 'Engenheira Tesla', 'slug' => 'engenheira-tesla',
                'linhagem' => 'ferroveu', 'classe' => 'Inventora', 'raridade' => 'rara',
                'tipo' => 'unit', 'custo' => 4, 'ataque' => 2, 'vida' => 4,
                'imagem' => 'engenheira-tesla', 'imagem_path' => 'ferroveu/engenheira_tesla_card.png',
                'descricao' => 'Enquanto estiver em campo, unidades Mecânicas aliadas recebem +1 ATK.',
                'habilidades' => [['nome' => 'Sobrecarga', 'tipo' => 'aura', 'gatilho' => null,
                    'efeito' => ['tipo' => 'aura_buff_ataque', 'valor' => 1, 'filtro_linhagem' => 'ferroveu']]],
            ],
            [
                'nome' => 'Aranha de Sucata', 'slug' => 'aranha-de-sucata',
                'linhagem' => 'ferroveu', 'classe' => 'Máquina', 'raridade' => 'comum',
                'tipo' => 'unit', 'custo' => 3, 'ataque' => 2, 'vida' => 5,
                'imagem' => 'aranha-de-sucata', 'imagem_path' => 'ferroveu/aranha_de_sucata_card.png',
                'descricao' => 'Uma vez por partida, ao chegar a 0 HP, renasce com 1 HP em campo.',
                'habilidades' => [['nome' => 'Reconstrução', 'tipo' => 'gatilho', 'gatilho' => 'ao_morrer',
                    'efeito' => ['tipo' => 'ressurreicao_unica', 'vida' => 1, 'limite_partida' => 1]]],
            ],
            [
                'nome' => 'Tremor MK-II', 'slug' => 'tremor-mk-ii',
                'linhagem' => 'ferroveu', 'classe' => 'Perfuração', 'raridade' => 'epica',
                'tipo' => 'unit', 'custo' => 6, 'ataque' => 5, 'vida' => 5,
                'imagem' => 'tremor-mk-ii', 'imagem_path' => 'ferroveu/tremor_mk_ii_card.png',
                'descricao' => 'Pode atacar no turno em que é invocado (Charge).',
                'habilidades' => [['nome' => 'Escavação', 'tipo' => 'batalha_cry', 'gatilho' => 'ao_invocar',
                    'efeito' => ['tipo' => 'charge', 'pode_atacar_imediato' => true]]],
            ],
            [
                'nome' => 'Núcleo Autômato', 'slug' => 'nucleo-automato',
                'linhagem' => 'ferroveu', 'classe' => 'Núcleo', 'raridade' => 'epica',
                'tipo' => 'unit', 'custo' => 5, 'ataque' => 1, 'vida' => 4,
                'imagem' => 'nucleo-automato', 'imagem_path' => 'ferroveu/nucleo_automato_card.png',
                'descricao' => 'No início de cada turno aliado, concede +1 energia temporária (não acumula).',
                'habilidades' => [['nome' => 'Gerador', 'tipo' => 'gatilho', 'gatilho' => 'inicio_turno_aliado',
                    'efeito' => ['tipo' => 'energia_temporaria', 'valor' => 1, 'acumula' => false]]],
            ],

            // ── Mortos-Vivos ──────────────────────────────────────────────────
            [
                'nome' => 'Cavaleiro Sem Face', 'slug' => 'cavaleiro-sem-face',
                'linhagem' => 'anhanga', 'classe' => 'Morto-vivo', 'raridade' => 'rara',
                'tipo' => 'unit', 'custo' => 4, 'ataque' => 4, 'vida' => 5,
                'imagem' => 'cavaleiro-sem-face', 'imagem_path' => 'anhanga/cavaleiro_sem_face_card.png',
                'descricao' => 'Imune a Silêncio, Teia Prisional e Confusão.',
                'habilidades' => [['nome' => 'Sem Medo', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'imune_controle', 'efeitos' => ['silencio', 'nao_pode_atacar', 'confusao']]]],
            ],
            [
                'nome' => 'Costureira Macabra', 'slug' => 'costureira-macabra',
                'linhagem' => 'anhanga', 'classe' => 'Necromante', 'raridade' => 'rara',
                'tipo' => 'unit', 'custo' => 5, 'ataque' => 3, 'vida' => 5,
                'imagem' => 'costureira-macabra', 'imagem_path' => 'anhanga/costureira_macabra_card.png',
                'descricao' => 'Ao atacar, recupera HP igual ao dano causado (máximo 3 por ataque).',
                'habilidades' => [['nome' => 'Roubo Vital', 'tipo' => 'gatilho', 'gatilho' => 'ao_atacar',
                    'efeito' => ['tipo' => 'cura_por_dano', 'maximo' => 3]]],
            ],
            [
                'nome' => 'Corvo Funerário', 'slug' => 'corvo-funerario',
                'linhagem' => 'anhanga', 'classe' => 'Ave Sombria', 'raridade' => 'comum',
                'tipo' => 'unit', 'custo' => 1, 'ataque' => 1, 'vida' => 1,
                'imagem' => 'corvo-funerario', 'imagem_path' => 'anhanga/corvo_funerario_card.png',
                'descricao' => 'Ao ser invocado, revela a próxima carta do deck inimigo.',
                'habilidades' => [['nome' => 'Observador', 'tipo' => 'batalha_cry', 'gatilho' => 'ao_invocar',
                    'efeito' => ['tipo' => 'revelar_proxima_carta_deck', 'quantidade' => 1, 'alvo' => 'deck_inimigo']]],
            ],
            [
                'nome' => 'Monge Apodrecido', 'slug' => 'monge-apodrecido',
                'linhagem' => 'anhanga', 'classe' => 'Monge', 'raridade' => 'comum',
                'tipo' => 'unit', 'custo' => 4, 'ataque' => 2, 'vida' => 6,
                'imagem' => 'monge-apodrecido', 'imagem_path' => 'anhanga/monge_apodrecido_card.png',
                'descricao' => 'Ao atacar, aplica Silêncio por 1 turno. Não funciona contra Sem Medo.',
                'habilidades' => [['nome' => 'Silêncio', 'tipo' => 'gatilho', 'gatilho' => 'ao_atacar',
                    'efeito' => ['tipo' => 'silencio', 'duracao' => 1, 'respeita_imune_controle' => true]]],
            ],
            [
                'nome' => 'Gigante Ossuário', 'slug' => 'gigante-ossuario',
                'linhagem' => 'anhanga', 'classe' => 'Colosso', 'raridade' => 'epica',
                'tipo' => 'unit', 'custo' => 7, 'ataque' => 5, 'vida' => 10,
                'imagem' => 'gigante-ossuario', 'imagem_path' => 'anhanga/gigante_ossuario_card.png',
                'descricao' => 'Ganha +1 redução de dano permanente a cada ataque recebido (máximo +3).',
                'habilidades' => [['nome' => 'Ossos Reforçados', 'tipo' => 'passiva', 'gatilho' => null,
                    'efeito' => ['tipo' => 'reducao_dano_acumulativa', 'valor_por_golpe' => 1, 'maximo' => 3]]],
            ],
            [
                'nome' => 'Criança do Véu', 'slug' => 'crianca-do-veu',
                'linhagem' => 'anhanga', 'classe' => 'Fantasma', 'raridade' => 'comum',
                'tipo' => 'unit', 'custo' => 3, 'ataque' => 1, 'vida' => 4,
                'imagem' => 'crianca-do-veu', 'imagem_path' => 'anhanga/crianca_do_veu_card.png',
                'descricao' => 'Ao atacar, o alvo tem 50% de chance de errar o próximo ataque.',
                'habilidades' => [['nome' => 'Confusão', 'tipo' => 'gatilho', 'gatilho' => 'ao_atacar',
                    'efeito' => ['tipo' => 'confusao', 'chance' => 50, 'duracao' => 1, 'respeita_imune_controle' => true]]],
            ],

            // ── Void / Celestiais ─────────────────────────────────────────────
            [
                'nome' => 'Oráculo Solar', 'slug' => 'oraculo-solar',
                'linhagem' => 'orun', 'classe' => 'Celestial', 'raridade' => 'comum',
                'tipo' => 'unit', 'custo' => 4, 'ataque' => 2, 'vida' => 4,
                'imagem' => 'oraculo-solar', 'imagem_path' => 'orun/oraculo_solar_card.png',
                'descricao' => 'Ativo (1 energia): revela as 3 próximas cartas do deck inimigo.',
                'habilidades' => [['nome' => 'Profecia', 'tipo' => 'ativa', 'gatilho' => null,
                    'efeito' => ['tipo' => 'revelar_proxima_carta_deck', 'quantidade' => 3, 'alvo' => 'deck_inimigo', 'custo_energia' => 1]]],
            ],
            [
                'nome' => 'Aberração do Vazio', 'slug' => 'aberracao-do-vazio',
                'linhagem' => 'orun', 'classe' => 'Void', 'raridade' => 'rara',
                'tipo' => 'unit', 'custo' => 5, 'ataque' => 4, 'vida' => 5,
                'imagem' => 'aberracao-do-vazio', 'imagem_path' => 'orun/aberracao_do_vazio_card_fixed.png',
                'descricao' => 'Ao ser invocado, destrói uma unidade inimiga aleatória (sem disparar ao morrer).',
                'habilidades' => [['nome' => 'Dobra Espacial', 'tipo' => 'batalha_cry', 'gatilho' => 'ao_invocar',
                    'efeito' => ['tipo' => 'destruir_aleatorio_inimigo', 'dispara_ao_morrer' => false]]],
            ],
            [
                'nome' => 'Serafim Partido', 'slug' => 'serafim-partido',
                'linhagem' => 'orun', 'classe' => 'Anjo', 'raridade' => 'epica',
                'tipo' => 'unit', 'custo' => 6, 'ataque' => 3, 'vida' => 7,
                'imagem' => 'serafim-partido', 'imagem_path' => 'orun/serafim_partido_card.png',
                'descricao' => 'Ativo (2 energia): revive a última unidade aliada destruída com metade do HP máximo.',
                'habilidades' => [['nome' => 'Renascimento', 'tipo' => 'ativa', 'gatilho' => null,
                    'efeito' => ['tipo' => 'reviver_ultimo_aliado', 'hp_percentual' => 50, 'custo_energia' => 2]]],
            ],
            [
                'nome' => 'Eclipse Vivo', 'slug' => 'eclipse-vivo',
                'linhagem' => 'orun', 'classe' => 'Entidade', 'raridade' => 'rara',
                'tipo' => 'unit', 'custo' => 5, 'ataque' => 2, 'vida' => 8,
                'imagem' => 'eclipse-vivo', 'imagem_path' => 'orun/eclipse_vivo_card.png',
                'descricao' => 'Enquanto estiver em campo, todas as unidades inimigas têm -1 ATK.',
                'habilidades' => [['nome' => 'Escuridão Total', 'tipo' => 'aura', 'gatilho' => null,
                    'efeito' => ['tipo' => 'aura_debuff_ataque', 'valor' => 1, 'alvo' => 'inimigos']]],
            ],
            [
                'nome' => 'Navegante Astral', 'slug' => 'navegante-astral',
                'linhagem' => 'orun', 'classe' => 'Mago Astral', 'raridade' => 'comum',
                'tipo' => 'unit', 'custo' => 4, 'ataque' => 3, 'vida' => 4,
                'imagem' => 'navegante-astral', 'imagem_path' => 'orun/navegante_astral_card.png',
                'descricao' => 'Ao ser invocado, retorna um aliado em campo para a mão (ou cemitério se mão cheia).',
                'habilidades' => [['nome' => 'Troca Dimensional', 'tipo' => 'batalha_cry', 'gatilho' => 'ao_invocar',
                    'efeito' => ['tipo' => 'retornar_aliado_mao', 'selecao' => 'aliado_campo', 'se_mao_cheia' => 'cemiterio']]],
            ],
            [
                'nome' => 'Devorador de Estrelas', 'slug' => 'devorador-de-estrelas',
                'linhagem' => 'orun', 'classe' => 'Entidade Cósmica', 'raridade' => 'lendaria',
                'tipo' => 'unit', 'custo' => 8, 'ataque' => 6, 'vida' => 8,
                'imagem' => 'devorador-de-estrelas', 'imagem_path' => 'orun/devorador_de_estrelas_card_fixed.png',
                'descricao' => 'Quando qualquer unidade morre, ganha +1 ATK e +1 HP permanentes (máximo +3/+3).',
                'habilidades' => [['nome' => 'Consumo Cósmico', 'tipo' => 'gatilho', 'gatilho' => 'qualquer_unidade_morre',
                    'efeito' => ['tipo' => 'crescimento_por_morte', 'bonus_ataque' => 1, 'bonus_vida' => 1, 'maximo' => 3]]],
            ],
        ];

        return array_merge($cards, $this->cardsV21());
    }

    private function cardsV21(): array
    {
        return [
            ...$this->karunaV21(),
            ...$this->ybyraV21(),
            ...$this->ferroveuV21(),
            ...$this->anhangaV21(),
            ...$this->orunV21(),
            ...$this->spellsV21(),
        ];
    }

    private function unit(
        string $nome,
        string $slug,
        string $linhagem,
        string $classe,
        string $raridade,
        int $custo,
        int $ataque,
        int $vida,
        string $imagemPath,
        string $descricao,
        array $habilidades = [],
    ): array {
        return [
            'nome' => $nome,
            'slug' => $slug,
            'linhagem' => $linhagem,
            'classe' => $classe,
            'raridade' => $raridade,
            'tipo' => 'unit',
            'custo' => $custo,
            'ataque' => $ataque,
            'vida' => $vida,
            'imagem' => $slug,
            'imagem_path' => $imagemPath,
            'descricao' => $descricao,
            'habilidades' => $habilidades,
        ];
    }

    private function spell(
        string $nome,
        string $slug,
        string $subtipo,
        string $raridade,
        int $custo,
        string $imagemPath,
        string $descricao,
        array $efeito,
    ): array {
        return [
            'nome' => $nome,
            'slug' => $slug,
            'linhagem' => 'neutra',
            'classe' => $subtipo,
            'raridade' => $raridade,
            'tipo' => 'spell',
            'custo' => $custo,
            'ataque' => 0,
            'vida' => 0,
            'imagem' => $slug,
            'imagem_path' => $imagemPath,
            'descricao' => $descricao,
            'habilidades' => [[
                'nome' => $nome,
                'tipo' => 'spell',
                'gatilho' => null,
                'efeito' => $efeito,
            ]],
        ];
    }

    private function skill(string $nome, string $tipo, ?string $gatilho, array $efeito): array
    {
        return compact('nome', 'tipo', 'gatilho', 'efeito');
    }

    private function karunaV21(): array
    {
        return [
            $this->unit('Cinzeiro Rastejante', 'cinzeiro-rastejante', 'karuna', 'Assassin', 'comum', 1, 1, 2, 'karuna/cinzeiro_rastejante.png', 'Ao morrer, causa 1 de dano diretamente ao jogador inimigo.', [
                $this->skill('Rastro de Brasa', 'gatilho', 'ao_morrer', ['tipo' => 'dano_jogador_inimigo', 'valor' => 1]),
            ]),
            $this->unit('Salamandra de Chamas', 'salamandra-de-chamas', 'karuna', 'DPS', 'comum', 2, 2, 3, 'karuna/salamandra_de_chamas.png', 'Quando recebe dano, causa 1 de dano de volta ao atacante.', [
                $this->skill('Pele Incandescente', 'passiva', null, ['tipo' => 'reflexo_dano', 'valor' => 1]),
            ]),
            $this->unit('Berserker das Brasas', 'berserker-das-brasas', 'karuna', 'Assassin', 'comum', 2, 3, 1, 'karuna/berseker_das_brasas.png', 'Ao matar uma unidade inimiga, ganha +1 ATK.', [
                $this->skill('Sede de Sangue', 'gatilho', 'ao_matar', ['tipo' => 'ganho_ataque_ao_matar', 'valor' => 1]),
            ]),
            $this->unit('Cultista do Nexus', 'cultista-do-nexus', 'karuna', 'Support', 'comum', 2, 1, 3, 'karuna/cultista_do_nexus.png', 'Ativo (1 energia): sacrifica 2 HP para dar +2 ATK a um aliado neste turno.', [
                $this->skill('Oferenda de Sangue', 'ativa', null, ['tipo' => 'sacrificio_buff_aliado_turno', 'custo_energia' => 1, 'custo_vida' => 2, 'valor' => 2, 'alvo' => 'unidade_aliada']),
            ]),
            $this->unit('Lança-Chamas Infernal', 'lanca-chamas-infernal', 'karuna', 'DPS', 'comum', 3, 3, 2, 'karuna/lanca_chamas_infernal.png', 'Ao atacar, causa 1 de dano adicional à unidade inimiga ao lado.', [
                $this->skill('Cone de Fogo', 'gatilho', 'ao_atacar', ['tipo' => 'dano_adjacente', 'valor' => 1]),
            ]),
            $this->unit('Guardião de Lava', 'guardiao-de-lava', 'karuna', 'Tank', 'comum', 3, 1, 6, 'karuna/guardiao_de_lava.png', 'Provocar.', [
                $this->skill('Provocar', 'passiva', null, ['tipo' => 'provocar']),
            ]),
            $this->unit('Feiticeira do Vulcão', 'feiticeira-do-vulcao', 'karuna', 'Support', 'comum', 3, 2, 3, 'karuna/feiticeira_do_vulcao.png', 'Ao ser invocada, aplica Silêncio em uma unidade inimiga por 1 turno.', [
                $this->skill('Chama Enfraquecedora', 'batalha_cry', 'ao_invocar', ['tipo' => 'silencio', 'duracao' => 1, 'alvo' => 'unidade_inimiga']),
            ]),
            $this->unit('Espectro Ígneo', 'espectro-igneo', 'karuna', 'Healer', 'comum', 4, 1, 4, 'karuna/espectro_igneo.png', 'No início de cada turno aliado, cura 2 HP da unidade aliada com menos HP.', [
                $this->skill('Chama Curativa', 'gatilho', 'inicio_turno_aliado', ['tipo' => 'cura_aliado_menor_hp', 'valor' => 2]),
            ]),
            $this->unit('Colosso de Escória', 'colosso-da-escoria', 'karuna', 'Tank', 'rara', 4, 2, 8, 'karuna/colosso_da_escoria.png', 'Recebe -1 de dano por ataque. Ao morrer, causa 1 de dano a todas as unidades inimigas.', [
                $this->skill('Corpo de Magma', 'passiva', null, ['tipo' => 'reducao_dano', 'valor' => 1, 'minimo_dano' => 1]),
                $this->skill('Estilhaços de Escória', 'gatilho', 'ao_morrer', ['tipo' => 'dano_todas_inimigas', 'valor' => 1]),
            ]),
            $this->unit('Drakhar Sombrio', 'drakhar-sombrio', 'karuna', 'DPS', 'rara', 4, 4, 3, 'karuna/drakhar_sombrio.png', 'Fúria Inicial. Ao matar uma unidade, pode atacar uma segunda vez neste turno.', [
                $this->skill('Fúria Inicial', 'batalha_cry', 'ao_invocar', ['tipo' => 'charge', 'pode_atacar_imediato' => true]),
                $this->skill('Investida Dupla', 'gatilho', 'ao_matar', ['tipo' => 'ataque_extra_ao_matar', 'limite_por_turno' => 1]),
            ]),
            $this->unit('Sacerdotisa da Chama', 'sacerdotisa-da-chama', 'karuna', 'Healer', 'rara', 5, 2, 5, 'karuna/sacerdotista_da_chama.png', 'Ativo (2 energia): aplica Véu Arcano em 1 aliado.', [
                $this->skill('Ritual de Fogo', 'ativa', null, ['tipo' => 'veu_arcano', 'custo_energia' => 2, 'cargas' => 1, 'alvo' => 'unidade_aliada']),
            ]),
            $this->unit('Comandante Vulcânico', 'comandante-vulcanico', 'karuna', 'Support', 'rara', 5, 3, 6, 'karuna/comandante_vulcanico.png', 'Ao ser invocado, aliados Ka\'runa ganham +1 ATK neste turno.', [
                $this->skill('Grito de Guerra', 'batalha_cry', 'ao_invocar', ['tipo' => 'buff_linhagem_ataque_turno', 'linhagem' => 'karuna', 'valor' => 1]),
            ]),
            $this->unit('Drakhar Ancião', 'drakhar-anciao', 'karuna', 'DPS', 'rara', 6, 3, 7, 'karuna/drakhar_anciao.png', 'Ataque Direto.', [
                $this->skill('Voo Rasante', 'passiva', null, ['tipo' => 'ataque_direto_jogador']),
            ]),
            $this->unit('Senhor do Nexus Vulcânico', 'senhor-do-nexus-vulcanico', 'karuna', 'Tank', 'lendaria', 8, 5, 9, 'karuna/senhor_do_nexus_vulcanico.png', 'Ao ser invocado, causa dano a todas as unidades inimigas igual ao número de unidades inimigas em campo.', [
                $this->skill('Erupção do Nexus', 'batalha_cry', 'ao_invocar', ['tipo' => 'dano_por_unidades_inimigas', 'maximo' => 5]),
            ]),
        ];
    }

    private function ybyraV21(): array
    {
        return [
            $this->unit('Besouro Espinhoso', 'besouro-espinhoso', 'ybyra', 'Support', 'comum', 1, 1, 2, 'ybyra/besouro_espinhoso.png', 'Quando atacado, causa 1 de dano ao atacante além do contra-ataque normal.', [
                $this->skill('Espinhos', 'passiva', null, ['tipo' => 'reflexo_dano', 'valor' => 1]),
            ]),
            $this->unit('Víbora da Raiz', 'vibora-da-raiz', 'ybyra', 'Assassin', 'comum', 2, 2, 2, 'ybyra/vibora_da_raiz.png', 'Fúria Inicial. Ao atacar, aplica Veneno no alvo.', [
                $this->skill('Fúria Inicial', 'batalha_cry', 'ao_invocar', ['tipo' => 'charge', 'pode_atacar_imediato' => true]),
                $this->skill('Veneno Fulminante', 'gatilho', 'ao_atacar', ['tipo' => 'veneno', 'valor' => 1, 'duracao' => 2]),
            ]),
            $this->unit('Fungo Devorador', 'fungo-devorador', 'ybyra', 'Tank', 'comum', 2, 1, 4, 'ybyra/fungo_devorador.png', 'Ao morrer, aplica Veneno em todas as unidades inimigas.', [
                $this->skill('Esporos Tóxicos', 'gatilho', 'ao_morrer', ['tipo' => 'veneno_todas_inimigas', 'valor' => 1, 'duracao' => 2]),
            ]),
            $this->unit('Druida da Raiz', 'druida-da-raiz', 'ybyra', 'Healer', 'comum', 3, 1, 4, 'ybyra/druida_da_raiz.png', 'No início de cada turno aliado, cura 1 HP de todos os aliados.', [
                $this->skill('Regeneração Silvestre', 'gatilho', 'inicio_turno_aliado', ['tipo' => 'cura_todos_aliados', 'valor' => 1]),
            ]),
            $this->unit('Pantera Sombria', 'pantera-sombria', 'ybyra', 'Assassin', 'comum', 3, 4, 2, 'ybyra/pantera_sombria.png', 'Ao atacar uma unidade com HP cheio, causa +2 de dano adicional.', [
                $this->skill('Bote Silencioso', 'passiva', null, ['tipo' => 'dano_bonus_alvo_ileso', 'valor' => 2]),
            ]),
            $this->unit('Lagarto de Cristal', 'lagarto-de-cristal', 'ybyra', 'Tank', 'comum', 3, 2, 5, 'ybyra/lagarto_de_cristal.png', 'Ignora a primeira habilidade inimiga recebida.', [
                $this->skill('Camuflagem', 'batalha_cry', 'ao_invocar', ['tipo' => 'veu_arcano', 'cargas' => 1]),
            ]),
            $this->unit('Xamã do Pântano', 'xama-do-pantano', 'ybyra', 'Support', 'comum', 4, 2, 4, 'ybyra/xama_do_pantano.png', 'Ativo (1 energia): aplica Silêncio e Veneno em uma unidade inimiga.', [
                $this->skill('Maldição Vegetal', 'ativa', null, ['tipo' => 'silencio_veneno', 'custo_energia' => 1, 'alvo' => 'unidade_inimiga', 'duracao' => 1, 'valor' => 1]),
            ]),
            $this->unit('Mantis Caçador', 'mantis-cacador', 'ybyra', 'DPS', 'comum', 4, 3, 3, 'ybyra/mantis_cacador.png', 'Ao matar uma unidade inimiga, pode atacar novamente imediatamente.', [
                $this->skill('Corte Veloz', 'gatilho', 'ao_matar', ['tipo' => 'ataque_extra_ao_matar', 'limite_por_turno' => 1]),
            ]),
            $this->unit('Urso Ancestral', 'urso-ancestral', 'ybyra', 'Tank', 'rara', 5, 3, 8, 'ybyra/urso_ancestral.png', 'Provocar. No início do turno aliado, recupera 1 HP.', [
                $this->skill('Provocar', 'passiva', null, ['tipo' => 'provocar']),
                $this->skill('Vitalidade', 'gatilho', 'inicio_turno_aliado', ['tipo' => 'cura_si', 'valor' => 1]),
            ]),
            $this->unit('Cobra-Rainha', 'cobra-rainha', 'ybyra', 'DPS', 'rara', 5, 4, 5, 'ybyra/cobra_rainha.png', 'Ao atacar, aplica Veneno aprimorado.', [
                $this->skill('Veneno Necrótico', 'gatilho', 'ao_atacar', ['tipo' => 'veneno', 'valor' => 2, 'duracao' => 2, 'substitui' => true]),
            ]),
            $this->unit('Ancião da Floresta', 'anciao-da-floresta', 'ybyra', 'Healer', 'rara', 5, 1, 6, 'ybyra/anciao_da_floresta.png', 'Ativo (2 energia): cura 3 HP de um aliado. Se estiver cheio, cura o jogador aliado.', [
                $this->skill('Cura Profunda', 'ativa', null, ['tipo' => 'cura_aliado_ou_jogador', 'custo_energia' => 2, 'valor' => 3, 'alvo' => 'unidade_aliada']),
            ]),
            $this->unit('Predador do Dossel', 'predador-do-dossel', 'ybyra', 'DPS', 'rara', 5, 5, 4, 'ybyra/predador_do_dossel.png', 'Se invocado com o campo inimigo vazio, ganha +2 ATK e Fúria Inicial neste turno.', [
                $this->skill('Emboscada', 'batalha_cry', 'ao_invocar', ['tipo' => 'emboscada_campo_vazio', 'bonus_ataque' => 2]),
            ]),
            $this->unit('Gorila de Cipós', 'gorila-de-cipos', 'ybyra', 'Tank', 'rara', 6, 2, 10, 'ybyra/gorila_de_cipos.png', 'Ao receber dano, aplica Paralisia no atacante por 1 turno.', [
                $this->skill('Raízes Vivas', 'passiva', null, ['tipo' => 'paralisar_atacante_ao_receber_dano', 'duracao' => 1]),
            ]),
            $this->unit('Mãe das Raízes Eternas', 'mae-das-raizes-eternas', 'ybyra', 'Healer', 'lendaria', 8, 2, 10, 'ybyra/mae_das_raizes_eternas.png', 'No início do turno aliado, revive a unidade aliada morta mais recentemente com 2 HP, uma vez por partida.', [
                $this->skill('Floresta Viva', 'gatilho', 'inicio_turno_aliado', ['tipo' => 'reviver_ultimo_aliado', 'vida_fixa' => 2, 'limite_partida' => 1]),
            ]),
        ];
    }

    private function ferroveuV21(): array
    {
        return [
            $this->unit('Parafuso Ambulante', 'parafuso-ambulante', 'ferroveu', 'Support', 'comum', 1, 1, 2, 'ferroveu/parafuso_ambulante.png', 'Ao morrer, concede +1 HP a um aliado Ferrovéu aleatório em campo.', [
                $this->skill('Peça Sobressalente', 'gatilho', 'ao_morrer', ['tipo' => 'buff_hp_aliado_linhagem_aleatorio', 'linhagem' => 'ferroveu', 'valor' => 1]),
            ]),
            $this->unit('Atirador Enferrujado', 'atirador-enferrujado', 'ferroveu', 'DPS', 'comum', 2, 2, 2, 'ferroveu/atirador_enferrujado.png', 'Ao atacar, tem 50% de chance de causar +2 de dano. Se falhar, recebe 1 de dano.', [
                $this->skill('Tiro de Precisão', 'passiva', null, ['tipo' => 'chance_dano_bonus_ou_autodano', 'chance' => 50, 'bonus' => 2, 'autodano' => 1]),
            ]),
            $this->unit('Bomba Andante', 'bomba-andante', 'ferroveu', 'Assassin', 'comum', 2, 1, 3, 'ferroveu/bomba_andante.png', 'Ao morrer, causa 1 de dano diretamente ao jogador inimigo.', [
                $this->skill('Detonação', 'gatilho', 'ao_morrer', ['tipo' => 'dano_jogador_inimigo', 'valor' => 1]),
            ]),
            $this->unit('Escudo Autômato', 'escudo-automato', 'ferroveu', 'Tank', 'comum', 3, 1, 6, 'ferroveu/escudo_automato.png', 'Ao ser invocado, aplica Véu Arcano em uma carta aliada aleatória.', [
                $this->skill('Barreira Protetora', 'batalha_cry', 'ao_invocar', ['tipo' => 'veu_arcano_aliado_aleatorio', 'cargas' => 1]),
            ]),
            $this->unit('Técnico de Campo', 'tecnico-de-campo', 'ferroveu', 'Healer', 'comum', 3, 1, 4, 'ferroveu/tecnico_de_campo.png', 'Ativo (1 energia): restaura 2 HP de uma unidade aliada.', [
                $this->skill('Reparo Rápido', 'ativa', null, ['tipo' => 'cura_alvo', 'custo_energia' => 1, 'valor' => 2, 'alvo' => 'unidade_aliada']),
            ]),
            $this->unit('Canhão Portátil', 'canhao-portatil', 'ferroveu', 'DPS', 'comum', 3, 4, 2, 'ferroveu/canhao_portatil.png', 'Fúria Inicial. Ao atacar, perde 1 HP após o combate.', [
                $this->skill('Fúria Inicial', 'batalha_cry', 'ao_invocar', ['tipo' => 'charge', 'pode_atacar_imediato' => true]),
                $this->skill('Sobrecarga Balística', 'gatilho', 'ao_atacar', ['tipo' => 'autodano_pos_ataque', 'valor' => 1]),
            ]),
            $this->unit('Vigia Mecânico', 'vigia-mecanico', 'ferroveu', 'Support', 'comum', 4, 2, 5, 'ferroveu/vigia_mecanico.png', 'Ao ser invocado, revela as 2 próximas cartas do deck inimigo.', [
                $this->skill('Sinal de Alerta', 'batalha_cry', 'ao_invocar', ['tipo' => 'revelar_proxima_carta_deck', 'quantidade' => 2, 'alvo' => 'deck_inimigo']),
            ]),
            $this->unit('Golem de Aço', 'golem-de-aco', 'ferroveu', 'Tank', 'comum', 4, 2, 8, 'ferroveu/golem_de_aco.png', 'Provocar. Recebe -1 de dano por ataque.', [
                $this->skill('Provocar', 'passiva', null, ['tipo' => 'provocar']),
                $this->skill('Blindagem Total', 'passiva', null, ['tipo' => 'reducao_dano', 'valor' => 1, 'minimo_dano' => 1]),
            ]),
            $this->unit('Nano-Lâmina', 'nano-lamina', 'ferroveu', 'Assassin', 'rara', 3, 2, 1, 'ferroveu/nano_lamina.png', 'Tiro Longo e Ataque Direto.', [
                $this->skill('Tiro Longo', 'passiva', null, ['tipo' => 'ataque_sem_retaliacao']),
                $this->skill('Ataque Direto', 'passiva', null, ['tipo' => 'ataque_direto_jogador']),
            ]),
            $this->unit('Engenheiro Chefe', 'engenheiro-chefe', 'ferroveu', 'Support', 'rara', 5, 2, 5, 'ferroveu/engenheiro_chefe.png', 'Ativo (1 energia): reconstrói uma unidade Ferrovéu destruída com metade do HP máximo.', [
                $this->skill('Linha de Montagem', 'ativa', null, ['tipo' => 'reviver_ultimo_aliado_linhagem', 'linhagem' => 'ferroveu', 'hp_percentual' => 50, 'custo_energia' => 1]),
            ]),
            $this->unit('Tanque de Assalto', 'tanque-de-assalto', 'ferroveu', 'Tank', 'rara', 5, 4, 7, 'ferroveu/tanque_de_assalto.png', 'Provocar. Dano recebido de uma fonte única é limitado a 3.', [
                $this->skill('Provocar', 'passiva', null, ['tipo' => 'provocar']),
                $this->skill('Escudo de Impacto', 'passiva', null, ['tipo' => 'limite_dano_recebido', 'valor' => 3]),
            ]),
            $this->unit('Artilharia Elétrica', 'artilharia-eletrica', 'ferroveu', 'DPS', 'rara', 5, 3, 5, 'ferroveu/artilharia_eletrica.png', 'Ao atacar e matar uma unidade, o excesso de dano é transferido para outra unidade inimiga aleatória.', [
                $this->skill('Descarga em Cadeia', 'gatilho', 'ao_matar', ['tipo' => 'transferir_excesso_dano', 'alvo' => 'inimigo_aleatorio']),
            ]),
            $this->unit('Médico de Combate MK-I', 'medico-de-combate-mk1', 'ferroveu', 'Healer', 'rara', 6, 1, 6, 'ferroveu/medico_de_combate_mk1.png', 'No início de cada turno aliado, restaura 2 HP de todos os aliados em campo.', [
                $this->skill('Unidade de Suporte', 'gatilho', 'inicio_turno_aliado', ['tipo' => 'cura_todos_aliados', 'valor' => 2]),
            ]),
            $this->unit('Protocolo Omega', 'protocolo-omega', 'ferroveu', 'Support', 'lendaria', 8, 4, 8, 'ferroveu/protocolo_omega.png', 'Ao ser invocado, copia uma habilidade passiva inimiga e concede +1 energia temporária por turno.', [
                $this->skill('Cópia de Sistema', 'batalha_cry', 'ao_invocar', ['tipo' => 'copiar_passiva_maior_ataque_inimigo']),
                $this->skill('Gerador Omega', 'gatilho', 'inicio_turno_aliado', ['tipo' => 'energia_temporaria', 'valor' => 1, 'acumula' => false]),
            ]),
        ];
    }

    private function anhangaV21(): array
    {
        return [
            $this->unit('Espectro Sussurrante', 'espectro-sussurrante', 'anhanga', 'Assassin', 'comum', 1, 1, 1, 'anhanga/espectro_sussurante.png', 'Ataque Direto. Ao atacar o jogador, aplica Silêncio em uma unidade inimiga aleatória.', [
                $this->skill('Ataque Direto', 'passiva', null, ['tipo' => 'ataque_direto_jogador']),
                $this->skill('Toque Etéreo', 'gatilho', 'ao_atacar_jogador', ['tipo' => 'silencio_inimigo_aleatorio', 'duracao' => 1]),
            ]),
            $this->unit('Lacaio Podre', 'lacaio-podre', 'anhanga', 'DPS', 'comum', 2, 2, 3, 'anhanga/lacaio_podre.png', 'Ao atacar, aplica -1 ATK no alvo por 1 turno.', [
                $this->skill('Golpe Enfraquecedor', 'gatilho', 'ao_atacar', ['tipo' => 'debuff_ataque', 'valor' => 1, 'duracao' => 1]),
            ]),
            $this->unit('Banshee Lamentosa', 'banshee-lamentosa', 'anhanga', 'Support', 'comum', 2, 1, 3, 'anhanga/bunshee_lamentosa.png', 'Ao ser invocada, aplica Paralisia em uma unidade inimiga aleatória.', [
                $this->skill('Lamento', 'batalha_cry', 'ao_invocar', ['tipo' => 'paralisia_inimigo_aleatorio', 'duracao' => 1]),
            ]),
            $this->unit('Zumbi Colossus', 'zumbi-colossus', 'anhanga', 'Tank', 'comum', 3, 2, 7, 'anhanga/zumbi_colossus.png', 'Provocar. Ao receber dano, perde 1 ATK.', [
                $this->skill('Provocar', 'passiva', null, ['tipo' => 'provocar']),
                $this->skill('Decomposição', 'passiva', null, ['tipo' => 'perde_ataque_ao_receber_dano', 'valor' => 1]),
            ]),
            $this->unit('Necromante Aprendiz', 'necromante-aprendiz', 'anhanga', 'Healer', 'comum', 3, 1, 4, 'anhanga/necromante_aprendiz.png', 'Ao atacar, rouba 1 HP do alvo e transfere para um aliado aleatório.', [
                $this->skill('Drenar Vida', 'gatilho', 'ao_atacar', ['tipo' => 'drenar_para_aliado_aleatorio', 'valor' => 1]),
            ]),
            $this->unit('Verme das Tumbas', 'verme-das-tumbas', 'anhanga', 'DPS', 'comum', 3, 3, 2, 'anhanga/verme_das_tumbas.png', 'Ao matar uma unidade inimiga, ganha +1 HP permanente.', [
                $this->skill('Devorar', 'gatilho', 'ao_matar', ['tipo' => 'ganho_hp_ao_matar', 'valor' => 1]),
            ]),
            $this->unit('Sombra Vinculada', 'sombra-vinculada', 'anhanga', 'Support', 'comum', 4, 2, 5, 'anhanga/sombra_vinculada.png', 'Ativo (1 energia): aplica Silêncio e Paralisia em uma unidade inimiga por 1 turno.', [
                $this->skill('Maldição Vinculada', 'ativa', null, ['tipo' => 'silencio_paralisia', 'custo_energia' => 1, 'alvo' => 'unidade_inimiga', 'duracao' => 1]),
            ]),
            $this->unit('Espectro Guardião', 'espectro-guardiao', 'anhanga', 'Tank', 'comum', 4, 2, 6, 'anhanga/espectro_guardiao.png', 'Ao ser invocado, ganha Véu Arcano.', [
                $this->skill('Véu da Morte', 'batalha_cry', 'ao_invocar', ['tipo' => 'veu_arcano', 'cargas' => 1]),
            ]),
            $this->unit('Ceifador das Almas', 'ceifador-das-almas', 'anhanga', 'DPS', 'rara', 5, 5, 4, 'anhanga/ceifador_das_almas.png', 'Ao matar uma unidade inimiga, recupera 2 HP e ganha +1 ATK neste turno.', [
                $this->skill('Colheita', 'gatilho', 'ao_matar', ['tipo' => 'cura_si_e_bonus_ataque_turno', 'cura' => 2, 'bonus_ataque' => 1]),
            ]),
            $this->unit('Arquilich Menor', 'arquilich-menor', 'anhanga', 'Support', 'rara', 5, 3, 6, 'anhanga/arquilich_menor.png', 'Todas as unidades inimigas em campo têm -1 ATK enquanto este estiver em campo.', [
                $this->skill('Aura de Decomposição', 'aura', null, ['tipo' => 'aura_debuff_ataque', 'valor' => 1, 'alvo' => 'inimigos']),
            ]),
            $this->unit('Revenant Blindado', 'revenant-blindado', 'anhanga', 'Tank', 'rara', 5, 4, 7, 'anhanga/revenant_blindado.png', 'Quando chega a 0 HP pela primeira vez, retorna com 3 HP.', [
                $this->skill('Imortalidade Maldita', 'gatilho', 'ao_morrer', ['tipo' => 'ressurreicao_unica', 'vida' => 3, 'limite_partida' => 1]),
            ]),
            $this->unit('Curandeiro das Sombras', 'curandeiro-das-sombras', 'anhanga', 'Healer', 'rara', 6, 2, 6, 'anhanga/curandeiro_das_sombras.png', 'Ativo (2 energia): o jogador aliado perde 2 HP para curar completamente uma unidade aliada e remover debuffs.', [
                $this->skill('Pacto Sombrio', 'ativa', null, ['tipo' => 'pacto_cura_completa', 'custo_energia' => 2, 'custo_vida_jogador' => 2, 'alvo' => 'unidade_aliada']),
            ]),
            $this->unit('Espectro da Ruína', 'espectro-da-ruina', 'anhanga', 'Assassin', 'rara', 6, 4, 6, 'anhanga/espectro_da_ruina.png', 'Imune a Paralisia, Silêncio e Confusão.', [
                $this->skill('Forma Etérea', 'passiva', null, ['tipo' => 'imune_controle', 'efeitos' => ['silencio', 'paralisia', 'confusao', 'nao_pode_atacar']]),
            ]),
            $this->unit('O Profanado', 'o-profanado', 'anhanga', 'Support', 'lendaria', 8, 5, 9, 'anhanga/o_profanado.png', 'Ao morrer, invoca até 3 unidades Anhangá aleatórias do cemitério aliado com 1 HP.', [
                $this->skill('Exército dos Mortos', 'gatilho', 'ao_morrer', ['tipo' => 'reviver_ate_tres_do_cemiterio_linhagem', 'linhagem' => 'anhanga', 'vida' => 1, 'quantidade' => 3]),
            ]),
        ];
    }

    private function orunV21(): array
    {
        return [
            $this->unit('Fragmento Pulsante', 'fragmento-pulsante', 'orun', 'Support', 'comum', 1, 1, 2, 'orun/fragmento_pulsante.png', 'Ao morrer, concede +1 ATK ao Devorador de Estrelas aliado se ele estiver em campo.', [
                $this->skill('Eco do Vazio', 'gatilho', 'ao_morrer', ['tipo' => 'buff_carta_aliada_em_campo', 'slug' => 'devorador-de-estrelas', 'bonus_ataque' => 1]),
            ]),
            $this->unit('Sentinela Astral', 'sentinela-astral', 'orun', 'Tank', 'comum', 2, 1, 5, 'orun/sentinela_astral.png', 'Provocar. Ao morrer, aplica Paralisia em uma unidade inimiga aleatória.', [
                $this->skill('Provocar Cósmico', 'passiva', null, ['tipo' => 'provocar']),
                $this->skill('Pulso Astral', 'gatilho', 'ao_morrer', ['tipo' => 'paralisia_inimigo_aleatorio', 'duracao' => 1]),
            ]),
            $this->unit('Anjo Fragmentado', 'anjo-fragmentado', 'orun', 'DPS', 'comum', 2, 2, 3, 'orun/anjo_fragmentado.png', 'Fúria Inicial. Ao atacar, causa 1 de dano à unidade inimiga ao lado.', [
                $this->skill('Fúria Inicial', 'batalha_cry', 'ao_invocar', ['tipo' => 'charge', 'pode_atacar_imediato' => true]),
                $this->skill('Queda Celeste', 'gatilho', 'ao_atacar', ['tipo' => 'dano_adjacente', 'valor' => 1]),
            ]),
            $this->unit('Arauto do Vazio', 'arauto-do-vazio', 'orun', 'Support', 'comum', 3, 2, 3, 'orun/arauto_do_vazio.png', 'Ao ser invocado, revela a próxima carta do deck inimigo. Se ela custar 4 ou mais, causa 1 de dano ao jogador inimigo.', [
                $this->skill('Premonição', 'batalha_cry', 'ao_invocar', ['tipo' => 'revelar_e_dano_por_custo', 'quantidade' => 1, 'custo_minimo' => 4, 'dano' => 1]),
            ]),
            $this->unit('Espelho Cósmico', 'espelho-cosmico', 'orun', 'Support', 'comum', 3, 1, 4, 'orun/espelho_cosmico.png', 'Ao receber dano de uma habilidade inimiga, devolve 1 de dano fixo.', [
                $this->skill('Reflexo Astral', 'passiva', null, ['tipo' => 'reflexo_habilidade', 'valor' => 1]),
            ]),
            $this->unit('Predador Estelar', 'predador-estelar', 'orun', 'Assassin', 'comum', 3, 4, 2, 'orun/predador_estelar.png', 'Ataque Direto. Ao matar uma unidade inimiga, pode atacar novamente.', [
                $this->skill('Ataque Direto', 'passiva', null, ['tipo' => 'ataque_direto_jogador']),
                $this->skill('Singularidade', 'gatilho', 'ao_matar', ['tipo' => 'ataque_extra_ao_matar', 'limite_por_turno' => 1]),
            ]),
            $this->unit('Curador do Cosmos', 'curador-do-cosmos', 'orun', 'Healer', 'comum', 4, 1, 5, 'orun/curador_do_cosmos.png', 'No início do turno aliado, cura 2 HP de um aliado aleatório. Se tiver debuff, remove o debuff em vez de curar.', [
                $this->skill('Restauração Cósmica', 'gatilho', 'inicio_turno_aliado', ['tipo' => 'cura_ou_remove_debuff_aleatorio', 'valor' => 2]),
            ]),
            $this->unit('Colossus do Vazio', 'colossus-do-vazio', 'orun', 'Tank', 'comum', 4, 2, 8, 'orun/colossus_do_vazio.png', 'Provocar. Ao ser invocado, força uma unidade inimiga aleatória a atacá-lo imediatamente.', [
                $this->skill('Massa Gravitacional', 'passiva', null, ['tipo' => 'provocar']),
                $this->skill('Gravidade Hostil', 'batalha_cry', 'ao_invocar', ['tipo' => 'forcar_ataque_inimigo_aleatorio_imediato']),
            ]),
            $this->unit('Profeta do Eclipse', 'profeta-do-eclipse', 'orun', 'Support', 'rara', 4, 2, 5, 'orun/profeta_do_eclipse.png', 'Ativo (2 energia): aplica Paralisia em todas as unidades inimigas em campo por 1 turno.', [
                $this->skill('Visão do Fim', 'ativa', null, ['tipo' => 'paralisia_todas_inimigas', 'custo_energia' => 2, 'duracao' => 1]),
            ]),
            $this->unit('Guardião do Horizonte', 'guardiao-do-horizonte', 'orun', 'Tank', 'rara', 5, 3, 7, 'orun/guardiao_do_horizonte.png', 'Provocar. Ao receber dano fatal pela primeira vez, fica com 1 HP e ganha Véu Arcano.', [
                $this->skill('Barreira Dimensional', 'passiva', null, ['tipo' => 'sobreviver_dano_fatal', 'vida' => 1, 'ganha_veu_arcano' => true, 'limite_partida' => 1]),
                $this->skill('Provocar', 'passiva', null, ['tipo' => 'provocar']),
            ]),
            $this->unit('Estrela Cadente', 'estrela-cadente', 'orun', 'Assassin', 'rara', 5, 5, 2, 'orun/estrela_cadente.png', 'Fúria Inicial. Ao morrer, causa 1 de dano a todas as unidades inimigas.', [
                $this->skill('Fúria Inicial', 'batalha_cry', 'ao_invocar', ['tipo' => 'charge', 'pode_atacar_imediato' => true]),
                $this->skill('Impacto Orbital', 'gatilho', 'ao_morrer', ['tipo' => 'dano_todas_inimigas', 'valor' => 1]),
            ]),
            $this->unit('Restaurador Astral', 'restaurador-astral', 'orun', 'Healer', 'rara', 5, 2, 6, 'orun/restaurador_astral.png', 'Ativo (2 energia): revive a última unidade aliada destruída com HP cheio, uma vez por partida.', [
                $this->skill('Cura do Vazio', 'ativa', null, ['tipo' => 'reviver_ultimo_aliado', 'hp_percentual' => 100, 'custo_energia' => 2, 'limite_partida' => 1]),
            ]),
            $this->unit('Fragmento do Caos', 'fragmento-do-caos', 'orun', 'DPS', 'rara', 6, 4, 6, 'orun/fragmento_do_caos.png', 'No início do turno aliado, recebe aleatoriamente entre -2 e +4 ATK até o fim do turno.', [
                $this->skill('Instabilidade Cósmica', 'gatilho', 'inicio_turno_aliado', ['tipo' => 'bonus_ataque_turno_aleatorio', 'min' => -2, 'max' => 4]),
            ]),
            $this->unit('Arauto da Segunda Ruptura', 'arauto-da-segunda-ruptura', 'orun', 'DPS', 'lendaria', 8, 6, 8, 'orun/arauto_da_segunda_ruptura.png', 'Ao ser invocado, causa 2 de dano a todas as unidades em campo. Ganha +1 ATK e +1 HP para cada unidade destruída. Fúria Inicial.', [
                $this->skill('Ruptura Final', 'batalha_cry', 'ao_invocar', ['tipo' => 'dano_todas_unidades_cresce_por_mortes', 'valor' => 2, 'bonus_ataque' => 1, 'bonus_vida' => 1]),
                $this->skill('Fúria Inicial', 'batalha_cry', 'ao_invocar', ['tipo' => 'charge', 'pode_atacar_imediato' => true]),
            ]),
        ];
    }

    private function spellsV21(): array
    {
        return [
            $this->spell('Faísca Arcana', 'faisca-arcana', 'Feitiço - Dano', 'comum', 1, 'spells/faisca_arcana.png', 'Causa 2 de dano a uma unidade inimiga à escolha.', ['tipo' => 'dano_alvo', 'valor' => 2, 'alvo' => 'unidade_inimiga']),
            $this->spell('Toque Vital', 'toque-vital', 'Feitiço - Cura', 'comum', 1, 'spells/toque_vital.png', 'Cura 3 HP de uma unidade aliada à escolha.', ['tipo' => 'cura_alvo', 'valor' => 3, 'alvo' => 'unidade_aliada']),
            $this->spell('Sopro de Força', 'sopro-de-forca', 'Feitiço - Buff', 'comum', 1, 'spells/sopro_de_forca.png', 'Uma unidade aliada ganha +2 ATK neste turno.', ['tipo' => 'buff_ataque_turno', 'valor' => 2, 'alvo' => 'unidade_aliada']),
            $this->spell('Golpe Enfraquecedor', 'golpe-enfraquecedor', 'Feitiço - Debuff', 'comum', 1, 'spells/golpe_enfraquecedor.png', 'Uma unidade inimiga perde -1 ATK por 1 turno.', ['tipo' => 'debuff_ataque', 'valor' => 1, 'duracao' => 1, 'alvo' => 'unidade_inimiga']),
            $this->spell('Véu Passageiro', 'veu-passageiro', 'Feitiço - Defesa', 'comum', 2, 'spells/veu_passageiro.png', 'Aplica Véu Arcano em uma unidade aliada à escolha.', ['tipo' => 'veu_arcano', 'cargas' => 1, 'alvo' => 'unidade_aliada']),
            $this->spell('Choque Paralisante', 'choque-paralisante', 'Feitiço - Controle', 'comum', 2, 'spells/choque_paralisante.png', 'Aplica Paralisia em uma unidade inimiga por 1 turno.', ['tipo' => 'paralisia', 'duracao' => 1, 'alvo' => 'unidade_inimiga']),
            $this->spell('Pulso Restaurador', 'pulso-restaurador', 'Feitiço - Cura', 'comum', 2, 'spells/pulso_restaurador.png', 'Cura 2 HP de todos os aliados em campo.', ['tipo' => 'cura_todos_aliados', 'valor' => 2]),
            $this->spell('Ímpeto Momentâneo', 'impeto-momentaneo', 'Feitiço - Buff', 'comum', 2, 'spells/impeto_momentaneo.png', 'Uma unidade aliada pode atacar novamente neste turno.', ['tipo' => 'liberar_ataque_extra', 'alvo' => 'unidade_aliada', 'limite_por_turno' => 1]),
            $this->spell('Silêncio Repentino', 'silencio-repentino', 'Feitiço - Controle', 'comum', 2, 'spells/silencio_repentino.png', 'Aplica Silêncio em uma unidade inimiga por 1 turno.', ['tipo' => 'silencio', 'duracao' => 1, 'alvo' => 'unidade_inimiga']),
        ];
    }
}
