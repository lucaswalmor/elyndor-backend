<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityDeck extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'source_deck_id',
        'nome',
        'descricao',
        'linhagem_principal',
        'game_version',
        'ely_code',
        'is_streamer_deck',
        'tags',
        'likes_count',
        'views_count',
        'copies_count',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_streamer_deck' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceDeck(): BelongsTo
    {
        return $this->belongsTo(Deck::class, 'source_deck_id');
    }

    public function deckCards(): HasMany
    {
        return $this->hasMany(CommunityDeckCard::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommunityDeckLike::class);
    }
}
