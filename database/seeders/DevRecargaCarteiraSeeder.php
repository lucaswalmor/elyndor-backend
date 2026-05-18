<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Testes locais: atualiza moedas e/ou cristais por ID de utilizador.
 *
 * .env (exemplo):
 *   DEV_TOPUP_USER_IDS=1,2
 *   DEV_TOPUP_MOEDAS=1000000
 *   DEV_TOPUP_CRISTAIS=1000000
 *
 * Regras:
 * - IDs: lista separada por vírgula. Por omissão no código: 1 e 2.
 * - Se DEV_TOPUP_MOEDAS e DEV_TOPUP_CRISTAIS vierem ambos vazios/ausentes → usa 1_000_000 em cada.
 * - Se só definires uma das duas → atualiza só essa coluna (a outra mantém-se).
 */
class DevRecargaCarteiraSeeder extends Seeder
{
    private const DEFAULT_EACH = 1_000_000;

    public function run(): void
    {
        $ids = $this->parseUserIds(env('DEV_TOPUP_USER_IDS', '1,2'));
        if ($ids === []) {
            $this->command?->warn('Nenhum ID válido em DEV_TOPUP_USER_IDS.');

            return;
        }

        $moedasRaw = env('DEV_TOPUP_MOEDAS');
        $cristaisRaw = env('DEV_TOPUP_CRISTAIS');

        $moedas = $this->parseAmountOrUnset($moedasRaw);
        $cristais = $this->parseAmountOrUnset($cristaisRaw);

        $bothBlank = $this->isBlankEnv($moedasRaw) && $this->isBlankEnv($cristaisRaw);

        if ($moedas === null && $cristais === null) {
            if ($bothBlank) {
                $moedas = self::DEFAULT_EACH;
                $cristais = self::DEFAULT_EACH;
                $this->command?->info('Montantes em branco: usando '.number_format(self::DEFAULT_EACH, 0, ',', '.').' em moedas e em cristais.');
            } else {
                $this->command?->warn('Um dos montantes parece inválido; usa apenas dígitos ou deixa as duas chaves vazias para o padrão.');

                return;
            }
        }

        $payload = [];
        if ($moedas !== null) {
            $payload['moedas'] = $moedas;
        }
        if ($cristais !== null) {
            $payload['cristais'] = $cristais;
        }

        $found = User::query()->whereIn('id', $ids)->pluck('id')->all();
        $missing = array_values(array_diff($ids, $found));
        if ($missing !== []) {
            $this->command?->warn('IDs inexistentes: '.implode(', ', $missing));
        }
        if ($found === []) {
            return;
        }

        $affected = User::query()->whereIn('id', $found)->update($payload);

        $parts = [];
        if (array_key_exists('moedas', $payload)) {
            $parts[] = 'moedas '.number_format($payload['moedas'], 0, ',', '.');
        }
        if (array_key_exists('cristais', $payload)) {
            $parts[] = 'cristais '.number_format($payload['cristais'], 0, ',', '.');
        }

        $this->command?->info('IDs: '.implode(', ', $found)." · {$affected} linha(s) · ".implode(' · ', $parts));
    }

    /** @return list<int> */
    private function parseUserIds(string $raw): array
    {
        $parts = preg_split('/\s*,\s*/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
        $ids = [];
        foreach ($parts as $p) {
            $id = (int) $p;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function isBlankEnv(mixed $raw): bool
    {
        if ($raw === null) {
            return true;
        }

        return trim((string) $raw) === '';
    }

    /** null = não atualizar coluna; int = valor (pode ser 0) */
    private function parseAmountOrUnset(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        $t = trim($raw);
        if ($t === '') {
            return null;
        }

        return (int) $t;
    }
}
