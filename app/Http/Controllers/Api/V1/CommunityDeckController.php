<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Community\CommunityDeckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CommunityDeckController extends Controller
{
    public function __construct(
        private CommunityDeckService $communityDecks,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = [
            'sort' => $request->query('sort', 'popular'),
            'linhagem' => $request->query('linhagem'),
            'tag' => $request->query('tag'),
            'recent' => $request->boolean('recent'),
            'streamer_only' => $request->boolean('streamer_only'),
            'can_copy' => $request->boolean('can_copy'),
            'per_page' => $request->query('per_page'),
        ];

        $resultado = $this->communityDecks->listar($request->user(), $filtros);

        return response()->json($resultado);
    }

    public function minhas(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->communityDecks->minhasPublicacoes($request->user()),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->communityDecks->detalhe($request->user(), $id);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'deck_id' => 'required|integer',
            'nome' => 'required|string|max:80',
            'descricao' => 'nullable|string|max:500',
            'linhagem_principal' => 'required|string|max:40',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:30',
        ]);

        try {
            $publicado = $this->communityDecks->publicar(
                $request->user(),
                (int) $dados['deck_id'],
                $dados['nome'],
                (string) ($dados['descricao'] ?? ''),
                $dados['linhagem_principal'],
                $dados['tags'] ?? [],
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 400);
        }

        return response()->json(['data' => $publicado], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->communityDecks->despublicar($request->user(), $id);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['sucesso' => true]);
    }

    public function like(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->communityDecks->curtir($request->user(), $id);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['data' => $data]);
    }

    public function unlike(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->communityDecks->removerCurtida($request->user(), $id);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['data' => $data]);
    }

    public function copy(Request $request, int $id): JsonResponse
    {
        try {
            $deck = $this->communityDecks->copiar($request->user(), $id);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 400);
        }

        return response()->json(['data' => $deck]);
    }

    public function import(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'ely_code' => 'required|string|max:32',
        ]);

        try {
            $deck = $this->communityDecks->importarPorElyCode($request->user(), $dados['ely_code']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 400);
        }

        return response()->json(['data' => $deck]);
    }
}
