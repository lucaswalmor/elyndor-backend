<?php

namespace Database\Seeders;

use App\Models\PlayerCosmeticUnlock;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Concede o verso exclusivo «Beta Tester» (não está em nenhum baú).
 *
 * 1. Edita a constante USER_IDS abaixo com o(s) id(s) do jogador.
 * 2. Executa: php artisan db:seed --class=VersoBetaTesterSeeder
 *
 * O slug equipável na API/perfil é `verso_beta_tester` (asset_key = nome do PNG).
 */
class VersoBetaTesterSeeder extends Seeder
{
    /** @var list<int> IDs de utilizadores — alterar manualmente antes de correr a seeder */
    private const USER_IDS = [
        // 1,
    ];

    private const CATEGORIA = 'card_back';

    private const CHAVE_ASSET = 'verso_beta_tester';

    public function run(): void
    {
        if (self::USER_IDS === []) {
            $this->command?->warn('VersoBetaTesterSeeder: define pelo menos um ID em USER_IDS antes de executar.');

            return;
        }

        $existentes = User::query()->whereIn('id', self::USER_IDS)->pluck('id')->all();
        $ausentes = array_values(array_diff(self::USER_IDS, $existentes));

        if ($ausentes !== []) {
            $this->command?->warn('IDs inexistentes: '.implode(', ', $ausentes));
        }

        if ($existentes === []) {
            return;
        }

        $concedidos = 0;
        foreach ($existentes as $userId) {
            $unlock = PlayerCosmeticUnlock::query()->firstOrCreate(
                [
                    'user_id' => $userId,
                    'asset_category' => self::CATEGORIA,
                    'asset_key' => self::CHAVE_ASSET,
                ],
            );

            if ($unlock->wasRecentlyCreated) {
                $concedidos++;
            }
        }

        $this->command?->info(
            'Verso Beta Tester: '.count($existentes).' jogador(es) processado(s), '
            .$concedidos.' desbloqueio(s) novo(s). Chave: '.self::CHAVE_ASSET,
        );
    }
}
