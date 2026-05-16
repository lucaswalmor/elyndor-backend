<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Deck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeckController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $decks = Deck::where('user_id', $request->user()->id)
            ->with(['deckCards.card'])
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'nome' => $d->nome,
                'is_padrao' => $d->is_padrao,
                'cartas' => $d->deckCards->map(fn ($dc) => [
                    'card_id' => $dc->card_id,
                    'quantidade' => $dc->quantidade,
                    'nome' => $dc->card?->nome,
                ]),
            ]);

        return response()->json(['data' => $decks]);
    }
}
