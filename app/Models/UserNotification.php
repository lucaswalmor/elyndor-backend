<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    public const TYPE_FRIEND_REQUEST = 'friend_request';

    public const TYPE_FRIEND_ACCEPTED = 'friend_accepted';

    public const TYPE_PRIVATE_MESSAGE = 'private_message';

    protected $fillable = [
        'user_id',
        'type',
        'payload',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
