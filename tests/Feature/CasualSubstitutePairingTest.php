<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Deck;
use App\Models\DeckCard;
use App\Models\MatchmakingQueue;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Services\Bot\CasualSubstitutePairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CasualSubstitutePairingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pareia_humano_solo_apos_timeout_casual(): void
    {
        Config::set('game.bots.enabled', true);
        Config::set('game.bots.queue.casual_fallback_after_seconds', 5);

        $card = Card::query()->create([
            'nome' => 'Carta teste',
            'slug' => 'carta-teste-casual-bot',
            'faccao' => 'natureza',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 2,
            'vida' => 2,
        ]);

        $bot = User::factory()->create([
            'email' => CasualSubstitutePairingService::BOT_EMAIL,
            'is_bot' => true,
            'nickname' => 'Varek',
        ]);
        $deck = Deck::query()->create([
            'user_id' => $bot->id,
            'nome' => 'Deck bot',
            'is_padrao' => true,
        ]);
        DeckCard::query()->create(['deck_id' => $deck->id, 'card_id' => $card->id, 'quantidade' => 15]);

        $human = User::factory()->create(['nickname' => 'humano_casual']);
        $humanDeck = Deck::query()->create([
            'user_id' => $human->id,
            'nome' => 'Deck humano',
            'is_padrao' => true,
        ]);
        DeckCard::query()->create(['deck_id' => $humanDeck->id, 'card_id' => $card->id, 'quantidade' => 15]);

        MatchmakingQueue::query()->create([
            'user_id' => $human->id,
            'modo' => 'normal',
            'deck_id' => $humanDeck->id,
            'nivel' => 10,
            'entrou_na_fila_em' => now()->subSeconds(10),
        ]);

        $matchId = app(CasualSubstitutePairingService::class)->maybePairStaleSoloHuman();

        $this->assertNotNull($matchId);

        $botPlayer = MatchPlayer::query()
            ->where('match_id', $matchId)
            ->where('is_bot', true)
            ->first();

        $this->assertNotNull($botPlayer);
        $this->assertSame($bot->id, $botPlayer->user_id);
        $this->assertSame($deck->id, $botPlayer->deck_id);
    }
}
