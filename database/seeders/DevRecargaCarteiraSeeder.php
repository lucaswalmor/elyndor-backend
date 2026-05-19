<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Testes locais: atualiza moedas e/ou cristais por ID de utilizador.
 *
 * .env (exemplo):
 *   DEV_TOPUP_USER_IDS=1,2  (Deixe vazio para dar crédito a TODOS os jogadores)
 *   DEV_TOPUP_MOEDAS=1000000
 *   DEV_TOPUP_CRISTAIS=1000000
 *
 * Regras:
 * - Se a variável DEV_TOPUP_USER_IDS estiver vazia ou ausente, a recarga será aplicada a TODOS os utilizadores.
 * - Os montantes nunca ultrapassam 1.000.000 (predefinido). Se passado um valor maior, será limitado a 1.000.000.
 */
class DevRecargaCarteiraSeeder extends Seeder
{
    private const DEFAULT_EACH = 1_000_000;
    private const MAX_CAP = 1_000_000;

    public function run(): void
    {
        $rawIds = env('DEV_TOPUP_USER_IDS', '');
        
        if ($this->isBlankEnv($rawIds)) {
            $found = User::query()->pluck('id')->all();
            if ($found === []) {
                $this->command?->warn('Nenhum jogador cadastrado no banco.');
                return;
            }
            $this->command?->info('DEV_TOPUP_USER_IDS vazio: Aplicando recarga para TODOS os jogadores ('.count($found).' encontrados).');
        } else {
            $ids = $this->parseUserIds($rawIds);
            if ($ids === []) {
                $this->command?->warn('Nenhum ID válido em DEV_TOPUP_USER_IDS.');
                return;
            }
            $found = User::query()->whereIn('id', $ids)->pluck('id')->all();
            $missing = array_values(array_diff($ids, $found));
            if ($missing !== []) {
                $this->command?->warn('IDs inexistentes: '.implode(', ', $missing));
            }
            if ($found === []) {
                return;
            }
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
            $payload['moedas'] = min($moedas, self::MAX_CAP);
        }
        if ($cristais !== null) {
            $payload['cristais'] = min($cristais, self::MAX_CAP);
        }

        $affected = User::query()->whereIn('id', $found)->update($payload);

        $parts = [];
        if (array_key_exists('moedas', $payload)) {
            $parts[] = 'moedas '.number_format($payload['moedas'], 0, ',', '.');
        }
        if (array_key_exists('cristais', $payload)) {
            $parts[] = 'cristais '.number_format($payload['cristais'], 0, ',', '.');
        }

        $this->command?->info('IDs afetados: '.count($found)." · {$affected} linha(s) atualizada(s) · ".implode(' · ', $parts));
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
