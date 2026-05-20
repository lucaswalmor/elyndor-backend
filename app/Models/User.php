<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'nickname', 'email', 'password', 'moedas', 'cristais', 'card_back_slug', 'profile_bg_slug', 'match_board_slug', 'avatar_id', 'ranked_points', 'ranked_wins', 'ranked_losses', 'total_matches_played', 'match_mode_counts', 'playtime_seconds', 'registration_device_id', 'is_content_creator', 'streamer_invite_token', 'streamer_invite_claim'])]
#[Hidden(['name', 'email', 'password', 'remember_token'])]
class User extends Authenticatable implements CanResetPasswordContract
{
    /** @use HasFactory<UserFactory> */
    use CanResetPassword;
    use HasApiTokens, HasFactory, Notifiable;

    public function playerLevel(): HasOne
    {
        return $this->hasOne(PlayerLevel::class);
    }

    public function decks(): HasMany
    {
        return $this->hasMany(Deck::class);
    }

    public function playerCards(): HasMany
    {
        return $this->hasMany(PlayerCard::class);
    }

    public function lootDuplicates(): HasMany
    {
        return $this->hasMany(PlayerLootDuplicate::class);
    }

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Avatar::class, 'avatar_id');
    }

    public function unlockedAvatars(): BelongsToMany
    {
        return $this->belongsToMany(Avatar::class, 'player_avatars')->withTimestamps();
    }

    public function outgoingFriendRequests(): HasMany
    {
        return $this->hasMany(FriendRequest::class, 'requester_id');
    }

    public function incomingFriendRequests(): HasMany
    {
        return $this->hasMany(FriendRequest::class, 'addressee_id');
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
            'last_daily_win_bonus_date' => 'date',
            'match_mode_counts' => 'array',
            'is_bot' => 'boolean',
            'is_content_creator' => 'boolean',
        ];
    }

    public function streamerProfile(): HasOne
    {
        return $this->hasOne(StreamerProfile::class);
    }

    public function communityDecks(): HasMany
    {
        return $this->hasMany(CommunityDeck::class);
    }
}
