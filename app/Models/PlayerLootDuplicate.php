<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerLootDuplicate extends Model
{
    protected $table = 'player_loot_duplicates';

    protected $fillable = [
        'user_id',
        'stack_key',
        'card_id',
        'asset_category',
        'asset_key',
        'quantity',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public static function stackKeyForCard(int $cardId): string
    {
        return 'c:'.$cardId;
    }

    public static function stackKeyForCosmetic(string $category, string $key): string
    {
        return 'x:'.$category.':'.$key;
    }

    public static function addStack(
        int $userId,
        string $stackKey,
        ?int $cardId,
        ?string $assetCategory,
        ?string $assetKey,
        int $delta = 1,
    ): void {
        $row = static::query()->firstOrNew([
            'user_id' => $userId,
            'stack_key' => $stackKey,
        ]);

        if (! $row->exists) {
            $row->card_id = $cardId;
            $row->asset_category = $assetCategory;
            $row->asset_key = $assetKey;
            $row->quantity = $delta;
        } else {
            $row->quantity = (int) $row->quantity + $delta;
        }

        $row->save();
    }
}
