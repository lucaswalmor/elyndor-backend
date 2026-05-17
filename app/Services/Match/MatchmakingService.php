<?php

namespace App\Services\Match;

use App\Events\MatchFound;
use App\Events\MatchStarted;
use App\Enums\MatchStatus;
use App\Models\GameMatch;
use App\Models\MatchmakingQueue;
use App\Models\MatchPlayer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Services\Deck\DeckService;
use App\Services\Game\MatchInitializer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MatchmakingService
{
    public function __construct(
        private MatchInitializer $initializer,
        private DeckService $deckService,
    ) {}

    public function join(User $user, string $modo, int $deckId): array
    {
        $deck = $this->deckService->assertPlayable($user, $deckId);

        if ($modo !== 'normal') {
            throw new InvalidArgumentException('Apenas modo normal na Fase A');
        }

        MatchmakingQueue::where('user_id', $user->id)->delete();

        MatchmakingQueue::create([
            'user_id' => $user->id,
            'modo' => $modo,
            'deck_id' => $deckId,
            'nivel' => $user->playerLevel?->nivel ?? 1,
            'entrou_na_fila_em' => now(),
        ]);

        $paired = $this->tryPair($modo);

        if ($paired) {
            return [
                'status' => 'partida_encontrada',
                'match_id' => $paired,
            ];
        }

        return [
            'status' => 'aguardando',
            'posicao_na_fila' => MatchmakingQueue::where('modo', $modo)->count(),
            'tempo_estimado_segundos' => 15,
        ];
    }

    public function leave(User $user): void
    {
        MatchmakingQueue::where('user_id', $user->id)->delete();
    }

    public function status(User $user): array
    {
        $entry = MatchmakingQueue::where('user_id', $user->id)->first();
        if (! $entry) {
            // Usuário saiu da fila — verifica se já tem partida ativa (pode ter perdido evento WS)
            $player = MatchPlayer::where('user_id', $user->id)
                ->whereHas('match', fn ($q) => $q->whereIn('status', [
                    MatchStatus::Aguardando->value,
                    MatchStatus::EmAndamento->value,
                ]))
                ->latest()
                ->first();

            if ($player) {
                return ['status' => 'partida_encontrada', 'match_id' => $player->match_id];
            }

            return ['status' => 'fora_da_fila'];
        }

        $paired = $this->tryPair($entry->modo);
        if ($paired) {
            return ['status' => 'partida_encontrada', 'match_id' => $paired];
        }

        return [
            'status' => 'aguardando',
            'tempo_na_fila_segundos' => now()->diffInSeconds($entry->entrou_na_fila_em),
        ];
    }

    public function tryPair(string $modo): ?int
    {
        $waiting = MatchmakingQueue::where('modo', $modo)->orderBy('entrou_na_fila_em')->limit(2)->get();
        if ($waiting->count() < 2) {
            return null;
        }

        return DB::transaction(function () use ($waiting, $modo) {
            $a = $waiting[0];
            $b = $waiting[1];

            MatchmakingQueue::whereIn('user_id', [$a->user_id, $b->user_id])->delete();

            $match = GameMatch::create([
                'modo' => $modo,
                'status' => MatchStatus::Aguardando,
            ]);

            $p1 = MatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $a->user_id,
                'deck_id' => $a->deck_id,
                'player_slot' => 1,
            ]);
            $p2 = MatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $b->user_id,
                'deck_id' => $b->deck_id,
                'player_slot' => 2,
            ]);

            $this->initializer->start($match, $p1, $p2);
            $match->refresh();

            $userA = User::find($a->user_id);
            $userB = User::find($b->user_id);

            broadcast(new MatchFound($match, $userB, $userA));
            broadcast(new MatchFound($match, $userA, $userB));
            broadcast(new MatchStarted($match, $userA, 1));
            broadcast(new MatchStarted($match, $userB, 2));

            return $match->id;
        });
    }
}
