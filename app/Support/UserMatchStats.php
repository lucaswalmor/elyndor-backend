<?php

namespace App\Support;

class UserMatchStats
{
    /**
     * @param  array<string, int>|null  $matchModeCounts
     * @return array{
     *     casual_matches_played: int,
     *     ranked_matches_played: int,
     *     total_matches_played: int,
     *     other_modes_matches_played: int,
     *     playtime_seconds: int,
     *     playtime_hours: float
     * }
     */
    public static function summarize(?array $matchModeCounts, int $totalMatches, int $playtimeSeconds): array
    {
        $c = is_array($matchModeCounts) ? $matchModeCounts : [];
        $casual = (int) ($c['normal'] ?? 0);
        $ranked = (int) ($c['ranqueada'] ?? 0);
        $total = max(0, $totalMatches);
        $other = max(0, $total - $casual - $ranked);
        $pt = max(0, $playtimeSeconds);

        return [
            'casual_matches_played' => $casual,
            'ranked_matches_played' => $ranked,
            'total_matches_played' => $total,
            'other_modes_matches_played' => $other,
            'playtime_seconds' => $pt,
            'playtime_hours' => round($pt / 3600, 2),
        ];
    }
}
