<?php

namespace App\Services\AntiAbuse;

use App\Models\MatchmakingQueue;
use App\Models\User;

class AntiAbuseService
{
    /**
     * Ranqueada: sem bloqueio por IP/dispositivo (removido para testes e beta).
     * Ideia futura de anti-boost: ver ideias_futuras.md → «Anti-boost ranqueada».
     */
    public function allowsRankedPair(MatchmakingQueue $a, MatchmakingQueue $b): bool
    {
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
