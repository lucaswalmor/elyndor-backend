<?php

namespace Database\Seeders;

use App\Models\Chest;
use App\Models\ChestItem;
use App\Models\Card;
use App\Models\WeeklyChestPool;
use Illuminate\Database\Seeder;

/**
 * Catálogo oficial de baús + pool semanal. Editar apenas o método catalogoBaús().
 *
 * Campos do baú: name, description (texto para o jogador), cost_moedas, cost_cristais,
 * available_in_shop, active, sort_order, pity_epic_every (null = sem pity).
 *
 * Regra de moeda: loja premium (facções, cosméticos, baú premium) = moedas; baú de cristal = cristais.
 */
class BausPoolsSemanalSeeder extends Seeder
{
    /**
     * @return array<string, array{chest: array<string, mixed>, items: list<array<string, mixed>>}>
     */
    private function catalogoBaús(): array
    {
        $pityVersos = max(0, (int) config('game.chests.pity_epic_every', 20));
        $premiumCost = (int) config('game.chests.premium_padrao.cost_moedas', 0);

        $catalog = [
            /*
            |--------------------------------------------------------------------------
            | Recompensa semanal (só resgate XP — não aparece na loja)
            |--------------------------------------------------------------------------
            */
            'bau_recompensa_semanal' => [
                'chest' => [
                    'name' => 'Baú de recompensa semanal',
                    'description' => 'Recompensa pelo teu progresso na semana. Contém sorteio entre várias cartas da coleção base.',
                    'cost_moedas' => null,
                    'cost_cristais' => null,
                    'available_in_shop' => false,
                    'active' => true,
                    'sort_order' => 0,
                    'pity_epic_every' => null,
                ],
                'items' => [
                    ['asset_category' => 'card', 'asset_key' => 'cao-vulcanico', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 0],
                    ['asset_category' => 'card', 'asset_key' => 'bruxa-cinzenta', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 1],
                    ['asset_category' => 'card', 'asset_key' => 'morcego-igneo', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 2],
                    ['asset_category' => 'card', 'asset_key' => 'aranha-lunar', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 3],
                    ['asset_category' => 'card', 'asset_key' => 'espirito-da-raiz', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 4],
                    ['asset_category' => 'card', 'asset_key' => 'sapo-toxico', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 5],
                    ['asset_category' => 'card', 'asset_key' => 'drone-sentinela', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 6],
                    ['asset_category' => 'card', 'asset_key' => 'corvo-funerario', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 7],
                    ['asset_category' => 'card', 'asset_key' => 'carniceiro-de-brasas', 'display_tier' => 'rara', 'drop_weight' => 1, 'sort_order' => 8],
                    ['asset_category' => 'card', 'asset_key' => 'tita-magmatico', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 9],
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Baú premium (loja — moedas): todas as categorias de loot, por raridade
            | 1 × lendário + 2 × épico + 3 × raro + 4 × comum — por tipo: card, card_back, profile_bg, match_board
            |--------------------------------------------------------------------------
            */
            'premium_padrao' => [
                'chest' => [
                    'name' => 'Baú premium',
                    'description' => 'Sorteio de alta qualidade: cartas, versos, fundos de perfil e tabuleiros, com todas as raridades representadas em cada tipo de recompensa.',
                    'cost_moedas' => 3000,
                    'cost_cristais' => null,
                    'available_in_shop' => true,
                    'active' => true,
                    'sort_order' => 40,
                    'pity_epic_every' => $pityVersos > 0 ? $pityVersos : null,
                ],
                'items' => $this->itensBauPremiumPadrao(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Baú de cristal (loja — cristais): pool com todas as cartas comuns + 4 raras + 2 épicas da coleção base.
            | Cada abertura sorteia 1 carta do pool (pesos iguais por linha).
            |--------------------------------------------------------------------------
            */
            'chest_cristal_basico' => [
                'chest' => [
                    'name' => 'Baú de cristal',
                    'description' => 'Uma carta aleatória: todas as comuns da coleção base, 4 raras e 2 épicas destacadas — compra com cristais.',
                    'cost_moedas' => null,
                    'cost_cristais' => max(1, (int) config('game.chests.cristal_basico.cost_cristais', 800)),
                    'available_in_shop' => true,
                    'active' => true,
                    'sort_order' => 15,
                    'pity_epic_every' => null,
                ],
                'items' => $this->itensBauCristalBasico(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Baús de cartas por linhagem (loja — moedas / premium)
            |--------------------------------------------------------------------------
            */
            'bau_cartas_karuna' => [
                'chest' => [
                    'name' => "Baú de cartas — Ka'runa",
                    'description' => "Uma carta aleatória entre as unidades da linhagem Ka'runa.",
                    'cost_moedas' => 950,
                    'cost_cristais' => null,
                    'available_in_shop' => true,
                    'active' => true,
                    'sort_order' => 20,
                    'pity_epic_every' => null,
                ],
                'items' => [
                    ['asset_category' => 'card', 'asset_key' => 'carniceiro-de-brasas', 'display_tier' => 'rara', 'drop_weight' => 1, 'sort_order' => 0],
                    ['asset_category' => 'card', 'asset_key' => 'cao-vulcanico', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 1],
                    ['asset_category' => 'card', 'asset_key' => 'bruxa-cinzenta', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 2],
                    ['asset_category' => 'card', 'asset_key' => 'tita-magmatico', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 3],
                    ['asset_category' => 'card', 'asset_key' => 'morcego-igneo', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 4],
                    ['asset_category' => 'card', 'asset_key' => 'rei-das-correntes', 'display_tier' => 'rara', 'drop_weight' => 1, 'sort_order' => 5],
                ],
            ],
            'bau_cartas_ybyra' => [
                'chest' => [
                    'name' => 'Baú de cartas — Ybyrá',
                    'description' => 'Uma carta aleatória entre as unidades da linhagem Ybyrá.',
                    'cost_moedas' => 950,
                    'cost_cristais' => null,
                    'available_in_shop' => true,
                    'active' => true,
                    'sort_order' => 21,
                    'pity_epic_every' => null,
                ],
                'items' => [
                    ['asset_category' => 'card', 'asset_key' => 'guardiao-do-musgo', 'display_tier' => 'rara', 'drop_weight' => 1, 'sort_order' => 0],
                    ['asset_category' => 'card', 'asset_key' => 'aranha-lunar', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 1],
                    ['asset_category' => 'card', 'asset_key' => 'espirito-da-raiz', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 2],
                    ['asset_category' => 'card', 'asset_key' => 'sapo-toxico', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 3],
                    ['asset_category' => 'card', 'asset_key' => 'cervo-fantasma', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 4],
                    ['asset_category' => 'card', 'asset_key' => 'hidra-do-pantano', 'display_tier' => 'rara', 'drop_weight' => 1, 'sort_order' => 5],
                ],
            ],
            'bau_cartas_ferroveu' => [
                'chest' => [
                    'name' => 'Baú de cartas — Ferrovéu',
                    'description' => 'Uma carta aleatória entre as unidades da linhagem Ferrovéu.',
                    'cost_moedas' => 950,
                    'cost_cristais' => null,
                    'available_in_shop' => true,
                    'active' => true,
                    'sort_order' => 22,
                    'pity_epic_every' => null,
                ],
                'items' => [
                    ['asset_category' => 'card', 'asset_key' => 'drone-sentinela', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 0],
                    ['asset_category' => 'card', 'asset_key' => 'executor-de-ferro', 'display_tier' => 'rara', 'drop_weight' => 1, 'sort_order' => 1],
                    ['asset_category' => 'card', 'asset_key' => 'engenheira-tesla', 'display_tier' => 'rara', 'drop_weight' => 1, 'sort_order' => 2],
                    ['asset_category' => 'card', 'asset_key' => 'aranha-de-sucata', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 3],
                    ['asset_category' => 'card', 'asset_key' => 'tremor-mk-ii', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 4],
                    ['asset_category' => 'card', 'asset_key' => 'nucleo-automato', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 5],
                ],
            ],
            'bau_cartas_anhanga' => [
                'chest' => [
                    'name' => 'Baú de cartas — Anhangá',
                    'description' => 'Uma carta aleatória entre as unidades da linhagem Anhangá.',
                    'cost_moedas' => 950,
                    'cost_cristais' => null,
                    'available_in_shop' => true,
                    'active' => true,
                    'sort_order' => 23,
                    'pity_epic_every' => null,
                ],
                'items' => [
                    ['asset_category' => 'card', 'asset_key' => 'cavaleiro-sem-face', 'display_tier' => 'rara', 'drop_weight' => 1, 'sort_order' => 0],
                    ['asset_category' => 'card', 'asset_key' => 'costureira-macabra', 'display_tier' => 'rara', 'drop_weight' => 1, 'sort_order' => 1],
                    ['asset_category' => 'card', 'asset_key' => 'corvo-funerario', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 2],
                    ['asset_category' => 'card', 'asset_key' => 'monge-apodrecido', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 3],
                    ['asset_category' => 'card', 'asset_key' => 'gigante-ossuario', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 4],
                    ['asset_category' => 'card', 'asset_key' => 'crianca-do-veu', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 5],
                ],
            ],
            'bau_cartas_orun' => [
                'chest' => [
                    'name' => 'Baú de cartas — Orun',
                    'description' => 'Uma carta aleatória entre as unidades da linhagem Orun.',
                    'cost_moedas' => 950,
                    'cost_cristais' => null,
                    'available_in_shop' => true,
                    'active' => true,
                    'sort_order' => 24,
                    'pity_epic_every' => null,
                ],
                'items' => [
                    ['asset_category' => 'card', 'asset_key' => 'oraculo-solar', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 0],
                    ['asset_category' => 'card', 'asset_key' => 'aberracao-do-vazio', 'display_tier' => 'rara', 'drop_weight' => 1, 'sort_order' => 1],
                    ['asset_category' => 'card', 'asset_key' => 'serafim-partido', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 2],
                    ['asset_category' => 'card', 'asset_key' => 'eclipse-vivo', 'display_tier' => 'rara', 'drop_weight' => 1, 'sort_order' => 3],
                    ['asset_category' => 'card', 'asset_key' => 'navegante-astral', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 4],
                    ['asset_category' => 'card', 'asset_key' => 'devorador-de-estrelas', 'display_tier' => 'lendaria', 'drop_weight' => 1, 'sort_order' => 5],
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Cosméticos: fundos de perfil (loja — moedas / premium)
            |--------------------------------------------------------------------------
            */
            'bau_cosmetico_fundos' => [
                'chest' => [
                    'name' => 'Baú de fundos de perfil',
                    'description' => 'Destaca o teu perfil: fundos alinhados com cada facção ou visuais premium.',
                    'cost_moedas' => 1100,
                    'cost_cristais' => null,
                    'available_in_shop' => true,
                    'active' => true,
                    'sort_order' => 30,
                    'pity_epic_every' => null,
                ],
                'items' => [
                    ['asset_category' => 'profile_bg', 'asset_key' => 'ui_bg_profile_infernal', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 0],
                    ['asset_category' => 'profile_bg', 'asset_key' => 'ui_bg_profile_nature', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 1],
                    ['asset_category' => 'profile_bg', 'asset_key' => 'ui_bg_profile_mechanics', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 2],
                    ['asset_category' => 'profile_bg', 'asset_key' => 'ui_bg_profile_undead', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 3],
                    ['asset_category' => 'profile_bg', 'asset_key' => 'ui_bg_profile_celestial', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 4],
                    ['asset_category' => 'profile_bg', 'asset_key' => 'ui_bg_profile_crystal', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 10],
                    ['asset_category' => 'profile_bg', 'asset_key' => 'ui_bg_profile_gold', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 11],
                    ['asset_category' => 'profile_bg', 'asset_key' => 'ui_bg_profile_heaven', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 12],
                    ['asset_category' => 'profile_bg', 'asset_key' => 'ui_bg_profile_jade', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 13],
                    ['asset_category' => 'profile_bg', 'asset_key' => 'ui_bg_profile_obsidian', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 14],
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Cosméticos: tabuleiros (loja — moedas / premium)
            |--------------------------------------------------------------------------
            */
            'bau_cosmetico_tabuleiros' => [
                'chest' => [
                    'name' => 'Baú de tabuleiros',
                    'description' => 'Personaliza o campo de batalha com tabuleiros temáticos ou acabamentos premium.',
                    'cost_moedas' => 1100,
                    'cost_cristais' => null,
                    'available_in_shop' => true,
                    'active' => true,
                    'sort_order' => 31,
                    'pity_epic_every' => null,
                ],
                'items' => [
                    ['asset_category' => 'match_board', 'asset_key' => 'tabuleiro_ferrox', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 0],
                    ['asset_category' => 'match_board', 'asset_key' => 'tabuleiro_sylvaris', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 1],
                    ['asset_category' => 'match_board', 'asset_key' => 'tabuleiro_nocthar', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 2],
                    ['asset_category' => 'match_board', 'asset_key' => 'tabuleiro_vulkaris', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 3],
                    ['asset_category' => 'match_board', 'asset_key' => 'tabuleiro_astra_veil', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 4],
                    ['asset_category' => 'match_board', 'asset_key' => 'tabuleiro_premium_cristal', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 10],
                    ['asset_category' => 'match_board', 'asset_key' => 'tabuleiro_premium_jade', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 11],
                    ['asset_category' => 'match_board', 'asset_key' => 'tabuleiro_premium_obsidiana', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 12],
                    ['asset_category' => 'match_board', 'asset_key' => 'tabuleiro_premium_ouro', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 13],
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Versos de carta (loja — moedas / premium; pity épico+ conforme config)
            |--------------------------------------------------------------------------
            */
            'bau_cosmetico_versos' => [
                'chest' => [
                    'name' => 'Baú de versos',
                    'description' => 'Sorteia um verso para as tuas cartas na partida. Inclui linhas comuns, épicas e lendárias.',
                    'cost_moedas' => 1300,
                    'cost_cristais' => null,
                    'available_in_shop' => true,
                    'active' => true,
                    'sort_order' => 32,
                    'pity_epic_every' => $pityVersos > 0 ? $pityVersos : null,
                ],
                'items' => [
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_padrao', 'display_tier' => 'comum', 'drop_weight' => 8, 'sort_order' => 0],
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_ferrox', 'display_tier' => 'comum', 'drop_weight' => 8, 'sort_order' => 1],
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_sylvaris', 'display_tier' => 'comum', 'drop_weight' => 8, 'sort_order' => 2],
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_premium_jade', 'display_tier' => 'comum', 'drop_weight' => 8, 'sort_order' => 3],
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_premium_ouro', 'display_tier' => 'comum', 'drop_weight' => 8, 'sort_order' => 4],
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_nocthar', 'display_tier' => 'epica', 'drop_weight' => 3, 'sort_order' => 20],
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_vulkaris', 'display_tier' => 'epica', 'drop_weight' => 3, 'sort_order' => 21],
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_premium_cristal', 'display_tier' => 'epica', 'drop_weight' => 3, 'sort_order' => 22],
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_premium_obsidiana', 'display_tier' => 'epica', 'drop_weight' => 3, 'sort_order' => 23],
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_astra_veil', 'display_tier' => 'lendaria', 'drop_weight' => 1, 'sort_order' => 40],
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_da_carta', 'display_tier' => 'lendaria', 'drop_weight' => 1, 'sort_order' => 41],
                ],
            ],
        ];

        foreach (['karuna', 'ybyra', 'ferroveu', 'anhanga', 'orun'] as $linhagem) {
            $catalog["bau_cartas_{$linhagem}"]['items'] = $this->cardItemsByLineage($linhagem);
        }
        $catalog['bau_recompensa_semanal']['items'] = $this->cardItemsForWeekly();
        $catalog['chest_cristal_basico']['items'] = $this->itensBauCristalBasico();
        $catalog['premium_padrao']['items'] = $this->itensBauPremiumPadrao();

        return $catalog;
    }

    /**
     * Baú de cristal: todas as cartas comuns da coleção base (CartasSeeder) + 4 raras + 2 épicas.
     * Sem lendárias. Pesos iguais por linha (1 sorteio = 1 carta).
     *
     * @return list<array{asset_category: string, asset_key: string, display_tier: string, drop_weight: int, sort_order: int}>
     */
    private function itensBauCristalBasico(): array
    {
        return $this->cardItemsFromQuery(
            Card::query()
                ->where('ativo', true)
                ->where('colecionavel', true)
                ->where('tipo', 'unit')
                ->whereIn('raridade', ['comum', 'rara', 'epica'])
                ->orderBy('raridade')
                ->orderBy('linhagem')
                ->orderBy('custo')
                ->orderBy('slug')
        );
    }

    /**
     * Baú premium: em cada asset_category (card, card_back, profile_bg, match_board),
     * exatamente 1 lendário, 2 épicos, 3 raros e 4 comuns no pool do sorteio.
     * O display_tier alinha com a raridade de apresentação / pity (as cartas usam a raridade real na BD na abertura).
     *
     * @return list<array{asset_category: string, asset_key: string, display_tier: string, drop_weight: int, sort_order: int}>
     */
    private function itensBauPremiumPadrao(): array
    {
        $sort = 0;
        $rows = [];
        $row = function (
            string $category,
            string $key,
            string $tier,
            int $weight = 1,
        ) use (&$rows, &$sort): void {
            $rows[] = [
                'asset_category' => $category,
                'asset_key' => $key,
                'display_tier' => $tier,
                'drop_weight' => $weight,
                'sort_order' => $sort++,
            ];
        };

        // —— Cartas (inclui spells neutras; raridades = tabela cards) ——
        foreach ($this->cardItemsFromQuery(
            Card::query()
                ->where('ativo', true)
                ->where('colecionavel', true)
                ->orderBy('tipo')
                ->orderBy('linhagem')
                ->orderBy('raridade')
                ->orderBy('custo')
                ->orderBy('slug')
        ) as $cardItem) {
            $row('card', $cardItem['asset_key'], $cardItem['display_tier'], 1);
        }

        // —— Versos (tiers para pity / UI; chaves = PNGs em frame_cards/) ——
        $row('card_back', 'verso_brasil_copa_2026', 'lendaria', 1);
        $row('card_back', 'verso_da_carta', 'lendaria', 1);
        $row('card_back', 'verso_nocthar', 'epica', 3);
        $row('card_back', 'verso_vulkaris', 'epica', 3);
        $row('card_back', 'verso_premium_cristal', 'rara', 10);
        $row('card_back', 'verso_premium_obsidiana', 'rara', 10);
        $row('card_back', 'verso_comum', 'rara', 10);
        $row('card_back', 'verso_padrao', 'comum', 20);
        $row('card_back', 'verso_ferrox', 'comum', 20);
        $row('card_back', 'verso_sylvaris', 'comum', 20);
        $row('card_back', 'verso_premium_ouro', 'comum', 20);

        // —— Fundos de perfil ——
        $row('profile_bg', 'ui_bg_profile_obsidian', 'lendaria', 1);
        $row('profile_bg', 'ui_bg_profile_heaven', 'epica', 3);
        $row('profile_bg', 'ui_bg_profile_jade', 'epica', 3);
        $row('profile_bg', 'ui_bg_profile_celestial', 'rara', 10);
        $row('profile_bg', 'ui_bg_profile_crystal', 'rara', 10);
        $row('profile_bg', 'ui_bg_profile_gold', 'rara', 10);
        $row('profile_bg', 'ui_bg_profile_infernal', 'comum', 20);
        $row('profile_bg', 'ui_bg_profile_nature', 'comum', 20);
        $row('profile_bg', 'ui_bg_profile_mechanics', 'comum', 20);
        $row('profile_bg', 'ui_bg_profile_undead', 'comum', 20);

        // —— Tabuleiros ——
        $row('match_board', 'tabuleiro_brasil_copa_2026', 'lendaria', 1);
        $row('match_board', 'tabuleiro_astra_veil', 'lendaria', 1);
        $row('match_board', 'tabuleiro_premium_obsidiana', 'epica', 3);
        $row('match_board', 'tabuleiro_premium_ouro', 'epica', 3);
        $row('match_board', 'tabuleiro_premium_cristal', 'rara', 10);
        $row('match_board', 'tabuleiro_premium_jade', 'rara', 10);
        $row('match_board', 'tabuleiro_vulkaris', 'rara', 10);
        $row('match_board', 'tabuleiro_ferrox', 'comum', 20);
        $row('match_board', 'tabuleiro_sylvaris', 'comum', 20);
        $row('match_board', 'tabuleiro_nocthar', 'comum', 20);
        $row('match_board', 'tabuleiro_padrao_v2', 'comum', 20);

        return $rows;
    }

    /**
     * @return list<array{asset_category: string, asset_key: string, display_tier: string, drop_weight: int, sort_order: int}>
     */
    private function cardItemsByLineage(string $linhagem): array
    {
        return $this->cardItemsFromQuery(
            Card::query()
                ->where('ativo', true)
                ->where('colecionavel', true)
                ->where('tipo', 'unit')
                ->where('linhagem', $linhagem)
                ->orderBy('raridade')
                ->orderBy('custo')
                ->orderBy('slug')
        );
    }

    /**
     * @return list<array{asset_category: string, asset_key: string, display_tier: string, drop_weight: int, sort_order: int}>
     */
    private function cardItemsForWeekly(): array
    {
        return $this->cardItemsFromQuery(
            Card::query()
                ->where('ativo', true)
                ->where('colecionavel', true)
                ->where('tipo', 'unit')
                ->orderBy('raridade')
                ->orderBy('linhagem')
                ->orderBy('custo')
                ->orderBy('slug')
        );
    }

    /**
     * @return list<array{asset_category: string, asset_key: string, display_tier: string, drop_weight: int, sort_order: int}>
     */
    private function cardItemsFromQuery(\Illuminate\Database\Eloquent\Builder $query): array
    {
        $sort = 0;

        return $query->get(['slug', 'raridade'])->map(function (Card $card) use (&$sort) {
            return [
                'asset_category' => 'card',
                'asset_key' => $card->slug,
                'display_tier' => $card->raridade,
                'drop_weight' => 1,
                'sort_order' => $sort++,
            ];
        })->values()->all();
    }

    private function retirarBaúsLegadosDaLoja(): void
    {
        $aviso = 'Conteúdo de campanhas antigas. Se tiveres unidades, podes abri-las no inventário.';

        $slugs = ['cristal_basico', 'bau_cosmetico_iniciante'];

        foreach ($slugs as $slug) {
            Chest::query()->where('slug', $slug)->update([
                'available_in_shop' => false,
                'description' => $aviso,
            ]);
        }
    }

    public function run(): void
    {
        $catalogo = $this->catalogoBaús();

        foreach ($catalogo as $slug => $bloco) {
            $chest = Chest::query()->updateOrCreate(
                ['slug' => $slug],
                array_merge($bloco['chest'], [])
            );

            ChestItem::query()->where('chest_id', $chest->id)->delete();

            foreach ($bloco['items'] as $row) {
                ChestItem::query()->create([
                    'chest_id' => $chest->id,
                    'asset_category' => $row['asset_category'],
                    'asset_key' => $row['asset_key'],
                    'display_tier' => $row['display_tier'],
                    'drop_weight' => (int) $row['drop_weight'],
                    'sort_order' => (int) $row['sort_order'],
                ]);
            }
        }

        $this->retirarBaúsLegadosDaLoja();

        $pool = WeeklyChestPool::query()->updateOrCreate(
            ['slug' => 'padrao'],
            [
                'name' => 'Recompensa semanal — padrão',
                'active' => true,
            ]
        );

        $bauSemanal = Chest::query()->where('slug', 'bau_recompensa_semanal')->first();
        if ($bauSemanal) {
            $pool->chests()->sync([$bauSemanal->id => ['weight' => 100]]);
        }
    }
}
