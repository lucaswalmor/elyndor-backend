<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Collection\PlayerCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function __construct(
        private PlayerCollectionService $collection,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $cartas = $this->collection->catalogForUser($request->user());

        return response()->json([
            'data' => $cartas,
            'total' => $cartas->count(),
            'possui' => $cartas->where('possui', true)->count(),
        ]);
    }
}
