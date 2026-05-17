<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Auth\RegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private RegisterService $registerService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registerService->register($request->validated());
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $user->load(['playerLevel', 'avatar']);
        $user->tokens()->delete();

        UserSession::query()->create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'device_id' => $request->filled('device_id') ? substr((string) $request->device_id, 0, 80) : null,
            'client_type' => $request->input('client_type', 'web'),
            'last_seen_at' => now(),
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout realizado com sucesso']);
    }

    public function me(Request $request): UserResource
    {
        $request->user()->load(['playerLevel', 'avatar']);

        return new UserResource($request->user());
    }
}
