<?php

namespace App\Services\Auth;

use App\Enums\Raridade;
use App\Models\Avatar;
use App\Models\Card;
use App\Models\Deck;
use App\Models\DeckCard;
use App\Models\PlayerAvatar;
use App\Models\PlayerLevel;
use App\Models\User;
use App\Services\AntiAbuse\AntiAbuseService;
use App\Services\Collection\PlayerCollectionService;
use Illuminate\Support\Facades\DB;

class RegisterService
{
    public function __construct(
        private PlayerCollectionService $collection,
        private AntiAbuseService $antiAbuse,
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

            $deck = Deck::create([
                'user_id' => $user->id,
                'nome' => 'Deck Inicial',
                'is_padrao' => true,
            ]);

            $this->attachStarterDeck($user, $deck);

            return $user->load(['playerLevel', 'avatar']);
        });
    }

    private function attachStarterDeck(User $user, Deck $deck): void
    {
        $cfg = config('game.progression.starter_deck');
        $picked = collect();

        $picked = $picked->merge(
            Card::where('raridade', Raridade::Comum->value)->inRandomOrder()->limit($cfg['comum'])->pluck('id')
        );
        $picked = $picked->merge(
            Card::where('raridade', Raridade::Rara->value)->inRandomOrder()->limit($cfg['rara'])->pluck('id')
        );
        $picked = $picked->merge(
            Card::where('raridade', Raridade::Epica->value)->inRandomOrder()->limit($cfg['epica'])->pluck('id')
        );

        $grant = [];
        foreach ($picked as $cardId) {
            DeckCard::create([
                'deck_id' => $deck->id,
                'card_id' => $cardId,
                'quantidade' => 1,
            ]);
            $grant[$cardId] = 1;
        }

        $this->collection->grant($user, $grant);
    }
}
