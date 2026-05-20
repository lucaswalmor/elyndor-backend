<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Chest;
use App\Services\Economy\DuplicateDisenchantService;
use App\Models\PlayerChestStack;
use App\Models\PlayerLootDuplicate;
use App\Services\Economy\CosmeticChestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InventoryController extends Controller
{
    public function chestStacks(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $rows = PlayerChestStack::query()
            ->where('user_id', $userId)
            ->where('quantity', '>', 0)
            ->whereHas('chest', fn ($q) => $q->where('active', true))
            ->with(['chest' => fn ($q) => $q->select(
                'id', 'slug', 'name', 'description'
            )])
            ->orderByDesc('quantity')
            ->get();

        $stacks = $rows->map(fn (PlayerChestStack $s) => [
            'chest_id' => $s->chest_id,
            'quantity' => (int) $s->quantity,
            'chest' => [
                'id' => $s->chest->id,
                'slug' => $s->chest->slug,
                'name' => $s->chest->name,
                'description' => $s->chest->description,
            ],
        ]);

        return response()->json(['stacks' => $stacks]);
    }

    public function openCosmeticChest(
        Request $request,
        CosmeticChestService $chests,
    ): JsonResponse {
        $data = $request->validate([
            'chest_id' => ['required', 'integer', 'exists:chests,id'],
        ]);

        $chestId = (int) $data['chest_id'];

        $chest = Chest::query()
            ->whereKey($chestId)
            ->where('active', true)
            ->first();

        if (! $chest) {
            return response()->json(['message' => 'Baú indisponível ou inativo.'], 422);
        }

        try {
            return response()->json($chests->openOne($request->user(), $chestId));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function chestPreview(
        string $slug,
        CosmeticChestService $chests,
    ): JsonResponse {
        try {
            return response()->json($chests->previewPool($slug));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /** Itens repetidos (excedentes), estilo inventário de duplicatas — só leitura por agora. */
    public function lootDuplicates(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $rows = PlayerLootDuplicate::query()
            ->where('user_id', $userId)
            ->where('quantity', '>', 0)
            ->orderByDesc('quantity')
            ->orderBy('stack_key')
            ->get();

        $cardIds = $rows->pluck('card_id')->filter()->unique()->all();
        $cards = $cardIds === []
            ? collect()
            : Card::query()->whereIn('id', $cardIds)->get()->keyBy('id');

        $disenchant = app(DuplicateDisenchantService::class);

        $items = $rows->map(function (PlayerLootDuplicate $row) use ($cards, $disenchant) {
            $preview = $disenchant->resolve($row);
            if ($row->card_id) {
                $c = $cards->get($row->card_id);

                return [
                    'id' => $row->id,
                    'stack_key' => $row->stack_key,
                    'kind' => 'card',
                    'quantity' => (int) $row->quantity,
                    'disenchant_preview' => $preview,
                    'card' => $c ? [
                        'id' => $c->id,
                        'nome' => $c->nome,
                        'slug' => $c->slug,
                        'raridade' => $c->raridade,
                        'faccao' => $c->faccao,
                        'imagem_path' => $c->imagem_path,
                    ] : [
                        'id' => (int) $row->card_id,
                        'nome' => '(carta removida)',
                        'slug' => null,
                        'raridade' => 'comum',
                        'faccao' => null,
                        'imagem_path' => null,
                    ],
                ];
            }

            return [
                'id' => $row->id,
                'stack_key' => $row->stack_key,
                'kind' => 'cosmetic',
                'quantity' => (int) $row->quantity,
                'disenchant_preview' => $preview,
                'asset_category' => $row->asset_category,
                'asset_key' => $row->asset_key,
            ];
        })->values()->all();

        $totalQty = (int) collect($items)->sum('quantity');

        return response()->json([
            'items' => $items,
            'total_stacks' => count($items),
            'total_quantity' => $totalQty,
        ]);
    }

    public function disenchantDuplicate(Request $request, DuplicateDisenchantService $disenchant): JsonResponse
    {
        $data = $request->validate([
            'stack_key' => ['required', 'string'],
        ]);

        $userId = (int) $request->user()->id;
        $stackKey = $data['stack_key'];

        $row = PlayerLootDuplicate::query()
            ->where('user_id', $userId)
            ->where('stack_key', $stackKey)
            ->where('quantity', '>', 0)
            ->first();

        if (! $row) {
            return response()->json(['message' => 'Item repetido não encontrado ou já desencantado.'], 404);
        }

        $user = $request->user();
        $reward = $disenchant->resolve($row);
        $gainType = $reward['gain_type'];
        $gainAmount = $reward['gain_amount'];

        if ($gainType === 'cristais') {
            $user->cristais = (int) $user->cristais + $gainAmount;
        } else {
            $user->moedas = (int) $user->moedas + $gainAmount;
        }

        $row->quantity = (int) $row->quantity - 1;
        if ($row->quantity <= 0) {
            $row->delete();
        } else {
            $row->save();
        }

        $user->save();

        $labelMoeda = $gainType === 'cristais' ? 'Cristais' : 'Moedas';

        return response()->json([
            'message' => "Desfragmentado com sucesso! Você recebeu +{$gainAmount} {$labelMoeda}.",
            'gain_type' => $gainType,
            'gain_amount' => $gainAmount,
            'balance' => [
                'moedas' => $user->moedas,
                'cristais' => $user->cristais,
            ],
            'remaining_quantity' => $row->quantity,
        ]);
    }
}
