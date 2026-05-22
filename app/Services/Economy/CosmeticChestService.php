<?php

namespace App\Services\Economy;

use App\Models\Card;
use App\Models\Chest;
use App\Models\ChestItem;
use App\Models\PlayerChestStack;
use App\Models\PlayerCosmeticUnlock;
use App\Models\PlayerLootDuplicate;
use App\Models\User;
use App\Services\Collection\PlayerCollectionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Abertura de baús a partir do inventário: loot sempre definido em chest_items.
 *
 * - asset_category = card, asset_key = slug da carta (tabela cards.slug)
 * - asset_category = card_back | … — cosmético em player_cosmetic_unlocks
 */
class CosmeticChestService
{
    public function __construct(
        private PlayerCollectionService $collection,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function openOne(User $user, int $chestId): array
    {
        return DB::transaction(function () use ($user, $chestId) {
            $chest = Chest::query()
                ->where('id', $chestId)
                ->where('active', true)
                ->first();

            if (! $chest) {
                throw new InvalidArgumentException('Baú indisponível ou inativo.');
            }

            $userId = (int) $user->id;

            $stack = PlayerChestStack::query()
                ->where('user_id', $userId)
                ->where('chest_id', $chestId)
                ->lockForUpdate()
                ->first();

            if (! $stack || (int) $stack->quantity < 1) {
                throw new InvalidArgumentException('Não tens este baú no inventário.');
            }

            $items = $chest->items()->get();
            if ($items->isEmpty()) {
                throw new InvalidArgumentException('Este baú está vazio na configuração (adiciona linhas em chest_items).');
            }

            $u = User::query()->lockForUpdate()->findOrFail($userId);

            $candidates = $items;
            if ($chest->pity_epic_every !== null) {
                $pityEvery = max(1, (int) $chest->pity_epic_every);
                $pity = (int) $u->premium_chest_pity;
                if ($pity >= $pityEvery - 1) {
                    $epicPlus = $this->filterPityEpicPlusItems($items);
                    if ($epicPlus->isNotEmpty()) {
                        $candidates = $epicPlus;
                    }
                }
            }

            $picked = $this->pickWeightedItem($candidates, $chest);
            if (! $picked instanceof ChestItem) {
                throw new InvalidArgumentException('Falha ao sortear recompensa.');
            }

            $stack->quantity = (int) $stack->quantity - 1;
            $stack->save();

            if ($picked->asset_category === 'card') {
                return $this->finalizeCardReward($chest, $u, $picked, $stack);
            }

            return $this->finalizeCosmeticReward($chest, $u, $picked, $stack);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function finalizeCardReward(
        Chest $chest,
        User $u,
        ChestItem $picked,
        PlayerChestStack $stack,
    ): array {
        $card = Card::query()
            ->where('slug', $picked->asset_key)
            ->where('ativo', true)
            ->where('colecionavel', true)
            ->first();

        if (! $card) {
            throw new InvalidArgumentException('Carta configurada no baú não existe ou não é colecionável: '.$picked->asset_key);
        }

        if ($chest->pity_epic_every !== null) {
            if (in_array($card->raridade, ['epica', 'lendaria'], true)) {
                $u->premium_chest_pity = 0;
            } else {
                $u->premium_chest_pity = min(255, (int) $u->premium_chest_pity + 1);
            }
            $u->save();
        }

        $gain = $this->collection->applyCardGain($u->fresh(), $card);
        $u->refresh();

        return [
            'chest' => [
                'id' => $chest->id,
                'slug' => $chest->slug,
                'name' => $chest->name,
            ],
            'reward_type' => 'card',
            'card' => [
                'id' => $card->id,
                'nome' => $card->nome,
                'raridade' => $card->raridade,
            ],
            'duplicate_cristais' => $gain['cristais'],
            'duplicate_stashed' => $gain['stashed_duplicate'],
            'pity_after' => $chest->pity_epic_every !== null ? (int) $u->premium_chest_pity : null,
            'remaining_quantity' => (int) $stack->quantity,
            'balance' => [
                'cristais' => (int) $u->cristais,
                'moedas' => (int) $u->moedas,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function finalizeCosmeticReward(
        Chest $chest,
        User $u,
        ChestItem $picked,
        PlayerChestStack $stack,
    ): array {
        $userId = (int) $u->id;

        $alreadyOwned = PlayerCosmeticUnlock::query()
            ->where('user_id', $userId)
            ->where('asset_category', $picked->asset_category)
            ->where('asset_key', $picked->asset_key)
            ->exists();

        if (! $alreadyOwned) {
            PlayerCosmeticUnlock::query()->create([
                'user_id' => $userId,
                'asset_category' => $picked->asset_category,
                'asset_key' => $picked->asset_key,
            ]);
        } else {
            PlayerLootDuplicate::addStack(
                $userId,
                PlayerLootDuplicate::stackKeyForCosmetic($picked->asset_category, $picked->asset_key),
                null,
                $picked->asset_category,
                $picked->asset_key,
                1,
            );
        }

        if ($chest->pity_epic_every !== null) {
            $t = strtolower((string) $picked->display_tier);
            $isHigh = in_array($t, ['epica', 'lendaria'], true)
                || str_contains($t, 'epic')
                || str_contains($t, 'lend');
            if ($isHigh) {
                $u->premium_chest_pity = 0;
            } else {
                $u->premium_chest_pity = min(255, (int) $u->premium_chest_pity + 1);
            }
            $u->save();
        }

        return [
            'chest' => [
                'id' => $chest->id,
                'slug' => $chest->slug,
                'name' => $chest->name,
            ],
            'reward_type' => 'cosmetic',
            'won' => [
                'asset_category' => $picked->asset_category,
                'asset_key' => $picked->asset_key,
                'display_tier' => $picked->display_tier,
                'already_owned' => $alreadyOwned,
            ],
            'duplicate_stashed' => $alreadyOwned,
            'remaining_quantity' => (int) $stack->quantity,
            'pity_after' => $chest->pity_epic_every !== null ? (int) $u->fresh()->premium_chest_pity : null,
        ];
    }

    /**
     * Pré-visualização: só chest_items (sem expor drop_weight). Enriquece cartas com dados da tabela cards.
     *
     * @return array{chest: array, items: list<array<string, mixed>>}
     */
    public function previewPool(string $slug): array
    {
        $chest = Chest::query()
            ->where('slug', $slug)
            ->where('active', true)
            ->first();

        if (! $chest) {
            throw new InvalidArgumentException('Baú não encontrado.');
        }

        $rows = $chest->items()
            ->orderBy('sort_order')
            ->get(['asset_category', 'asset_key', 'display_tier', 'sort_order']);

        $cardSlugs = $rows->where('asset_category', 'card')->pluck('asset_key')->unique()->filter()->values()->all();
        $cardsBySlug = $cardSlugs === []
            ? collect()
            : Card::query()->whereIn('slug', $cardSlugs)->get()->keyBy('slug');

        $items = $rows->map(function (ChestItem $row) use ($cardsBySlug) {
            $out = [
                'asset_category' => $row->asset_category,
                'asset_key' => $row->asset_key,
                'display_tier' => $row->display_tier,
                'sort_order' => (int) $row->sort_order,
            ];
            if ($row->asset_category === 'card' && ($c = $cardsBySlug->get($row->asset_key))) {
                $out['card'] = [
                    'id' => $c->id,
                    'nome' => $c->nome,
                    'raridade' => $c->raridade,
                    'imagem_path' => $c->imagem_path,
                ];
            }
            return $out;
        })->values()->all();

        return [
            'chest' => [
                'id' => $chest->id,
                'slug' => $chest->slug,
                'name' => $chest->name,
                'description' => $chest->description,
            ],
            'items' => $items,
        ];
    }

    /**
     * Itens elegíveis quando o pity força épico+: cartas épica/lendária + qualquer linha com display_tier épico/lendário.
     *
     * @param  Collection<int, ChestItem>  $items
     * @return Collection<int, ChestItem>
     */
    private function filterPityEpicPlusItems(Collection $items): Collection
    {
        $cardSlugs = $items->where('asset_category', 'card')->pluck('asset_key')->unique()->all();
        $epicCardKeys = $cardSlugs === []
            ? []
            : Card::query()
                ->whereIn('slug', $cardSlugs)
                ->whereIn('raridade', ['epica', 'lendaria'])
                ->pluck('slug')
                ->all();

        return $items->filter(function (ChestItem $i) use ($epicCardKeys) {
            if ($i->asset_category === 'card') {
                return in_array($i->asset_key, $epicCardKeys, true);
            }

            $t = strtolower((string) $i->display_tier);

            return in_array($t, ['epica', 'lendaria'], true)
                || str_contains($t, 'epic')
                || str_contains($t, 'lend');
        })->values();
    }

    /**
     * @param  Collection<int, ChestItem>  $items
     */
    private function pickWeightedItem(Collection $items, ?Chest $chest = null): ?ChestItem
    {
        $tierWeights = $this->tierWeightsForChest($chest);
        if ($tierWeights !== null && $items->isNotEmpty()) {
            $byTier = $items->groupBy(fn (ChestItem $item) => $this->normalizeDisplayTier($item->display_tier));
            $chosenTier = $this->pickTierByWeights($tierWeights, $byTier->keys()->all());
            $pool = $byTier->get($chosenTier);
            if ($pool !== null && $pool->isNotEmpty()) {
                return $this->pickWeightedItemByDropWeight($pool);
            }
        }

        return $this->pickWeightedItemByDropWeight($items);
    }

    /**
     * @return array<string, int>|null
     */
    private function tierWeightsForChest(?Chest $chest): ?array
    {
        if (! $chest) {
            return null;
        }

        $slug = (string) $chest->slug;

        if ($slug === 'chest_cristal_basico') {
            return config('game.chests.spin_weights_cristal_basico');
        }

        if ($slug === 'bau_recompensa_semanal') {
            return config('game.chests.weekly_offer_weights');
        }

        if (str_starts_with($slug, 'bau_cartas_')) {
            return config('game.chests.spin_weights_linhagem');
        }

        $usesTierRoulette = [
            'premium_padrao',
            'bau_cosmetico_fundos',
            'bau_cosmetico_tabuleiros',
            'bau_cosmetico_versos',
        ];

        if (in_array($slug, $usesTierRoulette, true)) {
            return config('game.chests.spin_weights_premium');
        }

        return null;
    }

    private function normalizeDisplayTier(?string $tier): string
    {
        $tier = strtolower((string) $tier);

        return in_array($tier, ['comum', 'rara', 'epica', 'lendaria'], true) ? $tier : 'comum';
    }

    /**
     * @param  array<string, int>  $tierWeights
     * @param  list<string>  $availableTiers
     */
    private function pickTierByWeights(array $tierWeights, array $availableTiers): string
    {
        $order = ['comum', 'rara', 'epica', 'lendaria'];
        $sum = 0;
        foreach ($order as $tier) {
            if (! in_array($tier, $availableTiers, true)) {
                continue;
            }
            $sum += max(0, (int) ($tierWeights[$tier] ?? 0));
        }
        if ($sum <= 0) {
            return $availableTiers[0] ?? 'comum';
        }

        $roll = random_int(0, $sum - 1);
        $acc = 0;
        foreach ($order as $tier) {
            if (! in_array($tier, $availableTiers, true)) {
                continue;
            }
            $acc += max(0, (int) ($tierWeights[$tier] ?? 0));
            if ($roll < $acc) {
                return $tier;
            }
        }

        return $availableTiers[array_key_last($availableTiers)] ?? 'comum';
    }

    /**
     * @param  Collection<int, ChestItem>  $items
     */
    private function pickWeightedItemByDropWeight(Collection $items): ?ChestItem
    {
        $sum = 0;
        foreach ($items as $item) {
            $sum += max(0, (int) $item->drop_weight);
        }
        if ($sum <= 0) {
            return $items->first();
        }

        $roll = random_int(0, $sum - 1);
        $acc = 0;
        foreach ($items as $item) {
            $w = max(0, (int) $item->drop_weight);
            $acc += $w;
            if ($roll < $acc) {
                return $item;
            }
        }

        return $items->last();
    }
}
