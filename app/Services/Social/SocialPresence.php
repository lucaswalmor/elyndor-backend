<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\DB;

class SocialPresence
{
    /** @return array<int, true> Map of user IDs seen recently in user_sessions */
    public static function recentlyOnlineIds(array $userIds, ?int $windowSeconds = null): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return [];
        }

        $window = max(60, $windowSeconds ?? (int) config('game.stats.online_presence_seconds', 120));
        $since = now()->subSeconds($window);

        $rows = DB::table('user_sessions')
            ->whereIn('user_id', $userIds)
            ->where('last_seen_at', '>=', $since)
            ->pluck('user_id');

        $out = [];
        foreach ($rows as $id) {
            $out[(int) $id] = true;
        }

        return $out;
    }
}
