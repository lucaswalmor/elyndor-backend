<?php

namespace App\Events;

use App\Models\GameMatch;
use App\Models\User;
use App\Services\Ranked\RankedService;
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
        $ranked = app(RankedService::class);

        $deadline = $this->match->accept_deadline_at;
        $segundos = $deadline
            ? max(0, $deadline->getTimestamp() - now()->getTimestamp())
            : (int) config('game.match.accept_offer_seconds', 15);

        return [
            'match_id' => $this->match->id,
            'modo' => $this->match->modo,
            'accept_deadline_at' => $deadline?->toIso8601String(),
            'oponente' => $ranked->dadosOponenteParaOferta($this->recipient, $this->opponent, $this->match),
            'segundos_para_aceitar' => $segundos,
        ];
    }
}
