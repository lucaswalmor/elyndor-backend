<?php

namespace App\Events;

use App\Models\GameMatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TurnChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GameMatch $match,
        public int $jogadorDaVez,
        public bool $timeout = false,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('match.'.$this->match->id)];
    }

    public function broadcastAs(): string
    {
        return 'TurnChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'turno' => $this->match->turno,
            'jogador_da_vez' => $this->jogadorDaVez,
            'timeout' => $this->timeout,
            'turno_deadline_em' => $this->match->turno_deadline_em?->toIso8601String(),
        ];
    }
}
