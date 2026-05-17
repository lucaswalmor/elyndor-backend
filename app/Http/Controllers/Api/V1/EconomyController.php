<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
            'type' => ['required', 'string', Rule::in(['cristal_basico', 'premium_padrao'])],
        ]);

        try {
            return response()->json($this->chests->purchaseForInventory($request->user(), $data['type']));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function chestPrices(): JsonResponse
    {
        return response()->json([
            'cristal_basico' => [
                'cost_cristais' => (int) config('game.chests.cristal_basico.cost_cristais'),
            ],
            'premium_padrao' => [
                'cost_moedas' => (int) config('game.chests.premium_padrao.cost_moedas'),
            ],
            'pity_epic_every' => (int) config('game.chests.pity_epic_every'),
        ]);
    }
}
