<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RankedMatchOutcome extends Model
{
    protected $fillable = [
        'match_id', 'user_id', 'venceu', 'delta', 'pontos_antes', 'pontos_depois', 'divisao_oponente',
    ];

    protected function casts(): array
    {
        return ['venceu' => 'boolean'];
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
