<?php

namespace App\Http\Resources;

use App\Services\Ranked\RankedService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Perfil público (visitante) — sem email, saldos ou inventário. */
class PublicProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $level = $this->playerLevel;
        $ranked = app(RankedService::class);
        $rw = (int) ($this->ranked_wins ?? 0);
        $rl = (int) ($this->ranked_losses ?? 0);
        $total = $rw + $rl;
        $pts = (int) ($this->ranked_points ?? 0);

        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'nivel' => $level?->nivel ?? 1,
            'avatar_slug' => $this->avatar?->slug,
            'avatar_label' => $this->avatar?->label,
            'avatar_image_file' => $this->avatar?->image_file,
            'card_back_slug' => $this->card_back_slug ?? 'padrao',
            'profile_bg_slug' => $this->profile_bg_slug ?? 'padrao',
            'ranked_points' => $pts,
            'divisao' => $ranked->divisionKeyForPoints($pts),
            'divisao_label' => $ranked->divisionLabelForPoints($pts),
            'ranked_wins' => $rw,
            'ranked_losses' => $rl,
            'ranked_winrate' => $total > 0 ? round($rw / $total, 4) : null,
        ];
    }
}
