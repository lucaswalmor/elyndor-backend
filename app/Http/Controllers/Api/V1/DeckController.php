<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Deck\DeckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DeckController extends Controller
{
    public function __construct(
        private DeckService $decks,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->decks->listForUser($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nome' => 'required|string|max:80',
            'is_padrao' => 'sometimes|boolean',
            'cartas' => 'required|array|min:1',
            'cartas.*.card_id' => 'required|integer',
            'cartas.*.quantidade' => 'required|integer|min:1|max:3',
        ]);

        try {
            $deck = $this->decks->create(
                $request->user(),
                $data['nome'],
                $data['cartas'],
                (bool) ($data['is_padrao'] ?? false),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json(['data' => $deck], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'nome' => 'sometimes|string|max:80',
            'is_padrao' => 'sometimes|boolean',
            'cartas' => 'sometimes|array|min:1',
            'cartas.*.card_id' => 'required_with:cartas|integer',
            'cartas.*.quantidade' => 'required_with:cartas|integer|min:1|max:3',
        ]);

        try {
            $deck = $this->decks->update(
                $request->user(),
                $id,
                $data['nome'] ?? null,
                $data['cartas'] ?? null,
                array_key_exists('is_padrao', $data) ? (bool) $data['is_padrao'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json(['data' => $deck]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->decks->delete($request->user(), $id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json(['sucesso' => true]);
    }
}
