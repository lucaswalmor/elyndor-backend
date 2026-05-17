<?php

namespace App\Events;

use App\Models\GameMatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActionProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GameMatch $match,
        public string $acao,
        public int $playerSlot,
        public array $animacoes,
        public array $actionPayload = [],
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('match.'.$this->match->id)];
    }

    public function broadcastAs(): string
    {
        return 'ActionProcessed';
    }

    public function broadcastWith(): array
    {
        return [
            'acao' => $this->acao,
            'player_id' => $this->playerSlot,
            'animacoes' => $this->animacoes,
            'estado_atualizado' => $this->match->estado,
            'action_payload' => $this->actionPayload,
        ];
    }
}
