<?php

namespace App\Services\Bot;

use App\Events\MatchFound;
use App\Enums\MatchStatus;
use App\Models\GameMatch;
use App\Models\MatchmakingQueue;
use App\Models\MatchPlayer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ranqueada: se ficar apenas 1 humano na fila além do limite configurado, pareia contra substituto.
 */
class RankedSubstitutePairingService
{
    public function maybePairStaleSoloHumans(): ?int
    {
        if (! config('game.bots.enabled')) {
            return null;
        }

        return DB::transaction(function () {
            $count = MatchmakingQueue::query()->where('modo', 'ranqueada')->lockForUpdate()->count();
            if ($count !== 1) {
                return null;
            }

            /** @var MatchmakingQueue|null $humanRow */
            $humanRow = MatchmakingQueue::query()
                ->where('modo', 'ranqueada')
                ->orderBy('entrou_na_fila_em')
                ->lockForUpdate()
                ->first();

            if (! $humanRow) {
                return null;
            }

            $wait = max(0, (int) floor($humanRow->entrou_na_fila_em->diffInSeconds(now(), true)));
            $need = (int) config('game.bots.queue.ranked_fallback_after_seconds', 30);
            if ($wait < $need) {
                return null;
            }

            $div = (string) ($humanRow->divisao ?? 'ferro');
            $bot = User::query()
                ->where('is_bot', true)
                ->where('email', 'rank_substitute_'.$div.'@bots.elyndor.local')
                ->first();

            if (! $bot) {
                return null;
            }

            $botDeckId = $bot->decks()->where('is_padrao', true)->value('id');
            if (! $botDeckId) {
                return null;
            }

            MatchmakingQueue::where('user_id', $humanRow->user_id)->delete();

            $seconds = (int) config('game.match.accept_offer_seconds', 15);

            $match = GameMatch::create([
                'modo' => 'ranqueada',
                'status' => MatchStatus::Aguardando,
                'accept_deadline_at' => now()->addSeconds($seconds),
            ]);

            MatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $humanRow->user_id,
                'deck_id' => $humanRow->deck_id,
                'player_slot' => 1,
            ]);

            MatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $bot->id,
                'deck_id' => $botDeckId,
                'player_slot' => 2,
                'is_bot' => true,
                'match_accepted_at' => now(),
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
}
