<?php

namespace App\Services\Ranked;

use App\Models\User;

class RankedService
{
    /** @return list<array{key: string, label: string, min: int, max: ?int}> */
    public function divisions(): array
    {
        return config('game.ranked.divisions', []);
    }

    /**
     * @return array{key: string, label: string, min: int, max: ?int}|null
     */
    public function divisionDefinitionByKey(string $key): ?array
    {
        foreach ($this->divisions() as $div) {
            if ($div['key'] === $key) {
                return $div;
            }
        }

        return null;
    }

    public function divisionKeyForPoints(int $points): string
    {
        foreach ($this->divisions() as $div) {
            $max = $div['max'];
            if ($max === null) {
                if ($points >= $div['min']) {
                    return $div['key'];
                }
            } else {
                if ($points >= $div['min'] && $points <= $max) {
                    return $div['key'];
                }
            }
        }

        return 'ferro';
    }

    public function divisionLabelForPoints(int $points): string
    {
        $key = $this->divisionKeyForPoints($points);
        foreach ($this->divisions() as $div) {
            if ($div['key'] === $key) {
                return $div['label'];
            }
        }

        return $key;
    }

    public function divisionLabelForKey(?string $divisionKey): ?string
    {
        if ($divisionKey === null || $divisionKey === '') {
            return null;
        }

        foreach ($this->divisions() as $div) {
            if ($div['key'] === $divisionKey) {
                return $div['label'];
            }
        }

        return $divisionKey;
    }

    public function tierIndex(string $divisionKey): int
    {
        foreach ($this->divisions() as $i => $div) {
            if ($div['key'] === $divisionKey) {
                return $i;
            }
        }

        return 0;
    }

    /**
     * Fila ranqueada: mesmo tier sempre; tier adjacente só após N segundos na fila (maior espera dos dois).
     */
    public function pairingAllowed(string $divKeyA, string $divKeyB, int $maxWaitSeconds): bool
    {
        $a = $this->tierIndex($divKeyA);
        $b = $this->tierIndex($divKeyB);
        $diff = abs($a - $b);
        if ($diff === 0) {
            return true;
        }
        if ($diff === 1) {
            $need = (int) config('game.ranked.pairing.same_division_seconds', 15);

            return $maxWaitSeconds >= $need;
        }

        return false;
    }

    /** Aplicar piso Ferro (pontos não negativos). */
    public function clampPoints(int $points): int
    {
        return max(0, $points);
    }

    /**
     * Retorna [deltaWinner, deltaLoser] antes do multiplicador de bot (inteiro).
     * Underdog = divisão numericamente mais baixa (ferro < bronze).
     */
    public function pointDeltas(string $winnerDivKey, string $loserDivKey): array
    {
        $w = $this->tierIndex($winnerDivKey);
        $l = $this->tierIndex($loserDivKey);

        if ($w === $l) {
            return [22, -22];
        }

        // Vencedor é underdog (tier menor = índice menor)
        if ($w < $l) {
            return [35, -35];
        }

        // Vencedor é favorito
        return [22, -22];
    }

    public function minLevel(): int
    {
        return (int) config('game.ranked.min_level', 20);
    }

    public function userMeetsRankedLevel(User $user): bool
    {
        $nivel = $user->playerLevel?->nivel ?? 1;

        return $nivel >= $this->minLevel();
    }
}
