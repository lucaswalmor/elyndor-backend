<?php

namespace App\Models;

use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'modo', 'status', 'accept_deadline_at', 'vencedor_id', 'turno', 'jogador_da_vez', 'estado',
        'turno_deadline_em', 'iniciada_em', 'finalizada_em',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'array',
            'status' => MatchStatus::class,
            'accept_deadline_at' => 'datetime',
            'turno_deadline_em' => 'datetime',
            'iniciada_em' => 'datetime',
            'finalizada_em' => 'datetime',
        ];
    }

    public function players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MatchLog::class, 'match_id');
    }

    public function vencedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vencedor_id');
    }
}
