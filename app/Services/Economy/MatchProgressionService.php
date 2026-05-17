<?php

namespace App\Services\Economy;

use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\PlayerLevel;
use App\Models\PlayerWeekly;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MatchProgressionService
{
    public function applyIfNotYet(GameMatch $match, int $vencedorUserId): void
    {
        if (DB::table('match_progression_applied')->where('match_id', $match->id)->exists()) {
            return;
        }

        $players = MatchPlayer::where('match_id', $match->id)->get();
        if ($players->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($match, $players, $vencedorUserId) {
            foreach ($players as $p) {
                if ($p->is_bot) {
                    continue;
                }
                $won = (int) $p->user_id === (int) $vencedorUserId;
                $this->rewardUser($p->user_id, $won);
            }

            DB::table('match_progression_applied')->insert([
                'match_id' => $match->id,
                'applied_at' => now(),
            ]);
        });
    }

    private function rewardUser(int $userId, bool $won): void
    {
        $xpCfg = config('game.progression.xp_per_match');
        $cCfg = config('game.progression.cristais_per_match');

        $xp = $won ? (int) $xpCfg['vitoria'] : (int) $xpCfg['derrota'];
        $cristais = $won ? (int) $cCfg['vitoria'] : (int) $cCfg['derrota'];

        $user = User::query()->lockForUpdate()->find($userId);
        if (! $user) {
            return;
        }

        $today = Carbon::now(config('app.timezone'))->toDateString();

        if ($won && $user->last_daily_win_bonus_date !== $today) {
            $xp += (int) $xpCfg['primeira_vitoria_dia_bonus'];
            $cristais += (int) $cCfg['primeira_vitoria_dia_bonus'];
            $user->last_daily_win_bonus_date = $today;
        }

        $user->cristais = (int) $user->cristais + $cristais;
        $user->save();

        $this->addWeeklyXp($user, $xp);

        $level = PlayerLevel::query()->lockForUpdate()->where('user_id', $userId)->first();
        if (! $level) {
            return;
        }

        $level->xp_atual = (int) $level->xp_atual + $xp;
        $bonusCfg = config('game.progression.level_up_bonus_cristais');
        $base = (int) $bonusCfg['base'];
        $per = (int) $bonusCfg['per_level'];

        while ($level->xp_atual >= $level->xpParaProximoNivel()) {
            $need = $level->xpParaProximoNivel();
            $level->xp_atual -= $need;
            $level->nivel = (int) $level->nivel + 1;
            $gain = $base + ($per * $level->nivel);
            $user->cristais = (int) $user->cristais + $gain;
            $user->save();
        }

        $level->save();
    }

    private function addWeeklyXp(User $user, int $xpGain): void
    {
        $weeklyCfg = config('game.progression.weekly');
        $cap = (int) $weeklyCfg['xp_cap'];
        $weekStart = Carbon::now(config('app.timezone'))->startOfWeek(Carbon::MONDAY)->toDateString();

        $row = PlayerWeekly::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'week_start' => $weekStart,
            ],
            ['xp_earned' => 0]
        );

        if ($row->claimed_at) {
            return;
        }

        $room = max(0, $cap - (int) $row->xp_earned);
        $add = min($xpGain, $room);
        if ($add > 0) {
            $row->xp_earned = (int) $row->xp_earned + $add;
            $row->save();
        }
    }
}
