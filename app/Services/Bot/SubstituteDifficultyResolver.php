<?php

namespace App\Services\Bot;

use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Services\Ranked\RankedService;

class SubstituteDifficultyResolver
{
    public function __construct(
        private RankedService $ranked,
    ) {}

    /**
     * @return array{slug: string, aggression: float, mistake_chance: float}
     */
    public function forMatch(GameMatch $match, int $botSlot): array
    {
        $modo = trim((string) ($match->modo ?? ''));

        if ($modo === 'normal') {
            return $this->profile('casual');
        }

        /** @var MatchPlayer|null $botPlayer */
        $botPlayer = $match->players->firstWhere('player_slot', $botSlot);
        $divisionKey = 'ferro';

        if ($botPlayer?->user_id) {
            $botUser = User::query()->find($botPlayer->user_id);
            if ($botUser) {
                $divisionKey = $this->ranked->divisionKeyForPoints((int) ($botUser->ranked_points ?? 0));
            }
        }

        $rankedProfile = config("game.bots.difficulties.ranked.{$divisionKey}");

        return is_array($rankedProfile)
            ? array_merge($this->profile('casual'), $rankedProfile, ['slug' => 'ranked_'.$divisionKey])
            : $this->profile('ranked_ferro');
    }

    /**
     * @return array{slug: string, aggression: float, mistake_chance: float}
     */
    private function profile(string $key): array
    {
        $defaults = [
            'slug' => $key,
            'aggression' => 0.55,
            'mistake_chance' => 0.08,
        ];

        if ($key === 'casual') {
            $configured = config('game.bots.difficulties.casual', []);

            return array_merge($defaults, is_array($configured) ? $configured : []);
        }

        $ranked = config('game.bots.difficulties.ranked.ferro', []);

        return array_merge($defaults, is_array($ranked) ? $ranked : [], ['slug' => $key]);
    }
}
