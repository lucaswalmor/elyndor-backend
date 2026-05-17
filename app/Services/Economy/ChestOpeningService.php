<?php

namespace App\Services\Economy;

use App\Models\Chest;
use App\Models\PlayerChestStack;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ChestOpeningService
{
    /**
     * Compra um baú na loja: debita moeda e coloca 1 unidade no inventário (player_chest_stacks).
     * O conteúdo sorteado na abertura vem sempre de chest_items.
     *
     * @return array{
     *     mode: string,
     *     chest: array{id: int, slug: string, name: string},
     *     quantity: int,
     *     cost_cristais?: int,
     *     cost_moedas?: int,
     *     balance: array{cristais: int, moedas: int}
     * }
     */
    public function purchaseForInventory(User $user, string $kind, int $quantity = 1): array
    {
        $qty = max(1, $quantity);

        return match ($kind) {
            'cristal_basico' => $this->purchaseCristalBasico($user, $qty),
            'premium_padrao' => $this->purchasePremiumPadrao($user, $qty),
            default => throw new InvalidArgumentException('Tipo de baú inválido.'),
        };
    }

    /** @return array<string, mixed> */
    private function purchaseCristalBasico(User $user, int $qty): array
    {
        $costEach = (int) config('game.chests.cristal_basico.cost_cristais');
        $chest = $this->chestBySlugOrFail('cristal_basico');
        $totalCost = $costEach * $qty;

        return DB::transaction(function () use ($user, $totalCost, $costEach, $chest, $qty) {
            $u = User::query()->lockForUpdate()->findOrFail($user->id);
            if ((int) $u->cristais < $totalCost) {
                throw new InvalidArgumentException('Cristais insuficientes.');
            }
            $u->cristais = (int) $u->cristais - $totalCost;
            $u->save();

            $stack = $this->incrementStackLocked((int) $user->id, (int) $chest->id, $qty);

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
    private function purchasePremiumPadrao(User $user, int $qty): array
    {
        $costEach = (int) config('game.chests.premium_padrao.cost_moedas');
        $chest = $this->chestBySlugOrFail('premium_padrao');
        $totalCost = $costEach * $qty;

        return DB::transaction(function () use ($user, $totalCost, $costEach, $chest, $qty) {
            $u = User::query()->lockForUpdate()->findOrFail($user->id);
            if ((int) $u->moedas < $totalCost) {
                throw new InvalidArgumentException('Moedas insuficientes.');
            }
            $u->moedas = (int) $u->moedas - $totalCost;
            $u->save();

            $stack = $this->incrementStackLocked((int) $user->id, (int) $chest->id, $qty);

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

    private function chestBySlugOrFail(string $slug): Chest
    {
        $chest = Chest::query()
            ->where('slug', $slug)
            ->where('active', true)
            ->first();

        if (! $chest) {
            throw new InvalidArgumentException(
                'Baú não encontrado na base de dados (slug: '.$slug.'). Corra migrações e o ChestAndWeeklyPoolSeeder.'
            );
        }

        return $chest;
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
