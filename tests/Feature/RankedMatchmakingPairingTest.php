<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Deck;
use App\Models\DeckCard;
use App\Models\MatchmakingQueue;
use App\Models\User;
use App\Services\Match\MatchmakingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class RankedMatchmakingPairingTest extends TestCase
{
    use RefreshDatabase;

    private function criarDeckValido(User $usuario): Deck
    {
        $carta = Card::query()->create([
            'nome' => 'Carta teste mm',
            'slug' => 'carta-mm-'.$usuario->id,
            'linhagem' => 'ybyra',
            'raridade' => 'comum',
            'custo' => 2,
            'ataque' => 2,
            'vida' => 2,
        ]);

        $deck = Deck::query()->create([
            'user_id' => $usuario->id,
            'nome' => 'Deck MM',
            'is_padrao' => true,
        ]);

        DeckCard::query()->create([
            'deck_id' => $deck->id,
            'card_id' => $carta->id,
            'quantidade' => 15,
        ]);

        return $deck;
    }

    public function test_pareia_dois_humanos_ranqueados_mesmo_ip_na_mesma_maquina(): void
    {
        Config::set('game.bots.enabled', false);

        $jogadorPrata = User::factory()->create([
            'nickname' => 'jogador_prata',
            'ranked_points' => 300,
        ]);
        $jogadorOuro = User::factory()->create([
            'nickname' => 'jogador_ouro',
            'ranked_points' => 500,
        ]);

        $deckPrata = $this->criarDeckValido($jogadorPrata);
        $deckOuro = $this->criarDeckValido($jogadorOuro);

        $ipTeste = '203.0.113.50';

        MatchmakingQueue::query()->create([
            'user_id' => $jogadorPrata->id,
            'modo' => 'ranqueada',
            'deck_id' => $deckPrata->id,
            'nivel' => 25,
            'pontos_ranked' => 300,
            'divisao' => 'prata',
            'entrou_na_fila_em' => now()->subSeconds(5),
            'ip_address' => $ipTeste,
            'device_id' => 'device-teste-1',
        ]);

        MatchmakingQueue::query()->create([
            'user_id' => $jogadorOuro->id,
            'modo' => 'ranqueada',
            'deck_id' => $deckOuro->id,
            'nivel' => 25,
            'pontos_ranked' => 500,
            'divisao' => 'ouro',
            'entrou_na_fila_em' => now()->subSeconds(3),
            'ip_address' => $ipTeste,
            'device_id' => 'device-teste-1',
        ]);

        $matchId = app(MatchmakingService::class)->tryPairRanked();

        $this->assertNotNull($matchId);
        $this->assertSame(0, MatchmakingQueue::query()->where('modo', 'ranqueada')->count());
    }

    public function test_pareia_prata_e_ouro_apos_tempo_adjacente_sem_baixa_populacao(): void
    {
        Config::set('game.ranked.pairing.adjacent_division_seconds', 10);
        Config::set('game.ranked.low_population.max_humans_online', 0);
        Config::set('game.bots.enabled', false);

        $jogadorA = User::factory()->create(['nickname' => 'jogador_a', 'ranked_points' => 300]);
        $jogadorB = User::factory()->create(['nickname' => 'jogador_b', 'ranked_points' => 500]);
        $deckA = $this->criarDeckValido($jogadorA);
        $deckB = $this->criarDeckValido($jogadorB);

        MatchmakingQueue::query()->create([
            'user_id' => $jogadorA->id,
            'modo' => 'ranqueada',
            'deck_id' => $deckA->id,
            'nivel' => 25,
            'pontos_ranked' => 300,
            'divisao' => 'prata',
            'entrou_na_fila_em' => now()->subSeconds(20),
            'ip_address' => '10.0.0.1',
        ]);

        MatchmakingQueue::query()->create([
            'user_id' => $jogadorB->id,
            'modo' => 'ranqueada',
            'deck_id' => $deckB->id,
            'nivel' => 25,
            'pontos_ranked' => 500,
            'divisao' => 'ouro',
            'entrou_na_fila_em' => now()->subSeconds(15),
            'ip_address' => '10.0.0.2',
        ]);

        $matchId = app(MatchmakingService::class)->tryPairRanked();

        $this->assertNotNull($matchId);
    }
}
