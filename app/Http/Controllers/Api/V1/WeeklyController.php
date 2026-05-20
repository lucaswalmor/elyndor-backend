<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Economy\WeeklyRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WeeklyController extends Controller
{
    public function __construct(
        private WeeklyRewardService $weekly,
    ) {}

    public function status(Request $request): JsonResponse
    {
        return response()->json($this->weekly->status($request->user()));
    }

    public function claim(Request $request): JsonResponse
    {
        try {
            return response()->json($this->weekly->claim($request->user()));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function dismissModal(Request $request): JsonResponse
    {
        $this->weekly->dismissModal($request->user());

        return response()->json($this->weekly->status($request->user()));
    }
}
