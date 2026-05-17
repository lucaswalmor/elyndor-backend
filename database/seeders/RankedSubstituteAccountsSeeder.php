<?php

namespace Database\Seeders;

use App\Enums\Raridade;
use App\Models\Avatar;
use App\Models\Card;
use App\Models\Deck;
use App\Models\DeckCard;
use App\Models\PlayerLevel;
use App\Models\User;
use App\Services\Collection\PlayerCollectionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Contas internas ranqueadas (substitutos). Nunca listadas no leaderboard.
 * Em produção: `php artisan db:seed --class=RankedSubstituteAccountsSeeder` após migrações.
 */
class RankedSubstituteAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $collection = app(PlayerCollectionService::class);

        $divisions = config('game.ranked.divisions', []);

        $nicknames = [
            'ferro' => 'Kaelthorne',
            'bronze' => 'Myrrine',
            'prata' => 'Orros',
            'ouro' => 'Serathe',
            'platina' => 'Valdris',
            'diamante' => 'Ilythea',
            'mestre' => 'Aurekan',
        ];

        $avatarId = Avatar::query()->where('slug', 'creature_chimera')->value('id')
            ?? Avatar::query()->whereNotNull('image_file')->orderBy('sort_order')->value('id');

        foreach ($divisions as $def) {
            $key = $def['key'];
            $email = "rank_substitute_{$key}@bots.elyndor.local";
            $nickname = $nicknames[$key] ?? ucfirst($key).'Opponent';

            $midRank = isset($def['max']) && $def['max'] !== null
                ? (int) floor(($def['min'] + $def['max']) / 2)
                : ((int) $def['min'] + 200);

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $nickname,
                    'nickname' => $nickname,
                    'password' => Hash::make(bin2hex(random_bytes(24))),
                    'moedas' => 0,
                    'cristais' => 0,
                    'ranked_points' => $midRank,
                    'ranked_wins' => 0,
                    'ranked_losses' => 0,
                    'avatar_id' => $avatarId,
                    'registration_device_id' => null,
                ]
            );

            $user->is_bot = true;
            $user->nickname = $nickname;
            $user->ranked_points = $midRank;
            if ($avatarId) {
                $user->avatar_id = $avatarId;
            }
            $user->save();

            PlayerLevel::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['nivel' => max(40, (int) config('game.ranked.min_level', 20) + 5), 'xp_atual' => 0]
            );

            if ($user->decks()->exists()) {
                continue;
            }

            $deck = Deck::create([
                'user_id' => $user->id,
                'nome' => 'Deck Elyndor',
                'is_padrao' => true,
            ]);

            $cfg = config('game.progression.starter_deck');
            $picked = collect();
            $picked = $picked->merge(
                Card::where('raridade', Raridade::Comum->value)->inRandomOrder()->limit((int) $cfg['comum'])->pluck('id')
            );
            $picked = $picked->merge(
                Card::where('raridade', Raridade::Rara->value)->inRandomOrder()->limit((int) $cfg['rara'])->pluck('id')
            );
            $picked = $picked->merge(
                Card::where('raridade', Raridade::Epica->value)->inRandomOrder()->limit((int) $cfg['epica'])->pluck('id')
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

            $collection->grant($user, $grant);
        }
    }
}
