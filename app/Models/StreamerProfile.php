<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'youtube_url',
        'instagram_url',
        'whatsapp_group_url',
        'twitch_url',
        'other_url',
        'bio',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
