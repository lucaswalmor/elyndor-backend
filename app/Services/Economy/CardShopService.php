<?php

namespace App\Services\Economy;

use App\Models\Card;
use App\Models\User;
use App\Services\Collection\PlayerCollectionService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CardShopService
{
    public function __construct(
        private PlayerCollectionService $collection,
    ) {}

    /** @return list<array<string, mixed>> */
    public function catalog(User $user): array
    {
        $this->collection->ensureSynced($user);
        $owned = $this->collection->ownedMap($user);
        $prices = config('game.progression.card_shop_prices_cristais');

        return Card::query()
            ->where('ativo', true)
            ->where('colecionavel', true)
            ->orderBy('linhagem')
            ->orderBy('custo')
            ->get()
            ->map(function (Card $card) use ($owned, $prices) {
                $r = $card->raridade;
                $price = (int) ($prices[$r] ?? 0);

                return [
                    'id' => $card->id,
                    'nome' => $card->nome,
                    'slug' => $card->slug,
                    'linhagem' => $card->linhagem,
                    'raridade' => $r,
                    'custo' => $card->custo,
                    'preco_cristais' => $price,
                    'imagem_path' => $card->imagem_path,
                    'quantidade' => $owned[$card->id] ?? 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{duplicate_cristais: int, duplicate_stashed: bool, balance: array{cristais: int, moedas: int}}
     */
    public function buy(User $user, int $cardId): array
    {
        $card = Card::query()->find($cardId);
        if (! $card || ! $card->ativo || ! $card->colecionavel) {
            throw new InvalidArgumentException('Carta indisponível na loja.');
        }

        $prices = config('game.progression.card_shop_prices_cristais');
        $price = (int) ($prices[$card->raridade] ?? 0);
        if ($price < 1) {
            throw new InvalidArgumentException('Esta carta não tem preço configurado.');
        }

        return DB::transaction(function () use ($user, $card, $price) {
            $u = User::query()->lockForUpdate()->findOrFail($user->id);
            if ((int) $u->cristais < $price) {
                throw new InvalidArgumentException('Cristais insuficientes.');
            }
            $u->cristais = (int) $u->cristais - $price;
            $u->save();

            $gain = $this->collection->applyCardGain($u->fresh(), $card);
            $u->refresh();

            return [
                'duplicate_cristais' => $gain['cristais'],
                'duplicate_stashed' => $gain['stashed_duplicate'],
                'balance' => [
                    'cristais' => (int) $u->cristais,
                    'moedas' => (int) $u->moedas,
                ],
            ];
        });
    }
}
