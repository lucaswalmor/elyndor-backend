<?php

namespace App\Services\Economy;

use App\Models\Chest;
use App\Models\ChestShopPurchase;
use App\Models\PlayerChestStack;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ChestOpeningService
{
    /**
     * Compra um baú na loja: debita moeda conforme colunas do baú e soma unidades no inventário.
     *
     * @return array{
     *     mode: string,chest: array{id: int, slug: string, name: string},quantity: int,purchased: int,
     *     cost_cristais?: int,cost_cristais_each?: int,cost_moedas?: int,cost_moedas_each?: int,
     *     balance: array{cristais: int, moedas: int}
     * }
     */
    public function purchaseForInventory(User $user, string $slug, int $quantity = 1): array
    {
        $qty = max(1, $quantity);

        $chest = Chest::query()
            ->where('slug', $slug)
            ->where('active', true)
            ->where('available_in_shop', true)
            ->first();

        if (! $chest) {
            throw new InvalidArgumentException('Este baú não está disponível na loja.');
        }

        $cristaisUnit = $chest->cost_cristais;
        $moedasUnit = $chest->cost_moedas;

        $hasCristais = $cristaisUnit !== null && (int) $cristaisUnit > 0;
        $hasMoedas = $moedasUnit !== null && (int) $moedasUnit > 0;

        if (! $hasCristais && ! $hasMoedas) {
            throw new InvalidArgumentException('Este baú não tem preço para compra na loja.');
        }
        if ($hasCristais && $hasMoedas) {
            throw new InvalidArgumentException('Configuração de preço inválida para este baú.');
        }

        if ($hasCristais) {
            return $this->purchaseWithCristais($user, $chest, (int) $cristaisUnit, $qty);
        }

        return $this->purchaseWithMoedas($user, $chest, (int) $moedasUnit, $qty);
    }

    /** @return array<string, mixed> */
    private function purchaseWithCristais(User $user, Chest $chest, int $costEach, int $qty): array
    {
        $totalCost = $costEach * $qty;

        return DB::transaction(function () use ($user, $totalCost, $costEach, $chest, $qty) {
            $u = User::query()->lockForUpdate()->findOrFail($user->id);
            if ((int) $u->cristais < $totalCost) {
                throw new InvalidArgumentException('Cristais insuficientes.');
            }
            $u->cristais = (int) $u->cristais - $totalCost;
            $u->save();

            $stack = $this->incrementStackLocked((int) $user->id, (int) $chest->id, $qty);

            ChestShopPurchase::query()->create([
                'user_id' => $user->id,
                'chest_id' => $chest->id,
                'quantity' => $qty,
                'currency' => 'cristais',
                'unit_price' => $costEach,
                'total_paid' => $totalCost,
            ]);

            return [
                'mode' => 'inventory',
                'chest' => [
                    'id' => $chest->id,
                    'slug' => $chest->slug,
                    'name' => $chest->name,
                ],
                'quantity' => (int) $stack->quantity,
                'purchased' => $qty,
                'cost_cristais' => $totalCost,
                'cost_cristais_each' => $costEach,
                'balance' => [
                    'cristais' => (int) $u->fresh()->cristais,
                    'moedas' => (int) $u->fresh()->moedas,
                ],
            ];
        });
    }

    /** @return array<string, mixed> */
    private function purchaseWithMoedas(User $user, Chest $chest, int $costEach, int $qty): array
    {
        $totalCost = $costEach * $qty;

        return DB::transaction(function () use ($user, $totalCost, $costEach, $chest, $qty) {
            $u = User::query()->lockForUpdate()->findOrFail($user->id);
            if ((int) $u->moedas < $totalCost) {
                throw new InvalidArgumentException('Moedas insuficientes.');
            }
            $u->moedas = (int) $u->moedas - $totalCost;
            $u->save();

            $stack = $this->incrementStackLocked((int) $user->id, (int) $chest->id, $qty);

            ChestShopPurchase::query()->create([
                'user_id' => $user->id,
                'chest_id' => $chest->id,
                'quantity' => $qty,
                'currency' => 'moedas',
                'unit_price' => $costEach,
                'total_paid' => $totalCost,
            ]);

            return [
                'mode' => 'inventory',
                'chest' => [
                    'id' => $chest->id,
                    'slug' => $chest->slug,
                    'name' => $chest->name,
                ],
                'quantity' => (int) $stack->quantity,
                'purchased' => $qty,
                'cost_moedas' => $totalCost,
                'cost_moedas_each' => $costEach,
                'balance' => [
                    'cristais' => (int) $u->fresh()->cristais,
                    'moedas' => (int) $u->fresh()->moedas,
                ],
            ];
        });
    }

    private function incrementStackLocked(int $userId, int $chestId, int $delta = 1): PlayerChestStack
    {
        PlayerChestStack::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'chest_id' => $chestId,
            ],
            ['quantity' => 0]
        );

        $stack = PlayerChestStack::query()
            ->where('user_id', $userId)
            ->where('chest_id', $chestId)
            ->lockForUpdate()
            ->firstOrFail();

        $stack->increment('quantity', $delta);

        return $stack->fresh();
    }
}
