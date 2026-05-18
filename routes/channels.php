<?php

use App\Models\GameMatch;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('matchmaking.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('match.{matchId}', function ($user, $matchId) {
    return GameMatch::where('id', $matchId)
        ->whereHas('players', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});

Broadcast::channel('social.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
