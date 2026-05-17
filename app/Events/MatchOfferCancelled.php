<?php

namespace App\Events;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchOfferCancelled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GameMatch $match,
        public User $recipient,
        public string $reason,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('matchmaking.'.$this->recipient->id)];
    }

    public function broadcastAs(): string
    {
        return 'MatchOfferCancelled';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'reason' => $this->reason,
        ];
    }
}
