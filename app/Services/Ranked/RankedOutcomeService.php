<?php

namespace App\Services\Ranked;

use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\RankedMatchOutcome;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RankedOutcomeService
{
    public function __construct(
        private RankedService $ranked,
    ) {}

    public function applyIfRanked(GameMatch $match, int $vencedorUserId): void
    {
        if ($match->modo !== 'ranqueada') {
            return;
        }

        if (RankedMatchOutcome::where('match_id', $match->id)->exists()) {
            return;
        }

        $players = MatchPlayer::where('match_id', $match->id)->get();
        if ($players->count() !== 2) {
            return;
        }

        $winnerP = $players->first(fn ($p) => (int) $p->user_id === (int) $vencedorUserId);
        $loserP = $players->first(fn ($p) => (int) $p->user_id !== (int) $vencedorUserId);
        if (! $winnerP || ! $loserP) {
            return;
        }

        $botMult = (float) config('game.bots.ranked_points_multiplier', 0.5);
        $anyBot = $winnerP->is_bot || $loserP->is_bot;
        $mult = $anyBot ? $botMult : 1.0;

        [$wRaw, $lRaw] = $this->resolveBracketDeltas($winnerP, $loserP);
        $wDelta = (int) round($wRaw * $mult);
        $lDelta = (int) round($lRaw * $mult);

        DB::transaction(function () use ($winnerP, $loserP, $wDelta, $lDelta, $match) {
            if (! $winnerP->is_bot) {
                $oppDiv = $this->opponentDivisionKeyForOutcome($loserP);
                $this->persistPlayerOutcome($winnerP->user_id, true, $wDelta, $oppDiv, $match);
            }
            if (! $loserP->is_bot) {
                $oppDiv = $this->opponentDivisionKeyForOutcome($winnerP);
                $this->persistPlayerOutcome($loserP->user_id, false, $lDelta, $oppDiv, $match);
            }
        });
    }

    /** @return array{0: int, 1: int} [winnerDelta, loserDelta] */
    private function resolveBracketDeltas(MatchPlayer $winnerP, MatchPlayer $loserP): array
    {
        if ($winnerP->is_bot || $loserP->is_bot) {
            $human = $winnerP->is_bot ? $loserP : $winnerP;
            $div = $this->divisionKeyForHuman($human);

            return $this->ranked->pointDeltas($div, $div);
        }

        $wDiv = $this->divisionKeyForHuman($winnerP);
        $lDiv = $this->divisionKeyForHuman($loserP);

        return $this->ranked->pointDeltas($wDiv, $lDiv);
    }

    private function divisionKeyForHuman(MatchPlayer $p): string
    {
        $u = User::query()->find($p->user_id);

        return $this->ranked->divisionKeyForPoints((int) ($u?->ranked_points ?? 0));
    }

    private function opponentDivisionKeyForOutcome(MatchPlayer $opponent): ?string
    {
        if ($opponent->is_bot) {
            return null;
        }

        return $this->divisionKeyForHuman($opponent);
    }

    private function persistPlayerOutcome(int $userId, bool $won, int $delta, ?string $oppDiv, GameMatch $match): void
    {
        $user = User::query()->lockForUpdate()->find($userId);
        if (! $user) {
            return;
        }

        $antes = (int) $user->ranked_points;
        $depois = $this->ranked->clampPoints($antes + $delta);

        $user->ranked_points = $depois;
        if ($won) {
            $user->ranked_wins = ((int) $user->ranked_wins) + 1;
        } else {
            $user->ranked_losses = ((int) $user->ranked_losses) + 1;
        }
        $user->save();

        RankedMatchOutcome::create([
            'match_id' => $match->id,
            'user_id' => $userId,
            'venceu' => $won,
            'delta' => $delta,
            'pontos_antes' => $antes,
            'pontos_depois' => $depois,
            'divisao_oponente' => $oppDiv,
        ]);
    }
}
