<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $level = $this->playerLevel;

        return [
            'id'               => $this->id,
            'nickname'         => $this->nickname,         // público (fila, partida, perfil)
            'nivel'            => $level?->nivel ?? 1,
            'xp_atual'         => $level?->xp_atual ?? 0,
            'xp_proximo_nivel' => $level?->xpParaProximoNivel() ?? 358,
            'moedas'           => $this->moedas ?? 0,
            'cristais'         => $this->cristais ?? 0,
            'card_back_slug'   => $this->card_back_slug ?? 'padrao',
            // name e email omitidos: dados sensíveis, nunca expostos via API pública
        ];
    }
}
