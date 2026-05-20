<?php

namespace Database\Seeders;

use App\Models\PlayerCosmeticUnlock;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Prémios cosméticos exclusivos para criadores/streamers (fora dos baús).
 *
 * 1. Adiciona o PNG em frontend/src/assets/imagens/frame_cards/ ou tabuleiros/
 *    (nome do ficheiro = asset_key, ex.: versor_boomerangbr.png).
 * 2. Regista o rótulo em config/game/cosmetics.php (opcional mas recomendado).
 * 3. Edita CONCESSOES abaixo: user_id + chaves verso/tabuleiro (null = ignorar).
 * 4. php artisan db:seed --class=ItemStreamerSeeder
 *
 * Só concede se o ficheiro existir na pasta. Desbloqueios já existentes não duplicam.
 */
class ItemStreamerSeeder extends Seeder
{
    /**
     * @var list<array{
     *   user_id: int,
     *   rotulo: string,
     *   verso: string|null,
     *   tabuleiro: string|null,
     * }>
     */
    private const CONCESSOES = [
        // [
        //     'user_id' => 0,
        //     'rotulo' => 'BoomerangBR',
        //     'verso' => 'versor_boomerangbr',
        //     'tabuleiro' => null,
        // ],
        // Exemplo com verso + tabuleiro:
        [
            'user_id' => 1,
            'rotulo' => 'OutroCriador',
            'verso' => 'verso_brasil_copa_2026',
            'tabuleiro' => 'tabuleiro_brasil_copa_2026',
        ],
    ];

    public function run(): void
    {
        $linhasValidas = array_values(array_filter(
            self::CONCESSOES,
            fn (array $linha) => (int) ($linha['user_id'] ?? 0) > 0,
        ));

        if ($linhasValidas === []) {
            $this->command?->warn('ItemStreamerSeeder: define user_id > 0 em pelo menos uma entrada de CONCESSOES.');

            return;
        }

        $raizProjeto = dirname(base_path());
        $pastaVersos = $raizProjeto.'/frontend/src/assets/imagens/frame_cards';
        $pastaTabuleiros = $raizProjeto.'/frontend/src/assets/imagens/tabuleiros';

        $totalNovos = 0;
        $totalJaTinha = 0;
        $totalIgnorados = 0;

        foreach ($linhasValidas as $concessao) {
            $userId = (int) $concessao['user_id'];
            $rotulo = (string) ($concessao['rotulo'] ?? "user#{$userId}");

            if (! User::query()->whereKey($userId)->exists()) {
                $this->command?->warn("[{$rotulo}] Utilizador #{$userId} não existe — ignorado.");

                continue;
            }

            $this->command?->info("— {$rotulo} (user #{$userId}) —");

            foreach ($this->itensCosmetico($concessao) as $item) {
                $caminho = $item['categoria'] === 'card_back'
                    ? "{$pastaVersos}/{$item['asset_key']}.png"
                    : "{$pastaTabuleiros}/{$item['asset_key']}.png";

                if (! is_file($caminho)) {
                    $this->command?->warn("  ⊗ {$item['categoria']}/{$item['asset_key']}: PNG não encontrado ({$caminho})");
                    $totalIgnorados++;

                    continue;
                }

                $jaExistia = PlayerCosmeticUnlock::query()
                    ->where('user_id', $userId)
                    ->where('asset_category', $item['categoria'])
                    ->where('asset_key', $item['asset_key'])
                    ->exists();

                if ($jaExistia) {
                    $this->command?->line("  ○ {$item['categoria']}/{$item['asset_key']}: já desbloqueado");
                    $totalJaTinha++;

                    continue;
                }

                PlayerCosmeticUnlock::query()->create([
                    'user_id' => $userId,
                    'asset_category' => $item['categoria'],
                    'asset_key' => $item['asset_key'],
                ]);

                $this->command?->info("  ✓ {$item['categoria']}/{$item['asset_key']}: desbloqueado");
                $totalNovos++;
            }
        }

        $this->command?->info("Resumo: {$totalNovos} novo(s), {$totalJaTinha} já tinha, {$totalIgnorados} sem ficheiro.");
    }

    /**
     * @param  array{verso?: string|null, tabuleiro?: string|null}  $concessao
     * @return list<array{categoria: string, asset_key: string}>
     */
    private function itensCosmetico(array $concessao): array
    {
        $itens = [];

        $verso = isset($concessao['verso']) ? trim((string) $concessao['verso']) : '';
        if ($verso !== '') {
            $itens[] = ['categoria' => 'card_back', 'asset_key' => $verso];
        }

        $tabuleiro = isset($concessao['tabuleiro']) ? trim((string) $concessao['tabuleiro']) : '';
        if ($tabuleiro !== '') {
            $itens[] = ['categoria' => 'match_board', 'asset_key' => $tabuleiro];
        }

        return $itens;
    }
}
