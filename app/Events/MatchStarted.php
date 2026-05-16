<?php

namespace App\Events;

use App\Models\GameMatch;
use App\Models\User;
use App\Services\Game\MatchViewBuilder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GameMatch $match,
        public User $recipient,
        public int $playerSlot,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('matchmaking.'.$this->recipient->id),
            new PrivateChannel('match.'.$this->match->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MatchStarted';
    }

    public function broadcastWith(): array
    {
        $view = app(MatchViewBuilder::class)->forUser($this->match->load('players.user'), $this->recipient);
        $opp = $this->match->players->firstWhere('player_slot', $this->playerSlot === 1 ? 2 : 1);

        return [
            'match_id' => $this->match->id,
            'seu_player_id' => $this->playerSlot,
            'oponente' => [
                'nome' => $opp?->user?->nickname,
                'divisao' => 'ferro',
                'pontos' => 0,
                'card_back_slug' => $opp?->user?->card_back_slug ?? 'padrao',
            ],
            'voce_comeca' => $this->playerSlot === 1,
            'estado_inicial' => $view,
        ];
    }
}
