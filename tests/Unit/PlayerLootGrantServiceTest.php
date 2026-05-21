<?php

namespace Tests\Unit;

use App\Models\Card;
use App\Models\PlayerCard;
use App\Models\PlayerCosmeticUnlock;
use App\Models\PlayerLootDuplicate;
use App\Models\User;
use App\Services\Economy\PlayerLootGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerLootGrantServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_conceder_carta_igual_ao_bau(): void
    {
        $carta = Card::query()->create([
            'nome' => 'Teste Grant',
            'slug' => 'carta-grant-teste',
            'faccao' => 'natureza',
            'raridade' => 'comum',
            'custo' => 1,
            'ataque' => 1,
            'vida' => 1,
            'colecionavel' => true,
            'ativo' => true,
        ]);

        $usuario = User::factory()->create(['nickname' => 'GrantTeste']);

        $resultado = app(PlayerLootGrantService::class)->concederCartaPorSlug($usuario, 'carta-grant-teste');

        $this->assertSame('carta_concedida', $resultado['status']);
        $this->assertSame(1, PlayerCard::query()->where('user_id', $usuario->id)->where('card_id', $carta->id)->value('quantidade'));
    }

    public function test_conceder_cosmetico_duplicado_vai_para_repetidas(): void
    {
        $usuario = User::factory()->create(['nickname' => 'CosGrant']);

        $servico = app(PlayerLootGrantService::class);
        $servico->concederCosmetico($usuario, 'card_back', 'verso_teste_grant');
        $segundo = $servico->concederCosmetico($usuario, 'card_back', 'verso_teste_grant');

        $this->assertSame('cosmetico_duplicado_repetidas', $segundo['status']);
        $this->assertSame(1, PlayerCosmeticUnlock::query()->where('user_id', $usuario->id)->where('asset_key', 'verso_teste_grant')->count());
        $this->assertTrue(
            PlayerLootDuplicate::query()->where('user_id', $usuario->id)->where('quantity', '>=', 1)->exists(),
        );
    }
}
