<?php

namespace App\Services\Match;

use App\Enums\MatchStatus;
use App\Events\MatchFound;
use App\Events\MatchOfferCancelled;
use App\Events\MatchStarted;
use App\Models\GameMatch;
use App\Models\MatchmakingQueue;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Services\AntiAbuse\AntiAbuseService;
use App\Services\Bot\CasualSubstitutePairingService;
use App\Services\Bot\RankedSubstitutePairingService;
use App\Services\Deck\DeckService;
use App\Services\Game\MatchInitializer;
use App\Services\Ranked\RankedService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MatchmakingService
{
    public function __construct(
        private MatchInitializer $initializer,
        private DeckService $deckService,
        private RankedService $ranked,
        private AntiAbuseService $antiAbuse,
        private RankedSubstitutePairingService $rankedSubstitutes,
        private CasualSubstitutePairingService $casualSubstitutes,
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

        $this->expireAnyStalePendingMatchForUser($user);

        $existingInProgress = MatchPlayer::where('user_id', $user->id)
            ->whereHas('match', fn ($q) => $q->where('status', MatchStatus::EmAndamento->value))
            ->latest()
            ->first();

        if ($existingInProgress) {
            return ['status' => 'em_partida', 'match_id' => $existingInProgress->match_id];
        }

        $existingPending = MatchPlayer::query()
            ->where('user_id', $user->id)
            ->whereHas('match', fn ($q) => $q->where('status', MatchStatus::Aguardando->value))
            ->latest()
            ->first();

        if ($existingPending) {
            $match = GameMatch::with('players.user')->findOrFail($existingPending->match_id);

            return array_merge(
                ['status' => 'partida_encontrada', 'match_id' => $match->id],
                $this->matchAcceptMetaForUser($match, $user)
            );
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

        $paired = $modo === 'ranqueada'
            ? ($this->tryPairRanked() ?: $this->rankedSubstitutes->maybePairStaleSoloHumans())
            : ($this->tryPairNormal() ?: $this->casualSubstitutes->maybePairStaleSoloHuman());

        if ($paired) {
            $match = GameMatch::with('players.user')->findOrFail($paired);

            return array_merge(
                ['status' => 'partida_encontrada', 'match_id' => $paired],
                $this->matchAcceptMetaForUser($match, $user)
            );
        }

        return [
            'status' => 'aguardando',
            'posicao_na_fila' => MatchmakingQueue::where('modo', $modo)->count(),
            'tempo_estimado_segundos' => 15,
        ];
    }

    public function challenge(User $challenger, User $opponent, int $deckId): array
    {
        $this->deckService->assertPlayable($challenger, $deckId);

        $this->expireAnyStalePendingMatchForUser($challenger);
        $this->expireAnyStalePendingMatchForUser($opponent);

        $existingInProgress = MatchPlayer::where('user_id', $challenger->id)
            ->whereHas('match', fn ($q) => $q->where('status', MatchStatus::EmAndamento->value))
            ->latest()
            ->first();

        if ($existingInProgress) {
            return ['status' => 'em_partida', 'match_id' => $existingInProgress->match_id];
        }

        MatchmakingQueue::whereIn('user_id', [$challenger->id, $opponent->id])->delete();

        $oppDeck = $opponent->decks()->first()?->id ?? 1;

        $seconds = (int) config('game.match.accept_offer_seconds', 15);

        $match = GameMatch::create([
            'modo' => 'desafio',
            'status' => MatchStatus::Aguardando,
            'accept_deadline_at' => now()->addSeconds($seconds),
        ]);

        MatchPlayer::create([
            'match_id' => $match->id,
            'user_id' => $challenger->id,
            'deck_id' => $deckId,
            'player_slot' => 1,
        ]);
        MatchPlayer::create([
            'match_id' => $match->id,
            'user_id' => $opponent->id,
            'deck_id' => $oppDeck,
            'player_slot' => 2,
        ]);

        $match->load('players.user');

        broadcast(new MatchFound($match->fresh(), $opponent, $challenger));
        broadcast(new MatchFound($match->fresh(), $challenger, $opponent));

        return array_merge(
            ['status' => 'partida_encontrada', 'match_id' => $match->id],
            $this->matchAcceptMetaForUser($match, $challenger)
        );
    }

    public function leave(User $user): void
    {
        MatchmakingQueue::where('user_id', $user->id)->delete();
    }

    public function status(User $user): array
    {
        $this->expireAnyStalePendingMatchForUser($user);

        $inProgress = MatchPlayer::where('user_id', $user->id)
            ->whereHas('match', fn ($q) => $q->where('status', MatchStatus::EmAndamento->value))
            ->latest()
            ->first();

        if ($inProgress) {
            return ['status' => 'em_partida', 'match_id' => $inProgress->match_id];
        }

        $pending = MatchPlayer::query()
            ->where('user_id', $user->id)
            ->whereHas('match', fn ($q) => $q->where('status', MatchStatus::Aguardando->value))
            ->with(['match.players.user'])
            ->latest()
            ->first();

        if ($pending && $pending->match) {
            return array_merge(
                ['status' => 'partida_encontrada', 'match_id' => $pending->match_id],
                $this->matchAcceptMetaForUser($pending->match, $user)
            );
        }

        $entry = MatchmakingQueue::where('user_id', $user->id)->first();
        if (! $entry) {
            return ['status' => 'fora_da_fila'];
        }

        $paired = $entry->modo === 'ranqueada'
            ? ($this->tryPairRanked() ?: $this->rankedSubstitutes->maybePairStaleSoloHumans())
            : ($this->tryPairNormal() ?: $this->casualSubstitutes->maybePairStaleSoloHuman());
        if ($paired) {
            $match = GameMatch::with('players.user')->findOrFail($paired);

            return array_merge(
                ['status' => 'partida_encontrada', 'match_id' => $paired],
                $this->matchAcceptMetaForUser($match, $user)
            );
        }

        return [
            'status' => 'aguardando',
            // Carbon: $a->diffInSeconds($b) = $b − $a; queremos now − entrou_na_fila_em (valor ≥ 0).
            'tempo_na_fila_segundos' => max(
                0,
                (int) floor($entry->entrou_na_fila_em->diffInSeconds(now(), true)),
            ),
            'modo' => $entry->modo,
        ];
    }

    public function acceptOffer(User $user, int $matchId): array
    {
        DB::transaction(function () use ($user, $matchId) {
            $match = GameMatch::lockForUpdate()->find($matchId);
            if (! $match) {
                throw new InvalidArgumentException('Partida não encontrada.');
            }

            if ($match->status === MatchStatus::Aguardando
                && $match->accept_deadline_at
                && now()->gt($match->accept_deadline_at)) {
                $this->cancelPendingMatchAsExpired($match);
                throw new InvalidArgumentException('O tempo para aceitar esta partida expirou.');
            }

            if ($match->status !== MatchStatus::Aguardando) {
                throw new InvalidArgumentException('Esta partida não está aguardando confirmação.');
            }

            $player = MatchPlayer::where('match_id', $match->id)->where('user_id', $user->id)->first();
            if (! $player) {
                throw new InvalidArgumentException('Você não participa desta partida.');
            }

            if (! $player->match_accepted_at) {
                $player->update(['match_accepted_at' => now()]);
            }

            $players = MatchPlayer::where('match_id', $match->id)->with('user')->get();
            if ($players->count() === 2 && $players->every(fn ($p) => $p->match_accepted_at !== null)) {
                $p1 = $players->firstWhere('player_slot', 1);
                $p2 = $players->firstWhere('player_slot', 2);
                $arenaSlug = $this->resolveArenaMatchBoardSlugFromFirstAccepter($players);
                $match->arena_match_board_slug = $arenaSlug;
                $match->save();
                $this->initializer->start($match->fresh(['players.user']), $p1, $p2);
                $match->refresh();
                $userA = User::find($p1->user_id);
                $userB = User::find($p2->user_id);
                broadcast(new MatchStarted($match, $userA, 1));
                broadcast(new MatchStarted($match, $userB, 2));
            }
        });

        return ['ok' => true];
    }

    public function declineOffer(User $user, int $matchId): array
    {
        DB::transaction(function () use ($user, $matchId) {
            $match = GameMatch::lockForUpdate()->find($matchId);
            if (! $match) {
                throw new InvalidArgumentException('Partida não encontrada.');
            }

            if ($match->status === MatchStatus::Aguardando
                && $match->accept_deadline_at
                && now()->gt($match->accept_deadline_at)) {
                $this->cancelPendingMatchAsExpired($match);

                return;
            }

            if ($match->status !== MatchStatus::Aguardando) {
                return;
            }

            $player = MatchPlayer::where('match_id', $match->id)->where('user_id', $user->id)->first();
            if (! $player) {
                throw new InvalidArgumentException('Você não participa desta partida.');
            }

            if ($player->match_accepted_at) {
                throw new InvalidArgumentException('Você já confirmou esta partida.');
            }

            $this->cancelPendingMatchDeclined($match, $user->id);
        });

        return ['ok' => true];
    }

    public function tryPairNormal(): ?int
    {
        $waiting = MatchmakingQueue::where('modo', 'normal')->orderBy('entrou_na_fila_em')->limit(2)->get();
        if ($waiting->count() >= 2) {
            return $this->createMatchFromQueue($waiting[0], $waiting[1], 'normal');
        }

        return null;
    }

    public function tryPairRanked(): ?int
    {
        $queues = MatchmakingQueue::where('modo', 'ranqueada')->orderBy('entrou_na_fila_em')->get();
        if ($queues->count() < 2) {
            return null;
        }

        foreach ($queues as $i => $a) {
            $waitA = max(0, (int) floor($a->entrou_na_fila_em->diffInSeconds(now(), true)));
            foreach ($queues as $j => $b) {
                if ($i >= $j) {
                    continue;
                }
                if (! $this->antiAbuse->allowsRankedPair($a, $b)) {
                    continue;
                }
                $maxWait = max(
                    $waitA,
                    max(0, (int) floor($b->entrou_na_fila_em->diffInSeconds(now(), true))),
                );
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
                    max(0, (int) floor($freshA->entrou_na_fila_em->diffInSeconds(now(), true))),
                    max(0, (int) floor($freshB->entrou_na_fila_em->diffInSeconds(now(), true)))
                );
                if (! $this->ranked->pairingAllowed((string) $freshA->divisao, (string) $freshB->divisao, $maxWait)) {
                    return null;
                }
                if (! $this->antiAbuse->allowsRankedPair($freshA, $freshB)) {
                    return null;
                }
            }

            MatchmakingQueue::whereIn('user_id', [$freshA->user_id, $freshB->user_id])->delete();

            $seconds = (int) config('game.match.accept_offer_seconds', 15);

            $match = GameMatch::create([
                'modo' => $modo,
                'status' => MatchStatus::Aguardando,
                'accept_deadline_at' => now()->addSeconds($seconds),
            ]);

            MatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $freshA->user_id,
                'deck_id' => $freshA->deck_id,
                'player_slot' => 1,
            ]);
            MatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $freshB->user_id,
                'deck_id' => $freshB->deck_id,
                'player_slot' => 2,
            ]);

            $match->load('players.user');
            $userA = $match->players->firstWhere('player_slot', 1)?->user;
            $userB = $match->players->firstWhere('player_slot', 2)?->user;

            if ($userA && $userB) {
                broadcast(new MatchFound($match->fresh(), $userB, $userA));
                broadcast(new MatchFound($match->fresh(), $userA, $userB));
            }

            return $match->id;
        });
    }

    private function matchAcceptMetaForUser(GameMatch $match, User $viewer): array
    {
        $match->loadMissing('players.user');
        $oppPlayer = $match->players->first(fn ($p) => $p->user_id !== $viewer->id);
        $opp = $oppPlayer?->user;

        return [
            'modo' => $match->modo,
            'accept_deadline_at' => $match->accept_deadline_at?->toIso8601String(),
            'oponente' => $opp
                ? $this->ranked->dadosOponenteParaOferta($viewer, $opp, $match)
                : [
                    'nome' => 'Oponente',
                    'divisao' => 'ferro',
                    'divisao_label' => 'Ferro',
                    'pontos' => 0,
                    'eh_bot' => false,
                ],
            'segundos_para_aceitar' => max(
                0,
                ($match->accept_deadline_at?->getTimestamp() ?? 0) - now()->getTimestamp()
            ),
        ];
    }

    private function expireAnyStalePendingMatchForUser(User $user): void
    {
        $rows = MatchPlayer::query()
            ->where('user_id', $user->id)
            ->whereHas('match', fn ($q) => $q->where('status', MatchStatus::Aguardando->value))
            ->get();

        foreach ($rows as $row) {
            DB::transaction(function () use ($row) {
                $match = GameMatch::lockForUpdate()->find($row->match_id);
                if (! $match || $match->status !== MatchStatus::Aguardando) {
                    return;
                }
                if (! $match->accept_deadline_at || now()->lte($match->accept_deadline_at)) {
                    return;
                }
                $this->cancelPendingMatchAsExpired($match);
            });
        }
    }

    private function cancelPendingMatchAsExpired(GameMatch $match): void
    {
        $match->update(['status' => MatchStatus::Cancelada]);
        $uids = MatchPlayer::where('match_id', $match->id)->pluck('user_id');
        foreach ($uids as $uid) {
            $recipient = User::find($uid);
            if ($recipient) {
                broadcast(new MatchOfferCancelled($match->fresh(), $recipient, 'expired'));
            }
        }
    }

    /**
     * Tabuleiro de arena partilhado: usa o cosmético equipado de quem aceitou primeiro.
     * Empate microscópico no mesmo instante → desempata por player_slot menor (prioridade jogador 1).
     *
     * @param  EloquentCollection<int, MatchPlayer>  $players
     */
    private function resolveArenaMatchBoardSlugFromFirstAccepter(EloquentCollection $players): string
    {
        /** @var MatchPlayer|null $first */
        $first = $players
            ->sortBy(function ($p) {
                $t = $p->match_accepted_at;
                $ts = $t ? $t->format('Y-m-d H:i:s.u') : '0000-00-00 00:00:00.000000';

                return $ts.'_'.str_pad((string) $p->player_slot, 2, '0', STR_PAD_LEFT);
            })
            ->first();

        $slug = trim((string) ($first?->user?->match_board_slug ?? ''));
        if ($slug === '') {
            $slug = 'padrao';
        }

        return $slug;
    }

    private function cancelPendingMatchDeclined(GameMatch $match, int $declinerUserId): void
    {
        $match->update(['status' => MatchStatus::Cancelada]);
        $players = MatchPlayer::where('match_id', $match->id)->get();
        foreach ($players as $pl) {
            $recipient = User::find($pl->user_id);
            if (! $recipient) {
                continue;
            }
            $reason = $pl->user_id === $declinerUserId ? 'you_declined' : 'opponent_declined';
            broadcast(new MatchOfferCancelled($match->fresh(), $recipient, $reason));
        }
    }
}
