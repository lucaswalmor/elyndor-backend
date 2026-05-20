<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityDeckCard extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'community_deck_id',
        'card_id',
        'quantidade',
    ];

    public function communityDeck(): BelongsTo
    {
        return $this->belongsTo(CommunityDeck::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
