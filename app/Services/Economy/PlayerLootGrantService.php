<?php

namespace App\Services\Economy;

use App\Models\Card;
use App\Models\PlayerCosmeticUnlock;
use App\Models\PlayerLootDuplicate;
use App\Models\User;
use App\Services\Collection\PlayerCollectionService;
use InvalidArgumentException;

/**
 * Concede recompensas de loot manualmente (seeders, suporte, streamers)
 * com a mesma persistência que abrir um baú em CosmeticChestService.
 */
class PlayerLootGrantService
{
    public function __construct(
        private PlayerCollectionService $collection,
    ) {}

    /**
     * Carta por slug (asset_key dos baús).
     *
     * @return array{status: string, card_id?: int, card_nome?: string, stashed_duplicate: bool}
     */
    public function concederCartaPorSlug(User $user, string $slug): array
    {
        $slug = trim($slug);
        if ($slug === '') {
            throw new InvalidArgumentException('Slug da carta vazio.');
        }

        $carta = Card::query()
            ->where('slug', $slug)
            ->where('ativo', true)
            ->where('colecionavel', true)
            ->first();

        if (! $carta) {
            throw new InvalidArgumentException("Carta não encontrada ou não colecionável: {$slug}");
        }

        $ganho = $this->collection->applyCardGain($user->fresh(), $carta);

        return [
            'status' => $ganho['stashed_duplicate'] ? 'carta_duplicada_repetidas' : 'carta_concedida',
            'card_id' => $carta->id,
            'card_nome' => $carta->nome,
            'stashed_duplicate' => (bool) $ganho['stashed_duplicate'],
        ];
    }

    /**
     * Cosmético (card_back, profile_bg, match_board, …).
     *
     * @return array{status: string, asset_category: string, asset_key: string}
     */
    public function concederCosmetico(User $user, string $categoria, string $assetKey): array
    {
        $categoria = trim($categoria);
        $assetKey = trim($assetKey);
        if ($categoria === '' || $assetKey === '') {
            throw new InvalidArgumentException('Categoria ou asset_key do cosmético vazio.');
        }

        $userId = (int) $user->id;

        $jaExistia = PlayerCosmeticUnlock::query()
            ->where('user_id', $userId)
            ->where('asset_category', $categoria)
            ->where('asset_key', $assetKey)
            ->exists();

        if (! $jaExistia) {
            PlayerCosmeticUnlock::query()->create([
                'user_id' => $userId,
                'asset_category' => $categoria,
                'asset_key' => $assetKey,
            ]);

            return [
                'status' => 'cosmetico_novo',
                'asset_category' => $categoria,
                'asset_key' => $assetKey,
            ];
        }

        PlayerLootDuplicate::addStack(
            $userId,
            PlayerLootDuplicate::stackKeyForCosmetic($categoria, $assetKey),
            null,
            $categoria,
            $assetKey,
            1,
        );

        return [
            'status' => 'cosmetico_duplicado_repetidas',
            'asset_category' => $categoria,
            'asset_key' => $assetKey,
        ];
    }
}
