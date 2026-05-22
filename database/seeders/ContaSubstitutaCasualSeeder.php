<?php

namespace Database\Seeders;

use App\Enums\Raridade;
use App\Models\Avatar;
use App\Models\Card;
use App\Models\Deck;
use App\Models\DeckCard;
use App\Models\PlayerLevel;
use App\Models\User;
use App\Services\Bot\CasualSubstitutePairingService;
use App\Services\Collection\PlayerCollectionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Conta substituta da fila casual (normal). Nunca listada no leaderboard.
 */
class ContaSubstitutaCasualSeeder extends Seeder
{
    public function run(): void
    {
        $collection = app(PlayerCollectionService::class);

        $avatarId = Avatar::query()->where('slug', 'creature_chimera')->value('id')
            ?? Avatar::query()->whereNotNull('image_file')->orderBy('sort_order')->value('id');

        $nickname = 'Varek';

        $user = User::query()->firstOrCreate(
            ['email' => CasualSubstitutePairingService::BOT_EMAIL],
            [
                'name' => $nickname,
                'nickname' => $nickname,
                'password' => Hash::make(bin2hex(random_bytes(24))),
                'moedas' => 0,
                'cristais' => 0,
                'ranked_points' => 180,
                'ranked_wins' => 0,
                'ranked_losses' => 0,
                'avatar_id' => $avatarId,
                'registration_device_id' => null,
            ]
        );

        $user->is_bot = true;
        $user->nickname = $nickname;
        $user->ranked_points = 180;
        if ($avatarId) {
            $user->avatar_id = $avatarId;
        }
        $user->save();

        PlayerLevel::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['nivel' => 28, 'xp_atual' => 0]
        );

        $deck = Deck::query()->firstOrCreate(
            ['user_id' => $user->id, 'is_padrao' => true],
            ['nome' => 'Deck Elyndor']
        );
        $deck->nome = 'Deck Elyndor';
        $deck->is_padrao = true;
        $deck->save();
        DeckCard::query()->where('deck_id', $deck->id)->delete();

        $picked = collect();
        $picked = $picked->merge(
            Card::where('tipo', 'spell')->where('ativo', true)->inRandomOrder()->limit(2)->pluck('id')
        );
        $picked = $picked->merge(
            Card::where('tipo', 'unit')->where('raridade', Raridade::Comum->value)->inRandomOrder()->limit(13)->pluck('id')
        );
        $picked = $picked->merge(
            Card::where('tipo', 'unit')->where('raridade', Raridade::Rara->value)->inRandomOrder()->limit(4)->pluck('id')
        );
        $picked = $picked->merge(
            Card::where('tipo', 'unit')->where('raridade', Raridade::Epica->value)->inRandomOrder()->limit(1)->pluck('id')
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
