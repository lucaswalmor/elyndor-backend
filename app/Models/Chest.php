<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chest extends Model
{
    protected $fillable = [
        'slug', 'name', 'description', 'cost_moedas', 'cost_cristais',
        'available_in_shop', 'active', 'sort_order', 'pity_epic_every',
    ];

    protected function casts(): array
    {
        return [
            'available_in_shop' => 'boolean',
            'active' => 'boolean',
            'pity_epic_every' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChestItem::class)->orderBy('sort_order');
    }
}
