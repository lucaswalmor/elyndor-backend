<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'nickname', 'email', 'password', 'moedas', 'cristais', 'card_back_slug'])]
#[Hidden(['name', 'email', 'password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function playerLevel(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlayerLevel::class);
    }

    public function decks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Deck::class);
    }

    public function playerCards(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PlayerCard::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
