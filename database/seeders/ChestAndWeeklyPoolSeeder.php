<?php

namespace Database\Seeders;

use App\Models\Chest;
use App\Models\ChestItem;
use App\Models\WeeklyChestPool;
use Illuminate\Database\Seeder;

/**
 * Seeder responsável por:
 * - criar/atualizar os baús (`chests`)
 * - criar os itens de loot (`chest_items`)
 * - configurar o pool semanal padrão (`weekly_chest_pools`)
 *
 * ============================================================================
 * ESTRUTURA DO CATÁLOGO
 * ============================================================================
 *
 * Cada baú possui a seguinte estrutura:
 *
 * 'slug_do_bau' => [
 *     'chest' => [],
 *     'items' => [],
 * ]
 *
 * O slug é o identificador fixo do baú usado no backend, API e frontend.
 *
 *
 * ============================================================================
 * CAMPOS DE `chest`
 * ============================================================================
 *
 * name
 * Nome exibido para o jogador.
 *
 * description
 * Texto descritivo do baú.
 *
 * cost_moedas
 * Preço em moedas premium.
 * Use null caso o baú não possa ser comprado com moedas.
 *
 * cost_cristais
 * Preço em cristais.
 * Use null caso o baú não possa ser comprado com cristais.
 *
 * available_in_shop
 * Define se o baú aparece na loja do jogo.
 *
 * active
 * Define se o baú está ativo/disponível.
 *
 * sort_order
 * Ordem de exibição na interface.
 * Menor número = aparece primeiro.
 *
 * pity_epic_every
 * Sistema pity.
 * Garante recompensa épica ou superior após X aberturas.
 * Use null para desativar.
 *
 *
 * ============================================================================
 * CAMPOS DE `items`
 * ============================================================================
 *
 * Cada item representa um possível drop do baú.
 *
 * Exemplo:
 *
 * [
 *     'asset_category' => 'profile_bg',
 *     'asset_key' => 'ui_bg_profile_celestial',
 *     'display_tier' => 'epica',
 *     'drop_weight' => 1,
 *     'sort_order' => 0,
 * ]
 *
 *
 * ============================================================================
 * CAMPOS DOS ITENS
 * ============================================================================
 *
 * asset_category
 * Tipo do item.
 *
 * Exemplos:
 * - card
 * - card_back
 * - card_frame
 * - profile_bg
 *
 *
 * asset_key
 * Identificador único do asset.
 *
 * Exemplos:
 * - bruxa-cinzenta
 * - verso_infernais
 * - ui_bg_profile_celestial
 *
 * O frontend usa essa chave para localizar a imagem/recurso correto.
 *
 *
 * display_tier
 * Raridade visual do item.
 *
 * Valores aceitos:
 * - comum
 * - rara
 * - epica
 * - lendaria
 *
 *
 * drop_weight
 * Peso relativo no sorteio.
 *
 * Quanto maior o valor:
 * maior a chance do item aparecer.
 *
 * Exemplo:
 * item com peso 10 tem aproximadamente
 * 10x mais chance que item com peso 1.
 *
 *
 * sort_order
 * Ordem de exibição do item nas interfaces.
 *
 *
 * ============================================================================
 * asset_category ↔ pasta em `frontend/src/assets/imagens/`
 * ============================================================================
 *
 * No inventário (Vue), o preview resolve PNGs assim — exceto `card`, que só usa subpastas:
 *
 * | asset_category    | Pasta                 | asset_key / notas                              |
 * |-------------------|-----------------------|------------------------------------------------|
 * | card              | cards/{facção}/       | `cards.slug` (igual à BD); imagem via `imagem_path` na API |
 * | card_back         | frame_cards/          | nome do .png sem extensão                      |
 * | profile_bg        | backgrounds/          | idem                                           |
 * | avatar            | avatares/             | idem                                           |
 * | faction_icon      | icones_faccoes/       | idem                                           |
 * | attribute_icon    | icones_atributos/     | idem                                           |
 * | status_buff_icon  | icones_status_buffs/  | idem                                           |
 * | match_board       | tabuleiros/           | idem                                           |
 * | screen_general    | screans_gerais/       | idem (ortografia da pasta no repo)            |
 * | logo              | logos/                | idem                                           |
 * | chest_icon        | baus/                 | idem                                           |
 *
 * `card_frame`: reservado — quando houver pasta de molduras, mapear no frontend.
 *
 *
 * ============================================================================
 * IMPORTANTE
 * ============================================================================
 *
 * O método `run()` sempre sincroniza o banco com o catálogo atual.
 *
 * Isso significa:
 * - itens antigos do baú são removidos
 * - novos itens são recriados com base no array `items`
 *
 * Portanto:
 * o array do catálogo é a fonte principal de verdade do loot.
 */
