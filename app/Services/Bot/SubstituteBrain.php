<?php

namespace App\Services\Bot;

use App\Models\GameMatch;
use App\Services\Game\CardCatalog;
use App\Services\Game\EffectResolver;
use App\Services\Game\MatchEngine;

/**
 * Oponente substituto por heurísticas (sem ML/LLM).
 * Usa perfil de agressividade e chance de erro configurável por modo/divisão.
 */
class SubstituteBrain
{
    public function __construct(
        private MatchEngine $engine,
        private EffectResolver $effects,
        private SubstituteDifficultyResolver $difficultyResolver,
    ) {}

    /**
     * @return array<string, mixed>|null null = não deve continuar este job
     */
    public function nextPayload(GameMatch $match, int $botSlot): ?array
    {
        $estado = $match->estado ?? null;
        if (! is_array($estado)) {
            return null;
        }

        $turnSlot = (int) ($estado['jogador_da_vez'] ?? $botSlot);
        if ($turnSlot !== $botSlot) {
            return null;
        }

        $profile = $this->difficultyResolver->forMatch($match, $botSlot);
        $candidates = $this->buildActionCandidates($estado, $botSlot, $profile);

        if ($candidates === []) {
            return ['acao' => 'finalizar_turno'];
        }

        $invocacaoPrioritaria = $this->melhorInvocacaoSeDevePreencherCampo($estado, $botSlot, $candidates);
        if ($invocacaoPrioritaria !== null) {
            return $invocacaoPrioritaria;
        }

        return $this->chooseCandidate($candidates, $profile);
    }

    /**
     * @param  array{aggression: float, mistake_chance: float}  $profile
     * @return list<array{payload: array<string, mixed>, score: float}>
     */
    private function buildActionCandidates(array $estado, int $botSlot, array $profile): array
    {
        $candidates = [];
        $oppSlot = $botSlot === 1 ? 2 : 1;
        $aggression = max(0.0, min(1.0, (float) ($profile['aggression'] ?? 0.55)));
        $oppVida = (int) ($estado['jogadores'][(string) $oppSlot]['vida'] ?? 0);
        $oppField = $estado['campo'][$oppSlot] ?? [];
        $tauntIds = $this->strictTauntInstanceIds($estado, $oppSlot);
        $fieldMax = (int) config('game.match.field.max_units_per_player', 5);
        $campoCount = count($estado['campo'][$botSlot] ?? []);
        $energy = (int) (($estado['jogadores'][(string) $botSlot]['energia_atual'] ?? 0));

        foreach ($estado['campo'][$botSlot] ?? [] as $unit) {
            if (! ($unit['pode_atacar'] ?? false) || ($unit['foi_invocado_neste_turno'] ?? false)) {
                continue;
            }

            $attackerId = (string) $unit['instancia_id'];
            $cardId = (int) ($unit['card_id'] ?? 0);
            $attackPower = $this->engine->getUnitAttack($estado, $botSlot, $unit);

            $canFaceWithUnit = $oppField === []
                || ($tauntIds === [] && $cardId && $this->effects->hasPassive($cardId, 'ataque_direto_jogador'));

            if ($canFaceWithUnit) {
                $lethal = $attackPower >= $oppVida && $oppVida > 0;
                $faceScore = 120.0 + ($attackPower * 4.0) + ($aggression * 40.0);
                if ($lethal) {
                    $faceScore += 500.0;
                }

                $candidates[] = [
                    'payload' => ['acao' => 'atacar_jogador', 'instancia_id' => $attackerId],
                    'score' => $faceScore,
                ];
            }

            $targets = collect($oppField)->values()->all();
            if ($tauntIds !== []) {
                $targets = array_values(array_filter(
                    $targets,
                    fn ($defender) => in_array((string) $defender['instancia_id'], $tauntIds, true)
                ));
            }

            foreach ($targets as $defender) {
                $defenderId = (string) $defender['instancia_id'];
                $defHp = (int) ($defender['vida_atual'] ?? 0);
                $defValue = $this->unitBoardValue($defender);
                $kills = $attackPower >= $defHp && $defHp > 0;
                $tradeScore = ($kills ? 95.0 : 25.0) + $defValue * 0.35 + ($aggression * 20.0);
                if ($kills) {
                    $tradeScore += 60.0;
                }

                $candidates[] = [
                    'payload' => [
                        'acao' => 'atacar_unidade',
                        'instancia_id' => $attackerId,
                        'alvo_instancia_id' => $defenderId,
                    ],
                    'score' => $tradeScore,
                ];
            }
        }

        foreach ($estado['jogadores'][(string) $botSlot]['mao'] ?? [] as $handCard) {
            $spellCandidate = $this->spellCandidate($estado, $botSlot, $handCard, $energy, $aggression);
            if ($spellCandidate !== null) {
                $candidates[] = $spellCandidate;
            }
        }

        if ($campoCount < $fieldMax) {
            foreach ($estado['jogadores'][(string) $botSlot]['mao'] ?? [] as $handCard) {
                $cardId = (int) ($handCard['card_id'] ?? 0);
                $catalog = CardCatalog::get($cardId);
                if (! $catalog) {
                    continue;
                }
                if (($catalog->tipo ?? 'unit') === 'spell') {
                    continue;
                }
                $cost = (int) ($catalog->custo ?? 999);
                if ($cost > $energy) {
                    continue;
                }

                $boardValue = (int) ($catalog->ataque ?? 0) + (int) ($catalog->vida ?? 0);
                $tempoBonus = max(0, 6 - $cost) * 3.0;
                $invokeScore = 35.0 + $boardValue * 2.2 + $tempoBonus + ($aggression * 8.0);

                $candidates[] = [
                    'payload' => ['acao' => 'invocar', 'instancia_id' => (string) $handCard['instancia_id']],
                    'score' => $invokeScore,
                ];
            }
        }

        $candidates[] = [
            'payload' => ['acao' => 'finalizar_turno'],
            'score' => 5.0,
        ];

        return $candidates;
    }

