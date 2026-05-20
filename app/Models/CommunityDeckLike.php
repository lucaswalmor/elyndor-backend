<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityDeckLike extends Model
{
    protected $fillable = [
        'user_id',
        'community_deck_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function communityDeck(): BelongsTo
    {
        return $this->belongsTo(CommunityDeck::class);
    }
}
