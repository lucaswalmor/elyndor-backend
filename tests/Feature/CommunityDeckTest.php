<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\CommunityDeck;
use App\Models\Deck;
use App\Models\DeckCard;
use App\Models\ProjectVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommunityDeckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['elyndor.streamer_invites' => ['STREAMER_TESTE']]);
        ProjectVersion::query()->updateOrCreate(
            ['client_type' => ProjectVersion::CLIENT_GAME],
            ['versao' => '0.1.0', 'notas' => 'test'],
        );
    }

    public function test_publicar_e_copiar_deck_da_comunidade(): void
    {
        $usuario = User::factory()->create(['nickname' => 'autor_comunidade', 'total_matches_played' => 15]);
        $cartas = collect();
        for ($indice = 0; $indice < 20; $indice++) {
            $cartas->push(Card::query()->create([
                'nome' => "Carta teste {$indice}",
                'slug' => "carta-comunidade-{$indice}",
                'linhagem' => 'karuna',
                'raridade' => 'comum',
                'custo' => 2,
                'ataque' => 2,
                'vida' => 2,
                'ativo' => true,
            ]));
        }
        $deck = Deck::create(['user_id' => $usuario->id, 'nome' => 'Meu Deck', 'is_padrao' => true]);
        foreach ($cartas as $carta) {
            DeckCard::create(['deck_id' => $deck->id, 'card_id' => $carta->id, 'quantidade' => 1]);
            $usuario->playerCards()->create(['card_id' => $carta->id, 'quantidade' => 3]);
        }

        Sanctum::actingAs($usuario);

        $publicar = $this->postJson('/api/v1/community-decks', [
            'deck_id' => $deck->id,
            'nome' => 'Deck Publicado',
            'descricao' => 'Build da comunidade',
            'linhagem_principal' => 'karuna',
            'tags' => ['agressivo'],
        ]);

        $publicar->assertCreated();
        $communityId = $publicar->json('data.id');
        $this->assertNotEmpty($publicar->json('data.ely_code'));

        $outro = User::factory()->create(['nickname' => 'copiador_deck']);
        foreach ($cartas as $carta) {
            $outro->playerCards()->create(['card_id' => $carta->id, 'quantidade' => 3]);
        }
        Sanctum::actingAs($outro);

        $detalhe = $this->getJson("/api/v1/community-decks/{$communityId}");
        $detalhe->assertOk();
        $this->assertSame(1, CommunityDeck::find($communityId)->views_count);

        $copia = $this->postJson("/api/v1/community-decks/{$communityId}/copy");
        $copia->assertOk();
        $this->assertSame(1, $outro->decks()->count());
        $this->assertSame(1, CommunityDeck::find($communityId)->copies_count);
    }

    public function test_token_streamer_so_pode_ser_usado_uma_vez(): void
    {
        $primeiro = User::factory()->create(['nickname' => 'streamer_um']);
        Sanctum::actingAs($primeiro);
        $this->postJson('/api/v1/profile/streamer/activate', [
            'codigo_streamer' => 'STREAMER_TESTE',
        ])->assertOk();

        $segundo = User::factory()->create(['nickname' => 'streamer_dois']);
        Sanctum::actingAs($segundo);
        $this->postJson('/api/v1/profile/streamer/activate', [
            'codigo_streamer' => 'STREAMER_TESTE',
        ])->assertStatus(400);

        $this->assertTrue($primeiro->fresh()->is_content_creator);
        $this->assertFalse($segundo->fresh()->is_content_creator);
    }
}
