<?php

namespace App\Events;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchFound implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GameMatch $match,
        public User $recipient,
        public User $opponent,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('matchmaking.'.$this->recipient->id)];
    }

    public function broadcastAs(): string
    {
        return 'MatchFound';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'oponente' => [
                'nome' => $this->opponent->nickname,
                'divisao' => 'ferro',
                'pontos' => 0,
            ],
            'segundos_para_iniciar' => 3,
        ];
    }
}
