<?php

namespace App\Services\Economy;

use App\Models\ChestShopPurchase;
use App\Models\PlayerChestStack;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reembolso loja baús pagos apenas em moedas: até 24h (America/Sao_Paulo), sem abrir (stack ≥ qty).
 */
class ChestRefundService
{
    /** Valor creditado é sempre {@see ChestShopPurchase::$total_paid} registado na compra — imune a mudanças manuais de preço posteriores. */
    public function refundCoinsChestPurchase(User $user, ChestShopPurchase $purchase): User
    {
        if ($purchase->user_id !== $user->id) {
            throw new InvalidArgumentException('Compra não pertence ao utilizador.');
        }

        if ($purchase->refunded_at !== null) {
            throw new InvalidArgumentException('Esta compra já foi reembolsada.');
        }

        if ($purchase->currency !== 'moedas') {
            throw new InvalidArgumentException('Reembolso disponível apenas para compras com moedas.');
        }

        $tz = (string) config('game.chests.purchase_refund.timezone', 'America/Sao_Paulo');
        $hours = (int) config('game.chests.purchase_refund.window_hours', 24);

        $deadline = Carbon::parse($purchase->created_at)->timezone($tz)->addHours($hours);
        $nowTz = Carbon::now($tz);
        if ($nowTz->greaterThan($deadline)) {
            throw new InvalidArgumentException('O prazo de reembolso (24 h desde a compra, horário de São Paulo) expirou.');
        }

        return DB::transaction(function () use ($user, $purchase, $hours, $tz): User {
            $lockedPurchase = ChestShopPurchase::query()
                ->where('user_id', $user->id)
                ->where('id', $purchase->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedPurchase || $lockedPurchase->refunded_at !== null) {
                throw new InvalidArgumentException('Esta compra já não pode ser reembolsada.');
            }

            if ($lockedPurchase->currency !== 'moedas') {
                throw new InvalidArgumentException('Reembolso disponível apenas para compras com moedas.');
            }

            $stack = PlayerChestStack::query()
                ->where('user_id', $user->id)
                ->where('chest_id', $lockedPurchase->chest_id)
                ->lockForUpdate()
                ->first();

            $have = $stack !== null ? (int) $stack->quantity : 0;
            if ($have < (int) $lockedPurchase->quantity) {
                throw new InvalidArgumentException(
                    'Não há baús suficientes no inventário para reembolsar (talvez já tenham sido abertos).'
                );
            }

            $u = User::query()->where('id', $user->id)->lockForUpdate()->firstOrFail();
            $refundAmt = (int) $lockedPurchase->total_paid;
            $u->moedas = ((int) $u->moedas) + $refundAmt;
            $u->save();

            $stack->quantity = $have - (int) $lockedPurchase->quantity;
            if ($stack->quantity <= 0) {
                $stack->delete();
            } else {
                $stack->save();
            }

            $lockedPurchase->forceFill(['refunded_at' => Carbon::now()])->save();

            $createdSp = Carbon::parse($purchase->created_at)->timezone((string) $tz);
            $deadlineLogged = $createdSp->copy()->addHours($hours);

            logger()->channel('game_balance')->info('economy.chest_refund_completed', [
                'user_id' => $user->id,
                'purchase_id' => $lockedPurchase->id,
                'chest_id' => $lockedPurchase->chest_id,
                'qty' => (int) $lockedPurchase->quantity,
                'moedas_credited' => $refundAmt,
                'unit_price_snapshotted' => (int) $lockedPurchase->unit_price,
                'timezone' => $tz,
                'deadline_logged_at_tz' => $deadlineLogged->toIso8601String(),
            ]);

            return $u->fresh();
        });
    }

    /**
     * @param  iterable<int, ChestShopPurchase>  $items
     * @param  array<int, int>  $qtyByChestId  chest_id → quantidade no inventário
     * @return array<int, array<string, mixed>>
     */
    public function refundMetaByPurchaseIds(iterable $items, array $qtyByChestId): array
    {
        $tz = (string) config('game.chests.purchase_refund.timezone', 'America/Sao_Paulo');
        $hours = (int) config('game.chests.purchase_refund.window_hours', 24);
        $nowTz = Carbon::now($tz);
        $out = [];

        foreach ($items as $p) {
            $refundedAt = $p->refunded_at;
            $isMoedas = $p->currency === 'moedas';
            $qtyStack = $qtyByChestId[(int) $p->chest_id] ?? 0;
            $deadlineTz = Carbon::parse($p->created_at)->timezone($tz)->addHours($hours);
            $withinTime = ! $nowTz->greaterThan($deadlineTz);
            $hasInventory = $qtyStack >= (int) $p->quantity;

            $eligible = $refundedAt === null
                && $isMoedas
                && $withinTime
                && $hasInventory;

            $reason = match (true) {
                ! $isMoedas => 'cristais_excluded',
                $refundedAt !== null => 'refunded',
                ! $withinTime => 'deadline_passed',
                ! $hasInventory => 'opened_or_missing_stack',
                default => null,
            };

            $out[(int) $p->id] = [
                'refunded_at' => $refundedAt?->toIso8601String(),
                'refund_eligible' => $eligible,
                'refund_deadline_iso' => $deadlineTz->toIso8601String(),
                'refund_reason_code' => $reason,
                'refund_moedas_amount' => $eligible ? (int) $p->total_paid : null,
            ];
        }

        return $out;
    }
}
