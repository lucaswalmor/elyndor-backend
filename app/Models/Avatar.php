<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Avatar extends Model
{
    protected $fillable = ['slug', 'label', 'sort_order', 'is_starter'];

    protected function casts(): array
    {
        return ['is_starter' => 'boolean'];
    }

    public function usersWithUnlock(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'player_avatars')->withTimestamps();
    }
}
