<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Chest;
use App\Models\ChestShopPurchase;
use App\Services\Economy\ChestOpeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class EconomyController extends Controller
{
    public function __construct(
        private ChestOpeningService $chests,
    ) {}

    public function chestOpen(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => [
                'required',
                'string',
                'max:80',
                Rule::exists('chests', 'slug')->where(
                    fn ($q) => $q->where('available_in_shop', true)->where('active', true)
                ),
            ],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:999999'],
        ]);

        $qty = (int) ($data['quantity'] ?? 1);
        if ($qty < 1) {
            $qty = 1;
        }

        try {
            return response()->json($this->chests->purchaseForInventory($request->user(), $data['type'], $qty));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function chestPrices(): JsonResponse
    {
        $chests = Chest::query()
            ->where('available_in_shop', true)
            ->where('active', true)
            ->orderBy('sort_order')
            ->get(['slug', 'name', 'description', 'cost_cristais', 'cost_moedas', 'pity_epic_every']);

        return response()->json([
            'chests' => $chests->map(fn (Chest $c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'description' => $c->description,
                'cost_cristais' => $c->cost_cristais,
                'cost_moedas' => $c->cost_moedas,
                'pity_epic_every' => $c->pity_epic_every,
            ])->values()->all(),
            'pity_epic_every' => (int) config('game.chests.pity_epic_every'),
        ]);
    }

    public function chestPurchaseHistory(Request $request): JsonResponse
    {
        $page = ChestShopPurchase::query()
            ->where('user_id', $request->user()->id)
            ->with('chest:id,slug,name')
            ->orderByDesc('id')
            ->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (ChestShopPurchase $p) => [
                'id' => $p->id,
                'chest_slug' => $p->chest?->slug,
                'chest_name' => $p->chest?->name,
                'quantity' => $p->quantity,
                'currency' => $p->currency,
                'unit_price' => $p->unit_price,
                'total_paid' => $p->total_paid,
                'created_at' => $p->created_at?->toIso8601String(),
            ])->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
