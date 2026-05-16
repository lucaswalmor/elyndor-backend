<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerLevel extends Model
{
    protected $fillable = ['user_id', 'nivel', 'xp_atual'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function xpParaProximoNivel(): int
    {
        $base = config('game.progression.xp_level_formula.base');
        $per = config('game.progression.xp_level_formula.per_level');

        return $base + ($this->nivel * $per);
    }
}