    /**
     * @return array{payload: array<string, mixed>, score: float}|null
     */
    private function spellCandidate(array $estado, int $botSlot, array $handCard, int $energy, float $aggression): ?array
    {
        $cardId = (int) ($handCard['card_id'] ?? 0);
        $card = CardCatalog::get($cardId);
        if (! $card || ($card->tipo ?? 'unit') !== 'spell') {
            return null;
        }

        $cost = (int) ($card->custo ?? 999);
        if ($cost > $energy) {
            return null;
        }

        $effect = $card->skills[0]->efeito ?? [];
        if (! is_array($effect)) {
            return null;
        }

        $tipo = (string) ($effect['tipo'] ?? '');
        $target = null;
        $score = 0.0;

        if (($effect['alvo'] ?? null) === 'unidade_inimiga') {
            $target = $this->bestEnemySpellTarget($estado, $botSlot, $tipo, (int) ($effect['valor'] ?? 0));
            if (! $target) {
                return null;
            }
            $targetValue = $this->unitBoardValue($target);
            $score = match ($tipo) {
                'dano_alvo' => 55.0 + $targetValue + (((int) ($effect['valor'] ?? 0) >= (int) ($target['vida_atual'] ?? 99)) ? 160.0 : 0.0),
                'silencio' => 45.0 + $targetValue * 0.8,
                'paralisia' => 40.0 + max(0, (int) ($target['ataque_atual'] ?? 0)) * 10.0,
                'debuff_ataque' => 35.0 + max(0, (int) ($target['ataque_atual'] ?? 0)) * 8.0,
                default => 20.0 + $targetValue * 0.4,
            };
            $score += $aggression * 12.0;
        } elseif (($effect['alvo'] ?? null) === 'unidade_aliada') {
            $target = $this->bestAllySpellTarget($estado, $botSlot, $tipo);
            if (! $target) {
                return null;
            }
            $score = match ($tipo) {
                'cura_alvo' => 45.0 + max(0, (int) ($target['vida_max'] ?? 0) - (int) ($target['vida_atual'] ?? 0)) * 12.0,
                'buff_ataque_turno' => 40.0 + (($target['pode_atacar'] ?? false) ? 40.0 : 0.0) + $aggression * 14.0,
                'veu_arcano' => 35.0 + $this->unitBoardValue($target) * 0.7,
                'liberar_ataque_extra' => 60.0 + $this->unitBoardValue($target) * 0.7,
                default => 20.0 + $this->unitBoardValue($target) * 0.4,
            };
        } elseif ($tipo === 'cura_todos_aliados') {
            $damaged = array_filter(
                $estado['campo'][$botSlot] ?? [],
                fn ($unit) => (int) ($unit['vida_atual'] ?? 0) < (int) ($unit['vida_max'] ?? 0)
            );
            if ($damaged === []) {
                return null;
            }
            $score = 40.0 + count($damaged) * 22.0;
        } else {
            return null;
        }

        $payload = [
            'acao' => 'jogar_feitico',
            'instancia_id' => (string) ($handCard['instancia_id'] ?? ''),
        ];
        if ($target) {
            $payload['alvo_instancia_id'] = (string) ($target['instancia_id'] ?? '');
        }

        return [
            'payload' => $payload,
            'score' => $score - ($cost * 4.0),
        ];
    }

