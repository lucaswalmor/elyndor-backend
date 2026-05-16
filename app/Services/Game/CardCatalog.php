<?php

namespace App\Services\Game;

use App\Models\Card;
use Illuminate\Support\Collection;

class CardCatalog
{
    private static ?Collection $cache = null;

    public static function all(): Collection
    {
        if (self::$cache === null) {
            self::$cache = Card::with('skills')->get()->keyBy('id');
        }

        return self::$cache;
    }

    public static function get(int $cardId): ?Card
    {
        return self::all()->get($cardId);
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
