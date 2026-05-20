<?php

namespace Tests\Unit;

use App\Services\Ranked\RankedService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RankedPointDeltasTest extends TestCase
{
    private RankedService $ranked;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ranked = app(RankedService::class);
    }

    #[DataProvider('cenariosPontuacaoProvider')]
    public function test_calcula_delta_vencedor_e_perdedor(
        string $divisaoVencedor,
        string $divisaoPerdedor,
        int $deltaVencedorEsperado,
        int $deltaPerdedorEsperado,
    ): void {
        [$deltaVencedor, $deltaPerdedor] = $this->ranked->pointDeltas($divisaoVencedor, $divisaoPerdedor);

        $this->assertSame($deltaVencedorEsperado, $deltaVencedor);
        $this->assertSame($deltaPerdedorEsperado, $deltaPerdedor);
    }

    /** @return iterable<string, array{string, string, int, int}> */
    public static function cenariosPontuacaoProvider(): iterable
    {
        yield 'mesmo elo ferro' => ['ferro', 'ferro', 20, -20];
        yield 'mesmo elo prata' => ['prata', 'prata', 20, -20];

        yield 'underdog vence ferro vs prata' => ['ferro', 'prata', 30, -30];
        yield 'favorito vence prata vs ferro' => ['prata', 'ferro', 20, -20];

        yield 'favorito perde prata vs ferro' => ['ferro', 'prata', 30, -30];
        yield 'underdog perde ferro vs prata' => ['prata', 'ferro', 20, -20];

        yield 'underdog vence bronze vs ouro' => ['bronze', 'ouro', 30, -30];
        yield 'favorito perde mestre vs bronze' => ['bronze', 'mestre', 45, -45];
    }

    public function test_previsao_pontos_ranqueada_ferro_vs_prata(): void
    {
        $previsao = $this->ranked->previsaoPontosRanqueada(50, 300, false);

        $this->assertSame(30, $previsao['pontos_vitoria']);
        $this->assertSame(-20, $previsao['pontos_derrota']);
    }

    public function test_previsao_pontos_ranqueada_prata_vs_ferro_favorito_perde(): void
    {
        $previsao = $this->ranked->previsaoPontosRanqueada(300, 50, false);

        $this->assertSame(20, $previsao['pontos_vitoria']);
        $this->assertSame(-30, $previsao['pontos_derrota']);
    }

    public function test_previsao_pontos_ranqueada_com_bot_reduz_metade(): void
    {
        $previsao = $this->ranked->previsaoPontosRanqueada(50, 300, true);

        $this->assertSame(15, $previsao['pontos_vitoria']);
        $this->assertSame(-10, $previsao['pontos_derrota']);
    }
}
