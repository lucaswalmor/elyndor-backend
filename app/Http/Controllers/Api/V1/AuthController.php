<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\RegisterService;
use App\Services\Auth\UserSessionTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private RegisterService $registerService,
        private UserSessionTracker $sessions,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registerService->register(
            Arr::except($request->validated(), ['accept_terms'])
        );
        $user->tokens()->delete();
        $this->sessions->beginSession($request, $user);

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

        $this->sessions->beginSession($request, $user);

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
        $user = $request->user();
        $this->sessions->touch($request, $user);
        $user->load(['playerLevel', 'avatar']);

        return new UserResource($user);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->sendResetLink($request->validated());

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Aguarde antes de solicitar um novo envio.',
            ], 429);
        }

        return response()->json([
            'message' => 'Se existir uma conta com este e-mail, enviámos um link para redefinir a senha.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $status = Password::broker()->reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
                'token' => $validated['token'],
            ],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        return response()->json(['message' => 'Senha redefinida com sucesso. Faça login.']);
    }
}
