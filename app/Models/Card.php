<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    protected $fillable = [
        'nome', 'slug', 'descricao', 'faccao', 'classe', 'raridade', 'tipo',
        'custo', 'ataque', 'vida', 'imagem', 'imagem_path', 'ativo', 'colecionavel',
        'versao_balanceamento',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'colecionavel' => 'boolean',
        ];
    }

    public function skills(): HasMany
    {
        return $this->hasMany(CardSkill::class);
    }
}
