<?php

namespace App\Services\Collection;

use App\Models\Card;
use App\Models\DeckCard;
use App\Models\PlayerCard;
use App\Models\PlayerLootDuplicate;
use App\Models\User;

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

    /**
     * Ganhos de uma carta: a coleção (`player_cards.quantidade`) sobe sempre.
     * Cópias além do limite usável no deck são também registadas em `player_loot_duplicates` (Repetidas).
     *
     * @return array{cristais: int, stashed_duplicate: bool}
     */
    public function applyCardGain(User $user, Card $card): array
    {
        $this->ensureSynced($user);

        $limits = config('game.progression.decks.copy_limits');
        $max = (int) ($limits[$card->raridade] ?? 3);

        $row = PlayerCard::firstOrNew([
            'user_id' => $user->id,
            'card_id' => $card->id,
        ]);
        $current = (int) ($row->exists ? $row->quantidade : 0);
        $stashed = $current >= $max;

        if (! $row->exists) {
            $row->quantidade = 1;
            $row->save();
        } else {
            $row->increment('quantidade');
        }

        if ($stashed) {
            PlayerLootDuplicate::addStack(
                (int) $user->id,
                PlayerLootDuplicate::stackKeyForCard($card->id),
                $card->id,
                null,
                null,
                1,
            );
        }

        return [
            'cristais' => 0,
            'stashed_duplicate' => $stashed,
        ];
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

    public function catalogForUser(User $user): \Illuminate\Support\Collection
    {
        $this->ensureSynced($user);
        $owned = $this->ownedMap($user);

        return Card::query()
            ->where('ativo', true)
            ->where('colecionavel', true)
            ->with('skills')
            ->orderBy('linhagem')
            ->orderBy('custo')
            ->get()
            ->map(fn (Card $card) => [
                'id' => $card->id,
                'nome' => $card->nome,
                'slug' => $card->slug,
                'descricao' => $card->descricao,
                'linhagem' => $card->linhagem,
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
