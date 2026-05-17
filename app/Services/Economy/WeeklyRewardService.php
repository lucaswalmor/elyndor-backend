<?php

namespace App\Services\Economy;

use App\Models\Chest;
use App\Models\PlayerChestStack;
use App\Models\PlayerWeekly;
use App\Models\User;
use App\Models\WeeklyChestPool;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WeeklyRewardService
{
    public function weekStartToday(): string
    {
        return Carbon::now(config('app.timezone'))->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function inClaimWindow(): bool
    {
        if (config('game.progression.weekly.claim_any_time')) {
            return true;
        }

        $now = Carbon::now(config('app.timezone'));

        return $now->isSaturday() || $now->isSunday();
    }

    public function currentRow(User $user): PlayerWeekly
    {
        return PlayerWeekly::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'week_start' => $this->weekStartToday(),
            ],
            ['xp_earned' => 0]
        );
    }

    public function status(User $user): array
    {
        $cfg = config('game.progression.weekly');
        $row = $this->currentRow($user);
        $row->loadMissing('grantedChest');

        $eligible = (int) $row->xp_earned >= (int) $cfg['xp_min_claim']
            && ! $row->claimed_at
            && $this->inClaimWindow();

        $granted = $row->grantedChest;

        return [
            'week_start' => $row->week_start->toDateString(),
            'xp_earned' => (int) $row->xp_earned,
            'xp_cap' => (int) $cfg['xp_cap'],
            'xp_min_claim' => (int) $cfg['xp_min_claim'],
            'claimed' => (bool) $row->claimed_at,
            'claim_window_open' => $this->inClaimWindow(),
            'eligible' => $eligible,
            'reward_type' => 'chest',
            'granted_chest' => $granted ? [
                'id' => $granted->id,
                'slug' => $granted->slug,
                'name' => $granted->name,
            ] : null,
        ];
    }

    /**
     * Entrega um baú sorteado da pool semanal no inventário do jogador.
     *
     * @return array{chest: array{id: int, slug: string, name: string}}
     */
    public function claim(User $user): array
    {
        if (! $this->inClaimWindow()) {
            throw new InvalidArgumentException('Resgate semanal só pode ser feito no fim de semana.');
        }

        $cfg = config('game.progression.weekly');
        $poolSlug = (string) $cfg['chest_pool_slug'];

        return DB::transaction(function () use ($user, $cfg, $poolSlug) {
            $row = PlayerWeekly::query()->where('user_id', $user->id)
                ->where('week_start', $this->weekStartToday())
                ->lockForUpdate()
                ->first();

            if (! $row || $row->claimed_at) {
                throw new InvalidArgumentException('Nada a resgatar ou já resgatado.');
            }
            if ((int) $row->xp_earned < (int) $cfg['xp_min_claim']) {
                throw new InvalidArgumentException('XP semanal insuficiente (mín. '.(int) $cfg['xp_min_claim'].').');
            }

            $pool = WeeklyChestPool::query()
                ->where('slug', $poolSlug)
                ->where('active', true)
                ->first();

            if (! $pool) {
                throw new InvalidArgumentException('Pool de recompensa semanal indisponível.');
            }

            $chests = $pool->chests()
                ->where('chests.active', true)
                ->get();

            if ($chests->isEmpty()) {
                throw new InvalidArgumentException('Nenhum baú ativo nesta pool de recompensa.');
            }

            $chest = $this->pickWeightedChest($chests);
            if (! $chest instanceof Chest) {
                throw new InvalidArgumentException('Falha ao sortear baú.');
            }

            $userId = (int) $user->id;
            $stack = PlayerChestStack::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'chest_id' => $chest->id,
                ],
                ['quantity' => 0]
            );
            $stack->increment('quantity');

            $row->claimed_at = now();
            $row->granted_chest_id = $chest->id;
            $row->offers = null;
            $row->save();

            return [
                'chest' => [
                    'id' => $chest->id,
                    'slug' => $chest->slug,
                    'name' => $chest->name,
                ],
            ];
        });
    }

    /**
     * @param  Collection<int, Chest>  $chests
     */
    private function pickWeightedChest(Collection $chests): ?Chest
    {
        $sum = 0;
        foreach ($chests as $chest) {
            $sum += max(0, (int) $chest->pivot->weight);
        }
        if ($sum <= 0) {
            return $chests->first();
        }

        $r = random_int(0, $sum - 1);
        $acc = 0;
        foreach ($chests as $chest) {
            $w = max(0, (int) $chest->pivot->weight);
            $acc += $w;
            if ($r < $acc) {
                return $chest;
            }
        }

        return $chests->last();
    }
}
