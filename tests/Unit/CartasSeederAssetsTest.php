<?php

namespace Tests\Unit;

use Database\Seeders\CartasSeeder;
use ReflectionClass;
use Tests\TestCase;

class CartasSeederAssetsTest extends TestCase
{
    public function test_todas_as_cartas_do_seeder_apontam_para_assets_existentes(): void
    {
        $reflection = new ReflectionClass(CartasSeeder::class);
        $method = $reflection->getMethod('cards');
        $method->setAccessible(true);

        $cards = $method->invoke(new CartasSeeder());
        $basePath = base_path('../frontend/src/assets/imagens/cards');
        $missing = [];

        foreach ($cards as $card) {
            $path = $card['imagem_path'] ?? null;
            if (! $path || ! file_exists($basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path))) {
                $missing[] = ($card['slug'] ?? 'sem-slug').': '.($path ?? 'sem imagem_path');
            }
        }

        $this->assertSame([], $missing);
        $this->assertCount(109, $cards);
    }
}
