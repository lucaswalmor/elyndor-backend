<?php

namespace App\Services\Deck;

use App\Models\Card;
use App\Models\Deck;
use App\Models\DeckCard;
use App\Models\User;
use App\Services\Collection\PlayerCollectionService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeckService
{
    public function __construct(
        private PlayerCollectionService $collection,
    ) {}

    public function listForUser(User $user): array
    {
        return $user->decks()
            ->with(['deckCards.card'])
            ->orderByDesc('is_padrao')
            ->orderBy('id')
            ->get()
            ->map(fn (Deck $d) => $this->formatDeck($d))
            ->all();
    }

    public function create(User $user, string $nome, array $cartas, bool $isPadrao = false): array
    {
        $max = config('game.progression.decks.max_per_user');
        if ($user->decks()->count() >= $max) {
            throw new InvalidArgumentException("Máximo de {$max} decks por jogador");
        }

        $this->validateCartas($user, $cartas);

        return DB::transaction(function () use ($user, $nome, $cartas, $isPadrao) {
            if ($isPadrao) {
                $user->decks()->update(['is_padrao' => false]);
            }

            $hasPadrao = $user->decks()->where('is_padrao', true)->exists();

            $deck = Deck::create([
                'user_id' => $user->id,
                'nome' => $nome,
                'is_padrao' => $isPadrao || ! $hasPadrao,
            ]);

            $this->syncDeckCards($deck, $cartas);

            return $this->formatDeck($deck->load(['deckCards.card']));
        });
    }

    public function update(User $user, int $deckId, ?string $nome, ?array $cartas, ?bool $isPadrao): array
    {
        $deck = $this->findOwnedDeck($user, $deckId);

        if ($cartas !== null) {
            $this->validateCartas($user, $cartas);
        }

        return DB::transaction(function () use ($user, $deck, $nome, $cartas, $isPadrao) {
            if ($isPadrao === true) {
                $user->decks()->where('id', '!=', $deck->id)->update(['is_padrao' => false]);
                $deck->is_padrao = true;
            } elseif ($isPadrao === false && $deck->is_padrao) {
                throw new InvalidArgumentException('É necessário ter um deck padrão');
            }

            if ($nome !== null) {
                $deck->nome = $nome;
            }

            $deck->save();

            if ($cartas !== null) {
                $deck->deckCards()->delete();
                $this->syncDeckCards($deck, $cartas);
            }

            return $this->formatDeck($deck->load(['deckCards.card']));
        });
    }

    public function delete(User $user, int $deckId): void
    {
        $deck = $this->findOwnedDeck($user, $deckId);

        if ($user->decks()->count() <= 1) {
            throw new InvalidArgumentException('Não é possível excluir o único deck');
        }

        DB::transaction(function () use ($user, $deck) {
            $wasDefault = $deck->is_padrao;
            $deck->delete();

            if ($wasDefault) {
                $user->decks()->orderBy('id')->first()?->update(['is_padrao' => true]);
            }
        });
    }

    public function assertPlayable(User $user, int $deckId): Deck
    {
        $deck = $this->findOwnedDeck($user, $deckId);
        $formatted = $this->formatDeck($deck->load(['deckCards.card']));

        if (! $formatted['valido']) {
            throw new InvalidArgumentException('Deck inválido para partida (precisa de 15 cartas válidas)');
        }

        return $deck;
    }

    private function findOwnedDeck(User $user, int $deckId): Deck
    {
        $deck = Deck::where('user_id', $user->id)->where('id', $deckId)->first();
        if (! $deck) {
            throw new InvalidArgumentException('Deck não encontrado');
        }

        return $deck;
    }

    /** @param  array<int, array{card_id: int, quantidade: int}>  $cartas */
    private function syncDeckCards(Deck $deck, array $cartas): void
    {
        foreach ($cartas as $row) {
            DeckCard::create([
                'deck_id' => $deck->id,
                'card_id' => (int) $row['card_id'],
                'quantidade' => (int) $row['quantidade'],
            ]);
        }
    }

    /** @param  array<int, array{card_id: int, quantidade: int}>  $cartas */
    private function validateCartas(User $user, array $cartas): void
    {
        $size = config('game.progression.decks.size');
        $limits = config('game.progression.decks.copy_limits');
        $owned = $this->collection->ownedMap($user);

        if (empty($cartas)) {
            throw new InvalidArgumentException('Informe as cartas do deck');
        }

        $total = 0;
        $byCard = [];

        foreach ($cartas as $row) {
            $cardId = (int) ($row['card_id'] ?? 0);
            $qty = (int) ($row['quantidade'] ?? 0);

            if ($cardId < 1 || $qty < 1) {
                throw new InvalidArgumentException('Carta ou quantidade inválida');
            }

            $byCard[$cardId] = ($byCard[$cardId] ?? 0) + $qty;
            $total += $qty;
        }

        if ($total !== $size) {
            throw new InvalidArgumentException("O deck deve ter exatamente {$size} cartas (atual: {$total})");
        }

        $cards = Card::whereIn('id', array_keys($byCard))->get()->keyBy('id');

        foreach ($byCard as $cardId => $qty) {
            $card = $cards->get($cardId);
            if (! $card || ! $card->ativo) {
                throw new InvalidArgumentException('Carta não disponível no catálogo');
            }

            $ownedQty = $owned[$cardId] ?? 0;
            if ($qty > $ownedQty) {
                throw new InvalidArgumentException("Você não possui cópias suficientes de {$card->nome}");
            }

            $maxCopies = $limits[$card->raridade] ?? 1;
            if ($qty > $maxCopies) {
                throw new InvalidArgumentException("Limite de {$maxCopies} cópia(s) de {$card->raridade} por deck");
            }
        }
    }

    private function formatDeck(Deck $deck): array
    {
        $size = config('game.progression.decks.size');
        $limits = config('game.progression.decks.copy_limits');
        $total = $deck->deckCards->sum('quantidade');
        $valido = $total === $size;

        $cartas = $deck->deckCards->map(function ($dc) use ($limits, &$valido) {
            $raridade = $dc->card?->raridade ?? 'comum';
            $max = $limits[$raridade] ?? 1;
            if ($dc->quantidade > $max) {
                $valido = false;
            }

            return [
                'card_id' => $dc->card_id,
                'quantidade' => $dc->quantidade,
                'nome' => $dc->card?->nome,
                'raridade' => $raridade,
                'linhagem' => $dc->card?->linhagem,
                'custo' => $dc->card?->custo,
                'ataque' => $dc->card?->ataque,
                'vida' => $dc->card?->vida,
                'imagem_path' => $dc->card?->imagem_path,
            ];
        })->values()->all();

        if ($total !== $size) {
            $valido = false;
        }

        return [
            'id' => $deck->id,
            'nome' => $deck->nome,
            'is_padrao' => $deck->is_padrao,
            'total_cartas' => $total,
            'valido' => $valido,
            'cartas' => $cartas,
        ];
    }
}