    private function bestEnemySpellTarget(array $estado, int $botSlot, string $tipo, int $spellValue): ?array
    {
        $oppSlot = $botSlot === 1 ? 2 : 1;
        $targets = array_values($estado['campo'][$oppSlot] ?? []);
        $tauntIds = $this->strictTauntInstanceIds($estado, $oppSlot);
        if ($tauntIds !== []) {
            $targets = array_values(array_filter(
                $targets,
                fn ($unit) => in_array((string) ($unit['instancia_id'] ?? ''), $tauntIds, true)
            ));
        }
        if ($targets === []) {
            return null;
        }

        usort($targets, function ($left, $right) use ($tipo, $spellValue) {
            $leftKill = $tipo === 'dano_alvo' && $spellValue >= (int) ($left['vida_atual'] ?? 99);
            $rightKill = $tipo === 'dano_alvo' && $spellValue >= (int) ($right['vida_atual'] ?? 99);
            if ($leftKill !== $rightKill) {
                return $rightKill <=> $leftKill;
            }

            return $this->unitBoardValue($right) <=> $this->unitBoardValue($left);
        });

        return $targets[0] ?? null;
    }

    private function bestAllySpellTarget(array $estado, int $botSlot, string $tipo): ?array
    {
        $targets = array_values($estado['campo'][$botSlot] ?? []);
        if ($targets === []) {
            return null;
        }

        if ($tipo === 'cura_alvo') {
            $targets = array_values(array_filter(
                $targets,
                fn ($unit) => (int) ($unit['vida_atual'] ?? 0) < (int) ($unit['vida_max'] ?? 0)
            ));
            if ($targets === []) {
                return null;
            }
            usort($targets, fn ($left, $right) =>
                ((int) ($right['vida_max'] ?? 0) - (int) ($right['vida_atual'] ?? 0))
                <=>
                ((int) ($left['vida_max'] ?? 0) - (int) ($left['vida_atual'] ?? 0))
            );

            return $targets[0] ?? null;
        }

        if ($tipo === 'liberar_ataque_extra') {
            $targets = array_values(array_filter(
                $targets,
                fn ($unit) => ! ($unit['pode_atacar'] ?? false) && ! ($unit['foi_invocado_neste_turno'] ?? false)
            ));
            if ($targets === []) {
                return null;
            }
        }

        usort($targets, fn ($left, $right) => $this->unitBoardValue($right) <=> $this->unitBoardValue($left));

        return $targets[0] ?? null;
    }

