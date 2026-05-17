<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerChestStack extends Model
{
    protected $table = 'player_chest_stacks';

    protected $fillable = ['user_id', 'chest_id', 'quantity'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chest(): BelongsTo
    {
        return $this->belongsTo(Chest::class);
    }
}
