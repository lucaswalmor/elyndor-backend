<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchLog extends Model
{
    protected $fillable = [
        'match_id', 'turno', 'user_id', 'acao', 'card_id', 'card_alvo_id',
        'dano_causado', 'vida_antes', 'vida_depois', 'efeito_tipo', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}
