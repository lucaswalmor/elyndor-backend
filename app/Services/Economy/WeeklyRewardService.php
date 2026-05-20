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

/**
 * Ciclo semanal: inicia no domingo (1º acesso = reset + novo ciclo).
 * Resgate liberado ao atingir 1000 XP até sábado; no domingo seguinte perde se não resgatou.
 */
class WeeklyRewardService
{
    public function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    /** Domingo que abre o ciclo atual (Y-m-d). */
    public function cycleStartDate(): string
    {
        return Carbon::now($this->timezone())
            ->startOfWeek(Carbon::SUNDAY)
            ->toDateString();
    }

    public function currentRow(User $user): PlayerWeekly
    {
        return PlayerWeekly::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'week_start' => $this->cycleStartDate(),
            ],
            ['xp_earned' => 0]
        );
    }

    public function status(User $user): array
    {
        $cfg = config('game.progression.weekly');
        $row = $this->currentRow($user);
        $row->loadMissing('grantedChest');

        $podeResgatar = $this->podeResgatar($row, $cfg);
        $mostrarModal = $podeResgatar && $row->modal_resgate_vista_em === null;

        $granted = $row->grantedChest;
        $agora = Carbon::now($this->timezone());

        return [
            'week_start' => $row->week_start->toDateString(),
            'xp_earned' => (int) $row->xp_earned,
            'xp_cap' => (int) $cfg['xp_cap'],
            'xp_min_claim' => (int) $cfg['xp_min_claim'],
            'claimed' => (bool) $row->claimed_at,
            'pode_resgatar' => $podeResgatar,
            'mostrar_modal_resgate' => $mostrarModal,
            'eligible' => $podeResgatar,
            'claim_window_open' => true,
            'claim_block_reason' => $this->motivoResgateBloqueado($row, $cfg),
            'ultimo_dia_resgate' => $agora->isSaturday() && $podeResgatar,
            'proximo_reset_em' => $agora->copy()->next(Carbon::SUNDAY)->startOfDay()->toIso8601String(),
            'reward_type' => 'chest',
            'granted_chest' => $granted ? [
                'id' => $granted->id,
                'slug' => $granted->slug,
                'name' => $granted->name,
            ] : null,
        ];
    }

    /**
     * Soma XP semanal no ciclo atual (ignora se já resgatou).
     */
    public function addWeeklyXp(User $user, int $xpGain): void
    {
        if ($xpGain <= 0) {
            return;
        }

        $weeklyCfg = config('game.progression.weekly');
        $cap = (int) $weeklyCfg['xp_cap'];

        $row = $this->currentRow($user);

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

    /**
     * Fecha a oferta da modal sem resgatar (botão "Mais tarde").
     */
    public function dismissModal(User $user): void
    {
        $row = $this->currentRow($user);

        if ($row->claimed_at || $row->modal_resgate_vista_em !== null) {
            return;
        }

        if (! $this->podeResgatar($row, config('game.progression.weekly'))) {
            return;
        }

        $row->modal_resgate_vista_em = now();
        $row->save();
    }

    /**
     * @return array{chest: array{id: int, slug: string, name: string}}
     */
    public function claim(User $user): array
    {
        $cfg = config('game.progression.weekly');
        $poolSlug = (string) $cfg['chest_pool_slug'];

        return DB::transaction(function () use ($user, $cfg, $poolSlug) {
            $row = PlayerWeekly::query()->where('user_id', $user->id)
                ->where('week_start', $this->cycleStartDate())
                ->lockForUpdate()
                ->first();

            if (! $row || $row->claimed_at) {
                throw new InvalidArgumentException('Nada a resgatar ou já resgatado.');
            }
            if (! $this->podeResgatar($row, $cfg)) {
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
            $row->modal_resgate_vista_em = $row->modal_resgate_vista_em ?? now();
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

    private function podeResgatar(PlayerWeekly $row, array $cfg): bool
    {
        return (int) $row->xp_earned >= (int) $cfg['xp_min_claim']
            && ! $row->claimed_at;
    }

    private function motivoResgateBloqueado(PlayerWeekly $row, array $cfg): ?string
    {
        if ($row->claimed_at) {
            return 'ja_resgatado';
        }
        if ((int) $row->xp_earned < (int) $cfg['xp_min_claim']) {
            return 'xp_insuficiente';
        }

        return null;
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

        $roll = random_int(0, $sum - 1);
        $acc = 0;
        foreach ($chests as $chest) {
            $w = max(0, (int) $chest->pivot->weight);
            $acc += $w;
            if ($roll < $acc) {
                return $chest;
            }
        }

        return $chests->last();
    }
}
