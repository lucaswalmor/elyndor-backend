<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerWeekly extends Model
{
    protected $fillable = [
        'user_id', 'week_start', 'xp_earned', 'claimed_at', 'modal_resgate_vista_em',
        'offers', 'granted_chest_id',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'claimed_at' => 'datetime',
            'modal_resgate_vista_em' => 'datetime',
            'offers' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedChest(): BelongsTo
    {
        return $this->belongsTo(Chest::class, 'granted_chest_id');
    }
}
