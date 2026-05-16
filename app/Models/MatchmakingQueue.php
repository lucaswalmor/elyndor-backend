<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchmakingQueue extends Model
{
    public $timestamps = false;

    protected $table = 'matchmaking_queue';

    protected $fillable = [
        'user_id', 'modo', 'deck_id', 'nivel', 'pontos_ranked', 'divisao', 'entrou_na_fila_em',
    ];

    protected function casts(): array
    {
        return ['entrou_na_fila_em' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deck(): BelongsTo
    {
        return $this->belongsTo(Deck::class);
    }
}
