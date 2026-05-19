<?php

namespace App\Events;

use App\Models\GameMatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GameMatch $match,
        public int $senderId,
        public string $texto,
        public string $time,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('match.'.$this->match->id)];
    }

    public function broadcastAs(): string
    {
        return 'MatchMessage';
    }

    public function broadcastWith(): array
    {
        return [
            'sender_id' => $this->senderId,
            'texto' => $this->texto,
            'time' => $this->time,
        ];
    }
}
