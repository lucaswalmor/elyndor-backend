<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChestItem extends Model
{
    protected $fillable = [
        'chest_id', 'asset_category', 'asset_key', 'display_tier', 'drop_weight', 'sort_order',
    ];

    public function chest(): BelongsTo
    {
        return $this->belongsTo(Chest::class);
    }
}
