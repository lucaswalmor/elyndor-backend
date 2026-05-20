<?php

namespace App\Services\Streamer;

use App\Models\StreamerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StreamerInviteService
{
    /** @return list<string> */
    public function tokensDisponiveisNaEnv(): array
    {
        return config('elyndor.streamer_invites', []);
    }

    public function tokenEhValidoNaEnv(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        foreach ($this->tokensDisponiveisNaEnv() as $candidato) {
            if (hash_equals($candidato, $token)) {
                return true;
            }
        }

        return false;
    }

    public function tokenJaFoiUtilizado(string $token): bool
    {
        return User::query()->where('streamer_invite_token', trim($token))->exists();
    }

    /**
     * Ativa criador se token válido e ainda não usado.
     *
     * @return array{ativado: bool, mensagem: ?string}
     */
    public function tentarAtivar(User $usuario, ?string $token): array
    {
        if ($usuario->is_content_creator) {
            return ['ativado' => false, 'mensagem' => 'Conta já é de criador.'];
        }

        $token = trim((string) $token);
        if ($token === '') {
            return ['ativado' => false, 'mensagem' => null];
        }

        if (! $this->tokenEhValidoNaEnv($token)) {
            return ['ativado' => false, 'mensagem' => 'Código de criador inválido.'];
        }

        if ($this->tokenJaFoiUtilizado($token)) {
            return ['ativado' => false, 'mensagem' => 'Este código já foi utilizado.'];
        }

        DB::transaction(function () use ($usuario, $token) {
            $claim = $usuario->nickname.':'.$token;
            $usuario->forceFill([
                'is_content_creator' => true,
                'streamer_invite_token' => $token,
                'streamer_invite_claim' => $claim,
            ])->save();

            StreamerProfile::query()->firstOrCreate(['user_id' => $usuario->id]);
        });

        return ['ativado' => true, 'mensagem' => 'Conta de criador ativada com sucesso.'];
    }

    public function formatarPerfil(?StreamerProfile $perfil): ?array
    {
        if ($perfil === null) {
            return null;
        }

        return [
            'youtube_url' => $perfil->youtube_url,
            'instagram_url' => $perfil->instagram_url,
            'whatsapp_group_url' => $perfil->whatsapp_group_url,
            'twitch_url' => $perfil->twitch_url,
            'other_url' => $perfil->other_url,
            'bio' => $perfil->bio,
        ];
    }

    public function atualizarPerfil(User $usuario, array $dados): array
    {
        if (! $usuario->is_content_creator) {
            throw new \InvalidArgumentException('Apenas criadores podem editar a divulgação.');
        }

        $perfil = StreamerProfile::query()->firstOrCreate(['user_id' => $usuario->id]);

        if (isset($dados['bio']) && $dados['bio'] !== null) {
            $this->validarUrlOuVazio($dados, 'bio', false);
            app(\App\Services\Moderation\TextoModeracaoService::class)
                ->validarTextoPublico((string) $dados['bio'], 'Bio');
        }

        foreach (['youtube_url', 'instagram_url', 'whatsapp_group_url', 'twitch_url', 'other_url'] as $campo) {
            if (array_key_exists($campo, $dados)) {
                $this->validarUrlOuVazio($dados, $campo, true);
            }
        }

        $perfil->fill([
            'youtube_url' => $dados['youtube_url'] ?? $perfil->youtube_url,
            'instagram_url' => $dados['instagram_url'] ?? $perfil->instagram_url,
            'whatsapp_group_url' => $dados['whatsapp_group_url'] ?? $perfil->whatsapp_group_url,
            'twitch_url' => $dados['twitch_url'] ?? $perfil->twitch_url,
            'other_url' => $dados['other_url'] ?? $perfil->other_url,
            'bio' => $dados['bio'] ?? $perfil->bio,
        ]);
        $perfil->save();

        return $this->formatarPerfil($perfil) ?? [];
    }

    /** @param  array<string, mixed>  $dados */
    private function validarUrlOuVazio(array $dados, string $campo, bool $exigeUrl): void
    {
        $valor = $dados[$campo] ?? null;
        if ($valor === null || $valor === '') {
            return;
        }

        if (! is_string($valor) || strlen($valor) > 500) {
            throw new \InvalidArgumentException('Link inválido.');
        }

        if ($exigeUrl && ! filter_var($valor, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Informe uma URL válida (https://...).');
        }
    }
}
