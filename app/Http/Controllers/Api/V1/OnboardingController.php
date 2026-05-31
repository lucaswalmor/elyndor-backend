<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OnboardingController extends Controller
{
    public function __construct(
        private OnboardingService $onboarding,
    ) {}

    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->onboarding->status($request->user()),
        ]);
    }

    public function pularTutorial(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => $this->onboarding->pularTutorial($request->user()),
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function iniciarTutorial(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => $this->onboarding->iniciarPartidaTutorial($request->user()),
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function concluirTutorial(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->onboarding->marcarTutorialConcluido($request->user()),
        ]);
    }

    public function escolherDeck(Request $request): JsonResponse
    {
        $preset = (string) $request->input('preset', '');

        try {
            return response()->json([
                'data' => $this->onboarding->escolherDeckInicial($request->user(), $preset),
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function resgatarRecompensa(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => $this->onboarding->resgatarRecompensaTutorial($request->user()),
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
