<?php

namespace App\Services\Ranked;

use App\Models\GameMatch;
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
     * Comparar dois snapshots de pontos e detetar cruzamento de limite entre divisões (para logs Fase F e futuro modal rank-up).
     *
     * @return array{
     *     changed: bool,
     *     direction: 'promote'|'demote'|'none',
     *     from_key: string,
     *     to_key: string,
     *     from_label: string|null,
     *     to_label: string|null,
     * }
     */
    public function eloBracketMovementBetweenSnapshots(int $pointsBefore, int $pointsAfter): array
    {
        $fromKey = $this->divisionKeyForPoints($pointsBefore);
        $toKey = $this->divisionKeyForPoints($pointsAfter);
        $fromLabel = $this->divisionLabelForKey($fromKey);
        $toLabel = $this->divisionLabelForKey($toKey);

        if ($fromKey === $toKey) {
            return [
                'changed' => false,
                'direction' => 'none',
                'from_key' => $fromKey,
                'to_key' => $toKey,
                'from_label' => $fromLabel,
                'to_label' => $toLabel,
            ];
        }

        $fromTi = $this->tierIndex($fromKey);
        $toTi = $this->tierIndex($toKey);
        $direction = $toTi > $fromTi ? 'promote' : 'demote';

        return [
            'changed' => true,
            'direction' => $direction,
            'from_key' => $fromKey,
            'to_key' => $toKey,
            'from_label' => $fromLabel,
            'to_label' => $toLabel,
        ];
    }

    /**
     * Fila ranqueada: mesmo tier sempre; tier adjacente após adjacent_division_seconds;
     * qualquer gap após wide_pairing_seconds ou quando $relaxarBaixaPopulacao (só 2 na fila / poucos online).
     */
    public function pairingAllowed(
        string $divKeyA,
        string $divKeyB,
        int $maxWaitSeconds,
        bool $relaxarBaixaPopulacao = false,
    ): bool {
        if ($relaxarBaixaPopulacao) {
            return true;
        }

        $indiceA = $this->tierIndex($divKeyA);
        $indiceB = $this->tierIndex($divKeyB);
        $diferencaDivisoes = abs($indiceA - $indiceB);

        if ($diferencaDivisoes === 0) {
            return true;
        }

        $segundosAdjacente = (int) config('game.ranked.pairing.adjacent_division_seconds', 30);
        if ($diferencaDivisoes === 1) {
            return $maxWaitSeconds >= $segundosAdjacente;
        }

        if ($diferencaDivisoes === 2) {
            return $maxWaitSeconds >= $segundosAdjacente;
        }

        $segundosAmplo = (int) config('game.ranked.pairing.wide_pairing_seconds', 60);

        return $maxWaitSeconds >= $segundosAmplo;
    }

    /** Aplicar piso Ferro (pontos não negativos). */
    public function clampPoints(int $points): int
    {
        return max(0, $points);
    }

    /**
     * Retorna [deltaWinner, deltaLoser] antes do multiplicador de bot (inteiro).
     * Underdog = divisão com índice menor (ferro &lt; bronze &lt; …).
     *
     * @see config('game.ranked.scoring')
     */
    public function pointDeltas(string $winnerDivKey, string $loserDivKey): array
    {
        $scoring = config('game.ranked.scoring', []);
        $winBase = (int) ($scoring['win_base'] ?? 20);
        $winPerTier = (int) ($scoring['win_per_tier_underdog'] ?? 5);
        $lossBase = (int) ($scoring['loss_base'] ?? -20);
        $lossPerTierFavorite = (int) ($scoring['loss_per_tier_favorite'] ?? 5);

        $indiceVencedor = $this->tierIndex($winnerDivKey);
        $indicePerdedor = $this->tierIndex($loserDivKey);

        $gapDivisoes = max(0, $indicePerdedor - $indiceVencedor);
        $deltaVencedor = $winBase + ($winPerTier * $gapDivisoes);
        $deltaPerdedor = $lossBase - ($lossPerTierFavorite * $gapDivisoes);

        return [$deltaVencedor, $deltaPerdedor];
    }

    /**
     * Pré-visualização de MMR para a modal de aceitar partida ranqueada.
     *
     * @return array{pontos_vitoria: int, pontos_derrota: int}|null
     */
    public function previsaoPontosRanqueada(
        int $pontosJogador,
        int $pontosOponente,
        bool $oponenteEhBot = false,
    ): array {
        $divisaoJogador = $this->divisionKeyForPoints($pontosJogador);
        $divisaoOponente = $this->divisionKeyForPoints($pontosOponente);

        [$deltaVitoria] = $this->pointDeltas($divisaoJogador, $divisaoOponente);
        [, $deltaDerrota] = $this->pointDeltas($divisaoOponente, $divisaoJogador);

        if ($oponenteEhBot) {
            $multiplicador = (float) config('game.bots.ranked_points_multiplier', 0.5);
            $deltaVitoria = (int) round($deltaVitoria * $multiplicador);
            $deltaDerrota = (int) round($deltaDerrota * $multiplicador);
        }

        return [
            'pontos_vitoria' => $deltaVitoria,
            'pontos_derrota' => $deltaDerrota,
        ];
    }

    /**
     * Payload do oponente na oferta de partida (Echo + polling).
     *
     * @return array<string, mixed>
     */
    public function dadosOponenteParaOferta(User $jogador, User $oponente, GameMatch $partida): array
    {
        $partida->loadMissing('players');
        $jogadorOponente = $partida->players->first(
            fn ($player) => (int) $player->user_id === (int) $oponente->id,
        );

        $pontosOponente = (int) ($oponente->ranked_points ?? 0);
        $chaveDivisao = $this->divisionKeyForPoints($pontosOponente);

        $dados = [
            'nome' => $oponente->nickname,
            'divisao' => $chaveDivisao,
            'divisao_label' => $this->divisionLabelForPoints($pontosOponente),
            'pontos' => $pontosOponente,
            'eh_bot' => (bool) ($jogadorOponente?->is_bot ?? false),
        ];

        if ($partida->modo !== 'ranqueada') {
            return $dados;
        }

        $previsao = $this->previsaoPontosRanqueada(
            (int) ($jogador->ranked_points ?? 0),
            $pontosOponente,
            $dados['eh_bot'],
        );

        return array_merge($dados, $previsao);
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
