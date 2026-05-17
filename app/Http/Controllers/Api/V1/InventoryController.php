<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Chest;
use App\Models\PlayerChestStack;
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
}
