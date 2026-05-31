<?php

namespace App\Services\Auth;

use App\Models\Avatar;
use App\Models\PlayerAvatar;
use App\Models\PlayerLevel;
use App\Models\User;
use App\Services\AntiAbuse\AntiAbuseService;
use App\Services\Onboarding\OnboardingService;
use App\Services\Streamer\StreamerInviteService;
use Illuminate\Support\Facades\DB;

class RegisterService
{
    public function __construct(
        private AntiAbuseService $antiAbuse,
        private StreamerInviteService $streamerInvites,
        private OnboardingService $onboarding,
    ) {}

    public function register(array $data): User
    {
        $this->antiAbuse->assertRegistrationAllowed($data['device_id'] ?? null);

        return DB::transaction(function () use ($data) {
            $avatar = Avatar::query()
                ->where('slug', $data['avatar_slug'])
                ->where('is_starter', true)
                ->firstOrFail();

            $regDev = isset($data['device_id'])
                ? substr((string) $data['device_id'], 0, 80)
                : null;

            $user = User::create([
                'name'     => $data['name'],
                'nickname' => $data['nickname'],
                'email'    => $data['email'],
                'password' => $data['password'],
                'avatar_id' => $avatar->id,
                'registration_device_id' => $regDev ?: null,
            ]);

            foreach (Avatar::query()->where('is_starter', true)->pluck('id') as $aid) {
                PlayerAvatar::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'avatar_id' => $aid,
                ]);
            }

            PlayerLevel::create(['user_id' => $user->id, 'nivel' => 1, 'xp_atual' => 0]);

            $this->onboarding->registrarColecaoTreino($user);
            $this->onboarding->garantirDeckTreino($user);

            $codigoStreamer = isset($data['codigo_streamer']) ? trim((string) $data['codigo_streamer']) : '';
            if ($codigoStreamer !== '') {
                $this->streamerInvites->tentarAtivar($user, $codigoStreamer);
            }

            return $user->load(['playerLevel', 'avatar']);
        });
    }
}
