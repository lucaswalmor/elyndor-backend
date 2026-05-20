<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityDeckView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'community_deck_id',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function communityDeck(): BelongsTo
    {
        return $this->belongsTo(CommunityDeck::class);
    }
}
