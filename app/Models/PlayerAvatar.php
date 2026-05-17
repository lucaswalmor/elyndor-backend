<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerAvatar extends Model
{
    protected $fillable = ['user_id', 'avatar_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Avatar::class);
    }
}
