<?php

namespace App\Services\Player;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlayerMatchStatsService
{
    public function applyIfNotYet(GameMatch $match): void
    {
        DB::transaction(function () use ($match) {
            if (DB::table('match_player_stats_applied')->where('match_id', $match->id)->exists()) {
                return;
            }

            $fresh = GameMatch::query()->with('players')->find($match->id);
            if (! $fresh) {
                return;
            }

            $seconds = 0;
            if ($fresh->iniciada_em && $fresh->finalizada_em) {
                $seconds = max(0, $fresh->iniciada_em->diffInSeconds($fresh->finalizada_em));
            }

            $modo = trim((string) ($fresh->modo ?? 'normal'));
            if ($modo === '') {
                $modo = 'normal';
            }

            foreach ($fresh->players as $player) {
                if ($player->is_bot || ! $player->user_id) {
                    continue;
                }

                $user = User::query()->lockForUpdate()->find($player->user_id);
                if (! $user) {
                    continue;
                }

                $user->total_matches_played = ((int) $user->total_matches_played) + 1;

                $counts = is_array($user->match_mode_counts) ? $user->match_mode_counts : [];
                $counts[$modo] = (int) ($counts[$modo] ?? 0) + 1;
                $user->match_mode_counts = $counts;

                $user->playtime_seconds = ((int) $user->playtime_seconds) + $seconds;

                $user->save();
            }

            DB::table('match_player_stats_applied')->insert([
                'match_id' => $match->id,
                'applied_at' => now(),
            ]);
        });
    }
}
