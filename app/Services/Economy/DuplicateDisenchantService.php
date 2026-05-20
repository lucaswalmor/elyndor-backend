<?php

namespace App\Services\Economy;

use App\Models\Card;
use App\Models\ChestItem;
use App\Models\PlayerLootDuplicate;

/**
 * Valores de desfragmentação por raridade (Fase 11).
 */
class DuplicateDisenchantService
{
    /**
     * @return array{gain_type: string, gain_amount: int}
     */
    public function resolve(PlayerLootDuplicate $row): array
    {
        if ($row->card_id) {
            $card = Card::query()->find($row->card_id);
            $raridade = $card?->raridade ?? 'comum';
            $map = config('game.progression.duplicate_cristais', []);
            $gain = (int) ($map[$raridade] ?? $map['comum'] ?? 0);

            return ['gain_type' => 'cristais', 'gain_amount' => max(0, $gain)];
        }

        $tier = $this->resolveCosmeticTier($row);
        $map = config('game.progression.duplicate_moedas', []);
        $gain = (int) ($map[$tier] ?? $map['comum'] ?? 0);

        return ['gain_type' => 'moedas', 'gain_amount' => max(0, $gain)];
    }

    private function resolveCosmeticTier(PlayerLootDuplicate $row): string
    {
        $category = (string) ($row->asset_category ?? '');
        $key = (string) ($row->asset_key ?? '');
        if ($category === '' || $key === '') {
            return 'comum';
        }

        $item = ChestItem::query()
            ->where('asset_category', $category)
            ->where('asset_key', $key)
            ->orderByDesc('id')
            ->first();

        $tier = strtolower((string) ($item?->display_tier ?? 'comum'));

        return in_array($tier, ['comum', 'rara', 'epica', 'lendaria'], true) ? $tier : 'comum';
    }
}
