<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardSkill extends Model
{
    protected $fillable = ['card_id', 'nome', 'tipo', 'gatilho', 'efeito'];

    protected function casts(): array
    {
        return ['efeito' => 'array'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