    /**
     * Invoca antes de atacar para não bloquear jogadas com `ja_atacou_neste_turno`.
     *
     * @param  list<array{payload: array<string, mixed>, score: float}>  $candidates
     * @return array<string, mixed>|null
     */
    private function melhorInvocacaoSeDevePreencherCampo(array $estado, int $botSlot, array $candidates): ?array
    {
        $fieldMax = (int) config('game.match.field.max_units_per_player', 5);
        $campoCount = count($estado['campo'][$botSlot] ?? []);
        if ($campoCount >= $fieldMax) {
            return null;
        }

        $energia = (int) ($estado['jogadores'][(string) $botSlot]['energia_atual'] ?? 0);
        $invocacoes = array_values(array_filter(
            $candidates,
            fn ($candidate) => ($candidate['payload']['acao'] ?? '') === 'invocar'
        ));
        if ($invocacoes === []) {
            return null;
        }

        $acaoCritica = array_values(array_filter(
            $candidates,
            fn ($candidate) => ($candidate['payload']['acao'] ?? '') !== 'finalizar_turno'
                && ($candidate['score'] ?? 0.0) >= 400.0
        ));
        if ($acaoCritica !== []) {
            return null;
        }

        $custoMinimo = null;
        foreach ($invocacoes as $invocacao) {
            $instanciaMao = (string) ($invocacao['payload']['instancia_id'] ?? '');
            foreach ($estado['jogadores'][(string) $botSlot]['mao'] ?? [] as $cartaMao) {
                if ((string) ($cartaMao['instancia_id'] ?? '') !== $instanciaMao) {
                    continue;
                }
                $catalogo = CardCatalog::get((int) ($cartaMao['card_id'] ?? 0));
                if (! $catalogo) {
                    continue;
                }
                $custo = (int) ($catalogo->custo ?? 999);
                $custoMinimo = $custoMinimo === null ? $custo : min($custoMinimo, $custo);
            }
        }

        if ($custoMinimo === null || $energia < $custoMinimo) {
            return null;
        }

        usort($invocacoes, fn ($esquerda, $direita) => $direita['score'] <=> $esquerda['score']);

        return $invocacoes[0]['payload'];
    }

    /**
     * @param  list<array{payload: array<string, mixed>, score: float}>  $candidates
     * @param  array{aggression: float, mistake_chance: float}  $profile
     * @return array<string, mixed>
     */
    private function chooseCandidate(array $candidates, array $profile): array
    {
        usort($candidates, fn ($left, $right) => $right['score'] <=> $left['score']);

        $mistakeChance = max(0.0, min(0.35, (float) ($profile['mistake_chance'] ?? 0.08)));
        $topScore = $candidates[0]['score'] ?? 0.0;
        $isCritical = $topScore >= 400.0;

        if (! $isCritical && $mistakeChance > 0.0 && mt_rand(1, 1000) <= (int) round($mistakeChance * 1000)) {
            $weakerPool = array_values(array_filter(
                $candidates,
                fn ($candidate) => ($candidate['payload']['acao'] ?? '') !== 'finalizar_turno'
                    && $candidate['score'] < $topScore * 0.85
            ));

            if ($weakerPool !== []) {
                return $weakerPool[array_rand($weakerPool)]['payload'];
            }
        }

        return $candidates[0]['payload'];
    }

    private function unitBoardValue(array $unit): float
    {
        $cardId = (int) ($unit['card_id'] ?? 0);
        $catalog = CardCatalog::get($cardId);

        return (float) ((int) ($catalog->ataque ?? 0)
            + (int) ($unit['vida_atual'] ?? 0)
            + (int) ($unit['bonus_ataque'] ?? 0)
            + (int) ($unit['bonus_ataque_turno'] ?? 0));
    }

    /**
     * @return list<string>
     */
    private function strictTauntInstanceIds(array $estado, int $oppSlot): array
    {
        $ids = [];
        foreach ($estado['campo'][$oppSlot] ?? [] as $unit) {
            if (($unit['silenciado'] ?? false) || ! ($unit['flags']['taunt_self'] ?? false)) {
                continue;
            }
            $ids[] = (string) $unit['instancia_id'];
        }

        return $ids;
    }
}
