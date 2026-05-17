<?php

namespace App\Services\Economy;

use App\Models\Card;
use App\Models\User;
use App\Services\Collection\PlayerCollectionService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ChestOpeningService
{
    public function __construct(
        private PlayerCollectionService $collection,
    ) {}

    /**
     * @return array{
     *     chest: string,
     *     duplicate_cristais: int,
     *     card: array{id: int, nome: string, raridade: string},
     *     cost_cristais?: int,
     *     cost_moedas?: int,
     *     pity_after?: int,
     *     balance: array{cristais: int, moedas: int}
     * }
     */
    public function open(User $user, string $kind): array
    {
        return match ($kind) {
            'cristal_basico' => $this->openCristalBasico($user),
            'premium_padrao' => $this->openPremiumPadrao($user),
            default => throw new InvalidArgumentException('Tipo de baú inválido.'),
        };
    }

    /** @return array<string, mixed> */
    private function openCristalBasico(User $user): array
    {
        $cost = (int) config('game.chests.cristal_basico.cost_cristais');

        return DB::transaction(function () use ($user, $cost) {
            $u = User::query()->lockForUpdate()->findOrFail($user->id);
            if ((int) $u->cristais < $cost) {
                throw new InvalidArgumentException('Cristais insuficientes.');
            }
            $u->cristais = (int) $u->cristais - $cost;
            $u->save();

            $card = $this->randomCollectibleByRarity('comum');
            if (! $card) {
                $card = $this->fallbackCollectibleCard();
            }

            $cristalsConv = $this->collection->applyCardGain($u->fresh(), $card);
            $u->refresh();

            return [
                'chest' => 'cristal_basico',
                'cost_cristais' => $cost,
                'card' => [
                    'id' => $card->id,
                    'nome' => $card->nome,
                    'raridade' => $card->raridade,
                ],
                'duplicate_cristais' => $cristalsConv,
                'balance' => [
                    'cristais' => (int) $u->cristais,
                    'moedas' => (int) $u->moedas,
                ],
            ];
        });
    }

    /** @return array<string, mixed> */
    private function openPremiumPadrao(User $user): array
    {
        $cost = (int) config('game.chests.premium_padrao.cost_moedas');
        $pityEvery = max(1, (int) config('game.chests.pity_epic_every'));

        return DB::transaction(function () use ($user, $cost, $pityEvery) {
            $u = User::query()->lockForUpdate()->findOrFail($user->id);
            if ((int) $u->moedas < $cost) {
                throw new InvalidArgumentException('Moedas insuficientes.');
            }
            $u->moedas = (int) $u->moedas - $cost;

            $pity = (int) $u->premium_chest_pity;
            $weights = config('game.chests.spin_weights_premium');
            if (! is_array($weights)) {
                $weights = [];
            }

            $forcedEpicPlus = $pity >= $pityEvery - 1;
            if ($forcedEpicPlus) {
                $subset = array_intersect_key($weights, array_flip(['epica', 'lendaria']));
                $rarity = ((float) array_sum($subset) > 0)
                    ? $this->weightedPick($subset)
                    : 'epica';
            } else {
                $rarity = $this->weightedPick($weights);
            }

            $card = $this->randomCollectibleByRarity($rarity);
            if (! $card) {
                $card = $this->fallbackCollectibleCard();
                $rarity = $card->raridade;
            }

            if (in_array($rarity, ['epica', 'lendaria'], true)) {
                $u->premium_chest_pity = 0;
            } else {
                $u->premium_chest_pity = min(255, $pity + 1);
            }
            $u->save();

            $cristalsConv = $this->collection->applyCardGain($u->fresh(), $card);
            $u->refresh();

            return [
                'chest' => 'premium_padrao',
                'cost_moedas' => $cost,
                'card' => [
                    'id' => $card->id,
                    'nome' => $card->nome,
                    'raridade' => $card->raridade,
                ],
                'duplicate_cristais' => $cristalsConv,
                'pity_after' => (int) $u->premium_chest_pity,
                'balance' => [
                    'cristais' => (int) $u->cristais,
                    'moedas' => (int) $u->moedas,
                ],
            ];
        });
    }

    /**
     * @param  array<string, float|int>  $weights
     */
    private function weightedPick(array $weights): string
    {
        $sum = (float) array_sum($weights);
        if ($sum <= 0) {
            return 'comum';
        }
        $pick = mt_rand() / mt_getrandmax() * $sum;
        $acc = 0.0;
        foreach ($weights as $k => $w) {
            $acc += (float) $w;
            if ($pick <= $acc) {
                return (string) $k;
            }
        }

        $first = array_key_first($weights);

        return $first !== null ? (string) $first : 'comum';
    }

    private function randomCollectibleByRarity(string $rarity): ?Card
    {
        return Card::query()
            ->where('ativo', true)
            ->where('colecionavel', true)
            ->where('raridade', $rarity)
            ->inRandomOrder()
            ->first();
    }

    private function fallbackCollectibleCard(): Card
    {
        return Card::query()
            ->where('ativo', true)
            ->where('colecionavel', true)
            ->where('raridade', 'comum')
            ->inRandomOrder()
            ->firstOrFail();
    }
}
