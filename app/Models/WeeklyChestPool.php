<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WeeklyChestPool extends Model
{
    protected $fillable = ['slug', 'name', 'active'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function chests(): BelongsToMany
    {
        return $this->belongsToMany(Chest::class, 'weekly_chest_pool_chests')
            ->withPivot('weight')
            ->withTimestamps();
    }
}
