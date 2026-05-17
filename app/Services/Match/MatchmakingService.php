<?php

namespace App\Services\Match;

use App\Events\MatchFound;
use App\Events\MatchStarted;
use App\Enums\MatchStatus;
use App\Models\GameMatch;
use App\Models\MatchmakingQueue;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Services\AntiAbuse\AntiAbuseService;
use App\Services\Deck\DeckService;
use App\Services\Game\MatchInitializer;
use App\Services\Ranked\RankedService;

class MatchmakingService
{
    public function __construct(
        private MatchInitializer $initializer,
        private DeckService $deckService,
        private RankedService $ranked,
        private AntiAbuseService $antiAbuse,
    ) {}

    /**
     * @param  array{device_id?: string|null, client_type?: string|null}  $meta
     */
    public function join(User $user, string $modo, int $deckId, array $meta = []): array
    {
        $this->deckService->assertPlayable($user, $deckId);
        $user->loadMissing('playerLevel');

        if ($modo === 'ranqueada') {
            if (! $this->ranked->userMeetsRankedLevel($user)) {
                throw new InvalidArgumentException(
                    'Ranqueada disponível a partir do nível '.$this->ranked->minLevel().'.'
                );
            }
        } elseif ($modo !== 'normal') {
            throw new InvalidArgumentException('Modo de partida inválido.');
        }

        MatchmakingQueue::where('user_id', $user->id)->delete();

        $ip = request()?->ip();
        $deviceId = $meta['device_id'] ?? null;

        $row = [
            'user_id' => $user->id,
            'modo' => $modo,
            'deck_id' => $deckId,
            'nivel' => $user->playerLevel?->nivel ?? 1,
            'entrou_na_fila_em' => now(),
            'ip_address' => $ip,
            'device_id' => $deviceId ? substr($deviceId, 0, 80) : null,
        ];

        if ($modo === 'ranqueada') {
            $row['pontos_ranked'] = $user->ranked_points ?? 0;
            $row['divisao'] = $this->ranked->divisionKeyForPoints((int) ($user->ranked_points ?? 0));
        } else {
            $row['pontos_ranked'] = 0;
            $row['divisao'] = null;
        }

        MatchmakingQueue::create($row);

        $paired = $modo === 'ranqueada' ? $this->tryPairRanked() : $this->tryPairNormal();

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

        $paired = $entry->modo === 'ranqueada' ? $this->tryPairRanked() : $this->tryPairNormal();
        if ($paired) {
            return ['status' => 'partida_encontrada', 'match_id' => $paired];
        }

        return [
            'status' => 'aguardando',
            'tempo_na_fila_segundos' => now()->diffInSeconds($entry->entrou_na_fila_em),
            'modo' => $entry->modo,
        ];
    }

    public function tryPairNormal(): ?int
    {
        $waiting = MatchmakingQueue::where('modo', 'normal')->orderBy('entrou_na_fila_em')->limit(2)->get();
        if ($waiting->count() < 2) {
            return null;
        }

        return $this->createMatchFromQueue($waiting[0], $waiting[1], 'normal');
    }

    public function tryPairRanked(): ?int
    {
        $queues = MatchmakingQueue::where('modo', 'ranqueada')->orderBy('entrou_na_fila_em')->get();
        if ($queues->count() < 2) {
            return null;
        }

        foreach ($queues as $i => $a) {
            $waitA = now()->diffInSeconds($a->entrou_na_fila_em);
            foreach ($queues as $j => $b) {
                if ($i >= $j) {
                    continue;
                }
                if (! $this->antiAbuse->allowsRankedPair($a, $b)) {
                    continue;
                }
                $maxWait = max($waitA, now()->diffInSeconds($b->entrou_na_fila_em));
                $divA = (string) $a->divisao;
                $divB = (string) $b->divisao;
                if (! $this->ranked->pairingAllowed($divA, $divB, $maxWait)) {
                    continue;
                }

                return $this->createMatchFromQueue($a, $b, 'ranqueada');
            }
        }

        return null;
    }

    /** @deprecated use tryPairNormal em código novo */
    public function tryPair(string $modo): ?int
    {
        return $modo === 'ranqueada' ? $this->tryPairRanked() : $this->tryPairNormal();
    }

    private function createMatchFromQueue(MatchmakingQueue $a, MatchmakingQueue $b, string $modo): ?int
    {
        return DB::transaction(function () use ($a, $b, $modo) {
            $freshA = MatchmakingQueue::where('user_id', $a->user_id)->lockForUpdate()->first();
            $freshB = MatchmakingQueue::where('user_id', $b->user_id)->lockForUpdate()->first();
            if (! $freshA || ! $freshB || $freshA->modo !== $modo || $freshB->modo !== $modo) {
                return null;
            }

            if ($modo === 'ranqueada') {
                $maxWait = max(
                    now()->diffInSeconds($freshA->entrou_na_fila_em),
                    now()->diffInSeconds($freshB->entrou_na_fila_em)
                );
                if (! $this->ranked->pairingAllowed((string) $freshA->divisao, (string) $freshB->divisao, $maxWait)) {
                    return null;
                }
                if (! $this->antiAbuse->allowsRankedPair($freshA, $freshB)) {
                    return null;
                }
            }

            MatchmakingQueue::whereIn('user_id', [$freshA->user_id, $freshB->user_id])->delete();

            $match = GameMatch::create([
                'modo' => $modo,
                'status' => MatchStatus::Aguardando,
            ]);

            $p1 = MatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $freshA->user_id,
                'deck_id' => $freshA->deck_id,
                'player_slot' => 1,
            ]);
            $p2 = MatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $freshB->user_id,
                'deck_id' => $freshB->deck_id,
                'player_slot' => 2,
            ]);

            $this->initializer->start($match, $p1, $p2);
            $match->refresh();

            $userA = User::find($freshA->user_id);
            $userB = User::find($freshB->user_id);

            broadcast(new MatchFound($match, $userB, $userA));
            broadcast(new MatchFound($match, $userA, $userB));
            broadcast(new MatchStarted($match, $userA, 1));
            broadcast(new MatchStarted($match, $userB, 2));

            return $match->id;
        });
    }
}
