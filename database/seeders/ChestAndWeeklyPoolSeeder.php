<?php

namespace Database\Seeders;

use App\Models\Chest;
use App\Models\ChestItem;
use App\Models\WeeklyChestPool;
use Illuminate\Database\Seeder;

/**
 * Catálogo oficial de baús + pool semanal. Editar apenas o método catalogoBaús().
 *
 * Campos do baú: name, description (texto para o jogador), cost_moedas, cost_cristais,
 * available_in_shop, active, sort_order, pity_epic_every (null = sem pity).
 */
class ChestAndWeeklyPoolSeeder extends Seeder
{
    /**
     * @return array<string, array{chest: array<string, mixed>, items: list<array<string, mixed>>}>
     */
    private function catalogoBaús(): array
    {
        $pityVersos = max(0, (int) config('game.chests.pity_epic_every', 20));
        $premiumCost = (int) config('game.chests.premium_padrao.cost_moedas', 0);

        return [
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
            | Baús de cartas por facção (loja — cristais)
            |--------------------------------------------------------------------------
            */
            'bau_cartas_infernais' => [
                'chest' => [
                    'name' => 'Baú de cartas — Infernais',
                    'description' => 'Uma carta aleatória entre as unidades da facção Infernais.',
                    'cost_moedas' => null,
                    'cost_cristais' => 950,
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
            'bau_cartas_natureza' => [
                'chest' => [
                    'name' => 'Baú de cartas — Natureza',
                    'description' => 'Uma carta aleatória entre as unidades da facção Natureza.',
                    'cost_moedas' => null,
                    'cost_cristais' => 950,
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
            'bau_cartas_mecanicos' => [
                'chest' => [
                    'name' => 'Baú de cartas — Mecânicos',
                    'description' => 'Uma carta aleatória entre as unidades da facção Mecânicos.',
                    'cost_moedas' => null,
                    'cost_cristais' => 950,
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
            'bau_cartas_mortos_vivos' => [
                'chest' => [
                    'name' => 'Baú de cartas — Mortos-vivos',
                    'description' => 'Uma carta aleatória entre as unidades da facção Mortos-vivos.',
                    'cost_moedas' => null,
                    'cost_cristais' => 950,
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
            'bau_cartas_celestiais' => [
                'chest' => [
                    'name' => 'Baú de cartas — Celestiais',
                    'description' => 'Uma carta aleatória entre as unidades Celestiais (Vazio).',
                    'cost_moedas' => null,
                    'cost_cristais' => 950,
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
            | Cosméticos: fundos de perfil (facção = comum; restantes = épico)
            |--------------------------------------------------------------------------
            */
            'bau_cosmetico_fundos' => [
                'chest' => [
                    'name' => 'Baú de fundos de perfil',
                    'description' => 'Destaca o teu perfil: fundos alinhados com cada facção ou visuais premium.',
                    'cost_moedas' => null,
                    'cost_cristais' => 1100,
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
            | Cosméticos: tabuleiros (estética de facção = comum; linha premium = épico)
            |--------------------------------------------------------------------------
            */
            'bau_cosmetico_tabuleiros' => [
                'chest' => [
                    'name' => 'Baú de tabuleiros',
                    'description' => 'Personaliza o campo de batalha com tabuleiros temáticos ou acabamentos premium.',
                    'cost_moedas' => null,
                    'cost_cristais' => 1100,
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
            | Versos de carta (comum / épico / lendário) + pity épico+
            |--------------------------------------------------------------------------
            */
            'bau_cosmetico_versos' => [
                'chest' => [
                    'name' => 'Baú de versos',
                    'description' => 'Sorteia um verso para as tuas cartas na partida. Inclui linhas comuns, épicas e lendárias.',
                    'cost_moedas' => null,
                    'cost_cristais' => 1300,
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

        // —— Cartas (raridades = tabela cards) ——
        $row('card', 'devorador-de-estrelas', 'lendaria');
        $row('card', 'tita-magmatico', 'epica');
        $row('card', 'serafim-partido', 'epica');
        $row('card', 'carniceiro-de-brasas', 'rara');
        $row('card', 'rei-das-correntes', 'rara');
        $row('card', 'hidra-do-pantano', 'rara');
        $row('card', 'cao-vulcanico', 'comum');
        $row('card', 'bruxa-cinzenta', 'comum');
        $row('card', 'morcego-igneo', 'comum');
        $row('card', 'drone-sentinela', 'comum');

        // —— Versos (tiers para pity / UI; chaves = PNGs em frame_cards/) ——
        $row('card_back', 'verso_da_carta', 'lendaria');
        $row('card_back', 'verso_nocthar', 'epica');
        $row('card_back', 'verso_vulkaris', 'epica');
        $row('card_back', 'verso_premium_cristal', 'rara');
        $row('card_back', 'verso_premium_obsidiana', 'rara');
        $row('card_back', 'verso_comum', 'rara');
        $row('card_back', 'verso_padrao', 'comum');
        $row('card_back', 'verso_ferrox', 'comum');
        $row('card_back', 'verso_sylvaris', 'comum');
        $row('card_back', 'verso_premium_ouro', 'comum');

        // —— Fundos de perfil ——
        $row('profile_bg', 'ui_bg_profile_obsidian', 'lendaria');
        $row('profile_bg', 'ui_bg_profile_heaven', 'epica');
        $row('profile_bg', 'ui_bg_profile_jade', 'epica');
        $row('profile_bg', 'ui_bg_profile_celestial', 'rara');
        $row('profile_bg', 'ui_bg_profile_crystal', 'rara');
        $row('profile_bg', 'ui_bg_profile_gold', 'rara');
        $row('profile_bg', 'ui_bg_profile_infernal', 'comum');
        $row('profile_bg', 'ui_bg_profile_nature', 'comum');
        $row('profile_bg', 'ui_bg_profile_mechanics', 'comum');
        $row('profile_bg', 'ui_bg_profile_undead', 'comum');

        // —— Tabuleiros ——
        $row('match_board', 'tabuleiro_astra_veil', 'lendaria');
        $row('match_board', 'tabuleiro_premium_obsidiana', 'epica');
        $row('match_board', 'tabuleiro_premium_ouro', 'epica');
        $row('match_board', 'tabuleiro_premium_cristal', 'rara');
        $row('match_board', 'tabuleiro_premium_jade', 'rara');
        $row('match_board', 'tabuleiro_vulkaris', 'rara');
        $row('match_board', 'tabuleiro_ferrox', 'comum');
        $row('match_board', 'tabuleiro_sylvaris', 'comum');
        $row('match_board', 'tabuleiro_nocthar', 'comum');
        $row('match_board', 'tabuleiro_padrao_v2', 'comum');

        return $rows;
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
