<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StreamerProfile;
use App\Services\Streamer\StreamerInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class StreamerProfileController extends Controller
{
    public function __construct(
        private StreamerInviteService $streamerInvites,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $perfil = StreamerProfile::query()->where('user_id', $usuario->id)->first();

        return response()->json([
            'data' => [
                'is_content_creator' => (bool) $usuario->is_content_creator,
                'pode_ativar_codigo' => ! $usuario->is_content_creator && $usuario->streamer_invite_token === null,
                'divulgacao' => $this->streamerInvites->formatarPerfil($perfil),
            ],
        ]);
    }

    public function activate(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'codigo_streamer' => 'required|string|max:64',
        ]);

        $resultado = $this->streamerInvites->tentarAtivar(
            $request->user(),
            $dados['codigo_streamer'],
        );

        if (! $resultado['ativado']) {
            $status = $resultado['mensagem'] ? 400 : 400;

            return response()->json(['message' => $resultado['mensagem'] ?? 'Não foi possível ativar.'], $status);
        }

        $request->user()->refresh();

        return response()->json([
            'message' => $resultado['mensagem'],
            'data' => [
                'is_content_creator' => true,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'youtube_url' => 'nullable|string|max:500',
            'instagram_url' => 'nullable|string|max:500',
            'whatsapp_group_url' => 'nullable|string|max:500',
            'twitch_url' => 'nullable|string|max:500',
            'other_url' => 'nullable|string|max:500',
            'bio' => 'nullable|string|max:500',
        ]);

        try {
            $perfil = $this->streamerInvites->atualizarPerfil($request->user(), $dados);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 400);
        }

        return response()->json(['data' => $perfil]);
    }
}
