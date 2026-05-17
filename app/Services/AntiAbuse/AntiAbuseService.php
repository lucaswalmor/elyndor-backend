<?php

namespace App\Services\AntiAbuse;

use App\Models\MatchmakingQueue;
use App\Models\User;

class AntiAbuseService
{
    public function allowsRankedPair(MatchmakingQueue $a, MatchmakingQueue $b): bool
    {
        if (config('game.anti_abuse.rank_block_same_device', true)) {
            if ($a->device_id && $b->device_id && $a->device_id === $b->device_id) {
                return false;
            }
        }

        if (config('game.anti_abuse.rank_block_same_ip', true)) {
            if ($a->ip_address && $b->ip_address && $a->ip_address === $b->ip_address) {
                return false;
            }
        }

        return true;
    }

    public function assertRegistrationAllowed(?string $deviceId): void
    {
        $max = (int) config('game.anti_abuse.max_registrations_per_device_per_day', 0);
        if ($max <= 0 || ! $deviceId) {
            return;
        }

        $count = User::query()
            ->where('registration_device_id', $deviceId)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($count >= $max) {
            throw new \InvalidArgumentException('Limite de contas novas neste dispositivo. Tente amanhã ou entre em contato com o suporte.');
        }
    }
}
