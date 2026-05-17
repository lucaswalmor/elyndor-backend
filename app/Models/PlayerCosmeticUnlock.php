<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerCosmeticUnlock extends Model
{
    protected $fillable = ['user_id', 'asset_category', 'asset_key'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
