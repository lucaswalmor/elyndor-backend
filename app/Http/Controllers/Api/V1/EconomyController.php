<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Chest;
use App\Models\ChestShopPurchase;
use App\Models\PlayerChestStack;
use App\Services\Economy\ChestOpeningService;
use App\Services\Economy\ChestRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class EconomyController extends Controller
{
    public function __construct(
        private ChestOpeningService $chests,
        private ChestRefundService $refunds,
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

        $items = collect($page->items());
        $chestIds = $items->pluck('chest_id')->unique()->filter()->values()->all();

        $qtyByChest = [];
        if ($chestIds !== []) {
            $qtyByChest = PlayerChestStack::query()
                ->where('user_id', $request->user()->id)
                ->whereIn('chest_id', $chestIds)
                ->get(['chest_id', 'quantity'])
                ->mapWithKeys(fn (PlayerChestStack $s): array => [(int) $s->chest_id => (int) $s->quantity])
                ->all();
        }

        /** @var array<int, array<string, mixed>> $extraByPid */
        $extraByPid = $this->refunds->refundMetaByPurchaseIds($items->all(), $qtyByChest);

        return response()->json([
            'data' => $items->map(fn (ChestShopPurchase $p) => array_merge([
                'id' => $p->id,
                'chest_slug' => $p->chest?->slug,
                'chest_name' => $p->chest?->name,
                'quantity' => $p->quantity,
                'currency' => $p->currency,
                'unit_price' => $p->unit_price,
                'total_paid' => $p->total_paid,
                'created_at' => $p->created_at?->toIso8601String(),
            ], $extraByPid[(int) $p->id] ?? []))->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'purchase_refund' => [
                    'timezone' => (string) config('game.chests.purchase_refund.timezone', 'America/Sao_Paulo'),
                    'window_hours' => (int) config('game.chests.purchase_refund.window_hours', 24),
                    /** Valor na devolução usa sempre {@see ChestShopPurchase::$total_paid} registado à compra. */
                    'refund_source' => 'total_paid_snapshotted',
                ],
            ],
        ]);
    }

    public function refundChestPurchase(Request $request, ChestShopPurchase $purchase): JsonResponse
    {
        if ((int) $purchase->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        try {
            $user = $this->refunds->refundCoinsChestPurchase($request->user(), $purchase);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Reembolso concluído. As moedas registadas nesta compra foram creditadas novamente.',
            'balance' => [
                'moedas' => (int) $user->moedas,
                'cristais' => (int) $user->cristais,
            ],
            'purchase_id' => $purchase->id,
        ]);
    }
}