class ChestAndWeeklyPoolSeeder extends Seeder
{
    /**
     * Catálogo estático dos baús e respetivos itens. É o único sítio a editar para mudar loot.
     *
     * @return array<string, array{chest: array<string, mixed>, items: list<array<string, mixed>>}>
     */
    private function catalogoBaús(): array
    {
        $cristalCost = (int) config('game.chests.cristal_basico.cost_cristais', 0);
        $premiumCost = (int) config('game.chests.premium_padrao.cost_moedas', 0);
        $pityEvery = (int) config('game.chests.pity_epic_every', 20);

        return [
            'bau_cosmetico_iniciante' => [
                'chest' => [
                    'name' => 'Baú cosmético iniciante',
                    'description' => 'Contém um dos versos básicos do jogo.',
                    'cost_moedas' => null,
                    'cost_cristais' => null,
                    'available_in_shop' => false,
                    'active' => true,
                    'sort_order' => 0,
                    'pity_epic_every' => null,
                ],
                'items' => [
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_comum', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 0],
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_padrao', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 1],
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_astra_veil', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 2],
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_infernais', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 3],
                    ['asset_category' => 'card_back', 'asset_key' => 'verso_natureza', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 4],
                ],
            ],

            'cristal_basico' => [
                'chest' => [
                    'name' => 'Baú de cristal',
                    'description' => 'Pool fixo: 8 cartas comuns, 1 rara e 1 épica (slugs na BD).',
                    'cost_moedas' => null,
                    'cost_cristais' => $cristalCost ?: null,
                    'available_in_shop' => false,
                    'active' => true,
                    'sort_order' => 10,
                    'pity_epic_every' => null,
                ],
                'items' => [
                    // 8 comuns
                    ['asset_category' => 'card', 'asset_key' => 'cao-vulcanico', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 0],
                    ['asset_category' => 'card', 'asset_key' => 'bruxa-cinzenta', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 1],
                    ['asset_category' => 'card', 'asset_key' => 'morcego-igneo', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 2],
                    ['asset_category' => 'card', 'asset_key' => 'aranha-lunar', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 3],
                    ['asset_category' => 'card', 'asset_key' => 'espirito-da-raiz', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 4],
                    ['asset_category' => 'card', 'asset_key' => 'sapo-toxico', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 5],
                    ['asset_category' => 'card', 'asset_key' => 'drone-sentinela', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 6],
                    ['asset_category' => 'card', 'asset_key' => 'corvo-funerario', 'display_tier' => 'comum', 'drop_weight' => 1, 'sort_order' => 7],
                    // 1 rara
                    ['asset_category' => 'card', 'asset_key' => 'carniceiro-de-brasas', 'display_tier' => 'rara', 'drop_weight' => 1, 'sort_order' => 8],
                    // 1 épica
                    ['asset_category' => 'card', 'asset_key' => 'tita-magmatico', 'display_tier' => 'epica', 'drop_weight' => 1, 'sort_order' => 9],
                ],
            ],

            'premium_padrao' => [
                'chest' => [
                    'name' => 'Baú premium',
                    'description' => 'Edita o array items abaixo para mudar o loot.',
                    'cost_moedas' => $premiumCost ?: null,
                    'cost_cristais' => null,
                    'available_in_shop' => false,
                    'active' => true,
                    'sort_order' => 11,
                    'pity_epic_every' => $pityEvery > 0 ? $pityEvery : null,
                ],
                'items' => [
                    // Exemplo: fundo ui_bg_profile_celestial.png → chave estável para o perfil
                    [
                        'asset_category' => 'card',
                        // Obrigatório: mesmo `slug` que em `cards.slug` (ex.: hífens, não underscores).
                        'asset_key' => 'cao-vulcanico',
                        'display_tier' => 'epica',
                        'drop_weight' => 1,
                        'sort_order' => 0,
                    ],
                    [
                        'asset_category' => 'card',
                        // Obrigatório: mesmo `slug` que em `cards.slug` (ex.: hífens, não underscores).
                        'asset_key' => 'bruxa-cinzenta',
                        'display_tier' => 'epica',
                        'drop_weight' => 1,
                        'sort_order' => 1,
                    ],
                    [
                        'asset_category' => 'card',
                        // Obrigatório: mesmo `slug` que em `cards.slug` (ex.: hífens, não underscores).
                        'asset_key' => 'tita-magmatico',
                        'display_tier' => 'epica',
                        'drop_weight' => 1,
                        'sort_order' => 2,
                    ],
                    [
                        'asset_category' => 'card',
                        // Obrigatório: mesmo `slug` que em `cards.slug` (ex.: hífens, não underscores).
                        'asset_key' => 'morcego-igneo',
                        'display_tier' => 'epica',
                        'drop_weight' => 1,
                        'sort_order' => 3,
                    ],
                    [
                        'asset_category' => 'card_back',
                        // Nome do PNG em imagens/frame_cards/ sem extensão (ex.: verso_da_carta.png).
                        'asset_key' => 'verso_da_carta',
                        'display_tier' => 'epica',
                        'drop_weight' => 1,
                        'sort_order' => 4,
                    ],
                    [
                        'asset_category' => 'profile_bg',
                        'asset_key' => 'ui_bg_profile_infernal',
                        'display_tier' => 'epica',
                        'drop_weight' => 1,
                        'sort_order' => 5,
                    ],
                ],
            ],
        ];
    }

    /**
     * Sincroniza `chests` e `chest_items` com o catálogo, depois configura o pool semanal padrão.
     *
     * Importante: sempre que corres este seeder, o loot de cada baú é substituído pelo que está
     * no array `items` (linhas antigas daquele baú em `chest_items` são apagadas antes de inserir).
     */
    public function run(): void
    {
        $catalogo = $this->catalogoBaús();

        foreach ($catalogo as $slug => $bloco) {
            $chest = Chest::query()->updateOrCreate(
                ['slug' => $slug],
                $bloco['chest']
            );

            // Loot deste baú = apenas o que está em `items` agora (edição manual no catálogo).
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

        // Pool da recompensa semanal: liga o baú iniciante uma única vez se faltar na tabela pivô.
        $pool = WeeklyChestPool::query()->updateOrCreate(
            ['slug' => 'padrao'],
            [
                'name' => 'Recompensa semanal — padrão',
                'active' => true,
            ]
        );

        $bauCosm = Chest::query()->where('slug', 'bau_cosmetico_iniciante')->first();
        if ($bauCosm && ! $pool->chests()->whereKey($bauCosm->id)->exists()) {
            $pool->chests()->attach($bauCosm->id, ['weight' => 100]);
        }
    }
}
