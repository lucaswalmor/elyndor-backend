<?php

namespace App\Services\Collection;

use App\Models\Card;
use App\Models\DeckCard;
use App\Models\PlayerCard;
use App\Models\User;
use Illuminate\Support\Collection;

class PlayerCollectionService
{
    /**
     * Garante inventário para usuários antigos (só tinham deck_cards).
     */
    public function ensureSynced(User $user): void
    {
        if ($user->playerCards()->exists()) {
            return;
        }

        $cardIds = DeckCard::query()
            ->whereIn('deck_id', $user->decks()->pluck('id'))
            ->selectRaw('card_id, MAX(quantidade) as qty')
            ->groupBy('card_id')
            ->pluck('qty', 'card_id');

        foreach ($cardIds as $cardId => $qty) {
            PlayerCard::create([
                'user_id' => $user->id,
                'card_id' => $cardId,
                'quantidade' => (int) $qty,
            ]);
        }
    }

    /** @param  array<int, int>  $cardQuantities  card_id => quantidade */
    public function grant(User $user, array $cardQuantities): void
    {
        foreach ($cardQuantities as $cardId => $qty) {
            if ($qty < 1) {
                continue;
            }
            $row = PlayerCard::firstOrNew([
                'user_id' => $user->id,
                'card_id' => $cardId,
            ]);
            $row->quantidade = ($row->exists ? $row->quantidade : 0) + $qty;
            $row->save();
        }
    }

    /** @return array<int, int> card_id => quantidade possuída */
    public function ownedMap(User $user): array
    {
        $this->ensureSynced($user);

        return $user->playerCards()
            ->pluck('quantidade', 'card_id')
            ->map(fn ($q) => (int) $q)
            ->all();
    }

    public function catalogForUser(User $user): Collection
    {
        $this->ensureSynced($user);
        $owned = $this->ownedMap($user);

        return Card::query()
            ->where('ativo', true)
            ->where('colecionavel', true)
            ->with('skills')
            ->orderBy('faccao')
            ->orderBy('custo')
            ->get()
            ->map(fn (Card $card) => [
                'id' => $card->id,
                'nome' => $card->nome,
                'slug' => $card->slug,
                'descricao' => $card->descricao,
                'faccao' => $card->faccao,
                'classe' => $card->classe,
                'raridade' => $card->raridade,
                'tipo' => $card->tipo,
                'custo' => $card->custo,
                'ataque' => $card->ataque,
                'vida' => $card->vida,
                'imagem_path' => $card->imagem_path,
                'possui' => ($owned[$card->id] ?? 0) > 0,
                'quantidade' => $owned[$card->id] ?? 0,
                'skills' => $card->skills->map(fn ($s) => [
                    'nome' => $s->nome,
                    'tipo' => $s->tipo,
                    'gatilho' => $s->gatilho,
                ]),
            ]);
    }
}
