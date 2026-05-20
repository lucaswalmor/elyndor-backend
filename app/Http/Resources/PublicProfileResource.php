<?php

namespace App\Http\Resources;

use App\Services\Ranked\RankedService;
use App\Services\Social\FriendshipService;
use App\Support\UserMatchStats;
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

        $stats = UserMatchStats::summarize(
            $this->match_mode_counts,
            (int) ($this->total_matches_played ?? 0),
            (int) ($this->playtime_seconds ?? 0),
        );

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
            'total_matches_played' => $stats['total_matches_played'],
            'casual_matches_played' => $stats['casual_matches_played'],
            'ranked_matches_played' => $stats['ranked_matches_played'],
            'other_modes_matches_played' => $stats['other_modes_matches_played'],
            'playtime_seconds' => $stats['playtime_seconds'],
            'playtime_hours' => $stats['playtime_hours'],
            'is_content_creator' => (bool) ($this->is_content_creator ?? false),
            'streamer_divulgacao' => $this->when(
                (bool) ($this->is_content_creator ?? false),
                fn () => app(\App\Services\Streamer\StreamerInviteService::class)
                    ->formatarPerfil($this->streamerProfile),
            ),
            'friendship' => $this->when(
                $request->user() !== null,
                fn () => app(FriendshipService::class)->relationshipForViewer($request->user(), $this->resource),
            ),
        ];
    }
}
