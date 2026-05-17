<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChestShopPurchase extends Model
{
    protected $fillable = [
        'user_id',
        'chest_id',
        'quantity',
        'currency',
        'unit_price',
        'total_paid',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'total_paid' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chest(): BelongsTo
    {
        return $this->belongsTo(Chest::class, 'chest_id');
    }
}
