<?php

namespace Tests\Unit;

use App\Models\Card;
use App\Models\User;
use App\Services\Collection\PlayerCollectionService;
use App\Services\Deck\DeckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DeckRulesV21Test extends TestCase
{
    use RefreshDatabase;

    public function test_deck_aceita_20_cartas_com_ate_5_spells(): void
    {
        $user = User::factory()->create(['nickname' => 'deck_rules_ok']);
        $cards = $this->createCards(15, 5, 0);
        app(PlayerCollectionService::class)->grant($user, array_fill_keys($cards->pluck('id')->all(), 1));

        $deck = app(DeckService::class)->create($user, 'Deck v2.1', $this->payload($cards), true);

        $this->assertSame(20, $deck['total_cartas']);
        $this->assertSame(5, $deck['total_spells']);
        $this->assertTrue($deck['valido']);
    }

    public function test_deck_rejeita_mais_de_5_spells(): void
    {
        $user = User::factory()->create(['nickname' => 'deck_rules_spells']);
        $cards = $this->createCards(14, 6, 0);
        app(PlayerCollectionService::class)->grant($user, array_fill_keys($cards->pluck('id')->all(), 1));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('feitiço');

        app(DeckService::class)->create($user, 'Deck com spells demais', $this->payload($cards), true);
    }

    public function test_deck_rejeita_mais_de_uma_lendaria_total(): void
    {
        $user = User::factory()->create(['nickname' => 'deck_rules_legendary']);
        $cards = $this->createCards(18, 0, 2);
        app(PlayerCollectionService::class)->grant($user, array_fill_keys($cards->pluck('id')->all(), 1));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lendaria');

        app(DeckService::class)->create($user, 'Deck com lendárias demais', $this->payload($cards), true);
    }

    private function createCards(int $units, int $spells, int $legendaryUnits)
    {
        $cards = collect();
        for ($i = 1; $i <= $units; $i++) {
            $cards->push(Card::query()->create([
                'nome' => "Unidade {$i}",
                'slug' => "deck-unit-{$i}",
                'linhagem' => 'karuna',
                'raridade' => 'comum',
                'tipo' => 'unit',
                'custo' => 1,
                'ataque' => 1,
                'vida' => 1,
                'ativo' => true,
                'colecionavel' => true,
            ]));
        }
        for ($i = 1; $i <= $spells; $i++) {
            $cards->push(Card::query()->create([
                'nome' => "Spell {$i}",
                'slug' => "deck-spell-{$i}",
                'linhagem' => 'neutra',
                'raridade' => 'comum',
                'tipo' => 'spell',
                'custo' => 1,
                'ataque' => 0,
                'vida' => 0,
                'ativo' => true,
                'colecionavel' => true,
            ]));
        }
        for ($i = 1; $i <= $legendaryUnits; $i++) {
            $cards->push(Card::query()->create([
                'nome' => "Lendária {$i}",
                'slug' => "deck-legendary-{$i}",
                'linhagem' => 'orun',
                'raridade' => 'lendaria',
                'tipo' => 'unit',
                'custo' => 5,
                'ataque' => 5,
                'vida' => 5,
                'ativo' => true,
                'colecionavel' => true,
            ]));
        }

        return $cards;
    }

    private function payload($cards): array
    {
        return $cards->map(fn (Card $card) => [
            'card_id' => $card->id,
            'quantidade' => 1,
        ])->values()->all();
    }
}
