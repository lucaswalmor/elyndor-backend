<?php

namespace App\Events;

use App\Models\GameMatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchFinished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GameMatch $match,
        public int $vencedorUserId,
        public string $motivo,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('match.'.$this->match->id)];
    }

    public function broadcastAs(): string
    {
        return 'MatchFinished';
    }

    public function broadcastWith(): array
    {
        return [
            'vencedor_id' => $this->vencedorUserId,
            'motivo' => $this->motivo,
            'xp_ganho' => 0,
            'moedas_ganhas' => 0,
        ];
    }
}
