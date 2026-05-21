<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Economy\PlayerLootGrantService;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

/**
 * Prémios manuais (streamers, beta, suporte) — mesma gravação que abrir um baú.
 *
 * - cartas → player_cards (+ repetidas se acima do limite de cópias)
 * - verso / tabuleiro / fundo → player_cosmetic_unlocks (+ repetidas se já tiver)
 *
 * 1. Rótulo em config/game/cosmetics.php (cosméticos)
 * 2. Carta deve existir em cards.slug (seed CartasSeeder)
 * 3. Edita CONCESSOES e executa: php artisan db:seed --class=ItemStreamerSeeder
 *
 * Não precisa da pasta frontend no servidor (API Hostinger / frontend Vercel).
 */
class ItemStreamerSeeder extends Seeder
{
    /**
     * @var list<array{
     *   user_id: int,
     *   rotulo: string,
     *   verso?: string|null,
     *   tabuleiro?: string|null,
     *   fundo_perfil?: string|null,
     *   cartas?: list<string>,
     * }>
     */
    private const CONCESSOES = [
        [
            'user_id' => 1,
            'rotulo' => 'BoomerangBR',
            'verso' => 'versor_boomerangbr',
            'tabuleiro' => null,
            'cartas' => [],
        ],
        [
            'user_id' => 1,
            'rotulo' => 'Brasil Copa 2026 (cosméticos)',
            'verso' => 'verso_brasil_copa_2026',
            'tabuleiro' => 'tabuleiro_brasil_copa_2026',
            'cartas' => [],
        ],
        // Exemplo com cartas:
        // [
        //     'user_id' => 2,
        //     'rotulo' => 'Pacote cartas',
        //     'cartas' => ['corvo-funerario', 'oraculo-solar'],
        // ],
    ];

    public function run(): void
    {
        $concessoes = array_values(array_filter(
            self::CONCESSOES,
            fn (array $linha) => (int) ($linha['user_id'] ?? 0) > 0,
        ));

        if ($concessoes === []) {
            $this->command?->warn('ItemStreamerSeeder: define user_id > 0 em pelo menos uma entrada de CONCESSOES.');

            return;
        }

        $servico = app(PlayerLootGrantService::class);

        $contagemNovos = 0;
        $contagemRepetidas = 0;
        $contagemErros = 0;

        foreach ($concessoes as $concessao) {
            $userId = (int) $concessao['user_id'];
            $rotulo = (string) ($concessao['rotulo'] ?? "user#{$userId}");

            $usuario = User::query()->find($userId);
            if (! $usuario) {
                $this->command?->warn("[{$rotulo}] Utilizador #{$userId} não existe — ignorado.");
                $contagemErros++;

                continue;
            }

            $this->command?->info("— {$rotulo} (user #{$userId}) —");

            foreach ($this->itensDaConcessao($concessao) as $item) {
                try {
                    $resultado = match ($item['tipo']) {
                        'carta' => $servico->concederCartaPorSlug($usuario, $item['chave']),
                        'cosmetico' => $servico->concederCosmetico($usuario, $item['categoria'], $item['chave']),
                        default => throw new InvalidArgumentException('Tipo de item inválido.'),
                    };
                } catch (InvalidArgumentException $exception) {
                    $this->command?->warn('  ⊗ '.$this->rotuloItem($item).': '.$exception->getMessage());
                    $contagemErros++;

                    continue;
                }

                $mensagem = $this->formatarResultado($item, $resultado);
                $this->command?->line('  '.$mensagem);

                if (str_contains($resultado['status'], 'duplicad')) {
                    $contagemRepetidas++;
                } else {
                    $contagemNovos++;
                }
            }
        }

        $this->command?->info(
            "Resumo: {$contagemNovos} concedido(s), {$contagemRepetidas} em repetidas (já tinha / cópia extra), {$contagemErros} erro(s).",
        );
    }

    /**
     * @param  array<string, mixed>  $concessao
     * @return list<array{tipo: string, chave: string, categoria?: string}>
     */
    private function itensDaConcessao(array $concessao): array
    {
        $itens = [];

        $verso = isset($concessao['verso']) ? trim((string) $concessao['verso']) : '';
        if ($verso !== '') {
            $itens[] = ['tipo' => 'cosmetico', 'categoria' => 'card_back', 'chave' => $verso];
        }

        $tabuleiro = isset($concessao['tabuleiro']) ? trim((string) $concessao['tabuleiro']) : '';
        if ($tabuleiro !== '') {
            $itens[] = ['tipo' => 'cosmetico', 'categoria' => 'match_board', 'chave' => $tabuleiro];
        }

        $fundo = isset($concessao['fundo_perfil']) ? trim((string) $concessao['fundo_perfil']) : '';
        if ($fundo !== '') {
            $itens[] = ['tipo' => 'cosmetico', 'categoria' => 'profile_bg', 'chave' => $fundo];
        }

        $cartas = $concessao['cartas'] ?? [];
        if (is_array($cartas)) {
            foreach ($cartas as $slugCarta) {
                $slug = trim((string) $slugCarta);
                if ($slug !== '') {
                    $itens[] = ['tipo' => 'carta', 'chave' => $slug];
                }
            }
        }

        return $itens;
    }

    /**
     * @param  array{tipo: string, chave: string, categoria?: string}  $item
     * @param  array<string, mixed>  $resultado
     */
    private function formatarResultado(array $item, array $resultado): string
    {
        $rotulo = $this->rotuloItem($item);

        return match ($resultado['status']) {
            'carta_concedida' => "✓ {$rotulo}: carta na coleção ({$resultado['card_nome']})",
            'carta_duplicada_repetidas' => "↻ {$rotulo}: cópia extra → repetidas ({$resultado['card_nome']})",
            'cosmetico_novo' => "✓ {$rotulo}: desbloqueado",
            'cosmetico_duplicado_repetidas' => "↻ {$rotulo}: já tinha → repetidas",
            default => "? {$rotulo}: {$resultado['status']}",
        };
    }

    /**
     * @param  array{tipo: string, chave: string, categoria?: string}  $item
     */
    private function rotuloItem(array $item): string
    {
        if ($item['tipo'] === 'carta') {
            return "card/{$item['chave']}";
        }

        return ($item['categoria'] ?? 'cosmetico').'/'.$item['chave'];
    }
}
