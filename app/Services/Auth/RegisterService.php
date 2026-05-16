<?php

namespace App\Services\Auth;

use App\Enums\Raridade;
use App\Models\Card;
use App\Models\Deck;
use App\Models\DeckCard;
use App\Models\PlayerLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterService
{
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],       // privado
                'nickname' => $data['nickname'],   // público
                'email'    => $data['email'],       // privado
                'password' => $data['password'],
            ]);

            PlayerLevel::create(['user_id' => $user->id, 'nivel' => 1, 'xp_atual' => 0]);

            $deck = Deck::create([
                'user_id' => $user->id,
                'nome' => 'Deck Inicial',
                'is_padrao' => true,
            ]);

            $this->attachStarterDeck($deck);

            return $user->load('playerLevel');
        });
    }

    private function attachStarterDeck(Deck $deck): void
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

        foreach ($picked as $cardId) {
            DeckCard::create([
                'deck_id' => $deck->id,
                'card_id' => $cardId,
                'quantidade' => 1,
            ]);
        }
    }
}
