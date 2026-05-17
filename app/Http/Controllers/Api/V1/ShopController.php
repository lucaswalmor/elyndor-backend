<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Economy\CardShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ShopController extends Controller
{
    public function __construct(
        private CardShopService $shop,
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        return response()->json(['cards' => $this->shop->catalog($request->user())]);
    }

    public function buy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'card_id' => ['required', 'integer', 'exists:cards,id'],
        ]);

        try {
            return response()->json($this->shop->buy($request->user(), (int) $data['card_id']));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
