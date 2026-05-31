<?php

namespace App\Http\Resources;

use App\Services\Ranked\RankedService;
use App\Support\UserMatchStats;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $level = $this->playerLevel;
        $ranked = app(RankedService::class);
        $pts = (int) ($this->ranked_points ?? 0);
        $rw = (int) ($this->ranked_wins ?? 0);
        $rl = (int) ($this->ranked_losses ?? 0);
        $tot = $rw + $rl;

        $stats = UserMatchStats::summarize(
            $this->match_mode_counts,
            (int) ($this->total_matches_played ?? 0),
            (int) ($this->playtime_seconds ?? 0),
        );

        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'nivel' => $level?->nivel ?? 1,
            'xp_atual' => $level?->xp_atual ?? 0,
            'xp_proximo_nivel' => $level?->xpParaProximoNivel() ?? 358,
            'moedas' => $this->moedas ?? 0,
            'cristais' => $this->cristais ?? 0,
            'card_back_slug' => $this->card_back_slug ?? 'padrao',
            'profile_bg_slug' => $this->profile_bg_slug ?? 'padrao',
            'match_board_slug' => $this->match_board_slug ?? 'padrao',
            'avatar_id' => $this->avatar_id,
            'avatar_slug' => $this->avatar?->slug,
            'avatar_label' => $this->avatar?->label,
            'avatar_image_file' => $this->avatar?->image_file,
            'ranked_points' => $pts,
            'divisao' => $ranked->divisionKeyForPoints($pts),
            'divisao_label' => $ranked->divisionLabelForPoints($pts),
            'ranked_wins' => $rw,
            'ranked_losses' => $rl,
            'ranked_winrate' => $tot > 0 ? round($rw / $tot, 4) : null,
            'ranked_min_level' => $ranked->minLevel(),
            'total_matches_played' => $stats['total_matches_played'],
            'casual_matches_played' => $stats['casual_matches_played'],
            'ranked_matches_played' => $stats['ranked_matches_played'],
            'other_modes_matches_played' => $stats['other_modes_matches_played'],
            'playtime_seconds' => $stats['playtime_seconds'],
            'playtime_hours' => $stats['playtime_hours'],
            'is_content_creator' => (bool) ($this->is_content_creator ?? false),
            'pode_ativar_codigo_streamer' => ! ($this->is_content_creator ?? false)
                && $this->streamer_invite_token === null,
            'onboarding_deck_escolhido' => $this->onboarding_deck_escolhido_em !== null,
            'tutorial_concluido' => $this->tutorial_concluido_em !== null,
            'tutorial_pulado' => $this->tutorial_pulado_em !== null,
            'tutorial_recompensa_resgatada' => $this->tutorial_recompensa_resgatada_em !== null,
            'precisa_onboarding' => $this->onboarding_deck_escolhido_em === null,
            'pode_resgatar_recompensa_tutorial' => $this->tutorial_concluido_em !== null
                && $this->tutorial_recompensa_resgatada_em === null,
        ];
    }
}
