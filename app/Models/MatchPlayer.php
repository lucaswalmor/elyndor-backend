<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPlayer extends Model
{
    protected $fillable = [
        'match_id', 'user_id', 'deck_id', 'player_slot', 'vida_inicial', 'vida_final',
        'is_bot', 'conectado', 'desconectado_em', 'reconectado_em',
    ];

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'conectado' => 'boolean',
            'desconectado_em' => 'datetime',
            'reconectado_em' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
