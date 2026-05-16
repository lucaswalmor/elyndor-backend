<?php

namespace App\Services\Game;

use Illuminate\Support\Str;

/**
 * Interpreta efeito.tipo do seed — ver roadmap/cards_seed.json
 */
class EffectResolver
{
    private ?MatchEngine $engine = null;

    public function bindEngine(MatchEngine $engine): void
    {
        $this->engine = $engine;
    }

    public function triggerSkills(
        array &$estado,
        int $slot,
        string $gatilho,
        ?array $sourceUnit,
        array &$animacoes,
        array $context = [],
    ): void {
        if (! $sourceUnit || ($sourceUnit['silenciado'] ?? false)) {
            return;
        }

        $card = CardCatalog::get($sourceUnit['card_id'] ?? 0);
        if (! $card) {
            return;
        }

        foreach ($card->skills as $skill) {
            if ($skill->gatilho === $gatilho) {
                $this->apply($estado, $slot, $sourceUnit, $skill->efeito, $animacoes, $context);
            }
        }
    }

    public function applyBattleCry(array &$estado, int $slot, array &$unit, array &$animacoes): void
    {
        $card = CardCatalog::get($unit['card_id']);
        if (! $card) {
            return;
        }
        foreach ($card->skills as $skill) {
            if ($skill->tipo === 'batalha_cry' || $skill->gatilho === 'ao_invocar') {
                $this->apply($estado, $slot, $unit, $skill->efeito, $animacoes, []);
            }
        }
    }

    public function apply(
        array &$estado,
        int $slot,
        ?array &$unit,
        array $efeito,
        array &$animacoes,
        array $context = [],
    ): void {
        $tipo = $efeito['tipo'] ?? '';
        $opp = $slot === 1 ? 2 : 1;

        match ($tipo) {
            'charge' => $this->charge($unit, $efeito, $animacoes),
            'dano_todas_inimigas' => $this->damageAllEnemyUnits($estado, $slot, (int) ($efeito['valor'] ?? 0), $animacoes),
            'debuff_ataque' => $this->addEffect($unit, 'debuff_ataque', $efeito, $animacoes),
            'veneno' => $this->addEffect($unit ?? $context['alvo'] ?? null, 'veneno', $efeito, $animacoes),
            'silencio' => $this->silence($context['alvo'] ?? null, $animacoes),
            'cura_aleatorio_aliado' => $this->healRandomAlly($estado, $slot, (int) ($efeito['valor'] ?? 1), $animacoes),
            'cura_por_dano' => $this->healPlayer($estado, $slot, (int) ($context['dano'] ?? 0), $animacoes),
            'energia_temporaria' => $this->bonusEnergy($estado, $slot, (int) ($efeito['valor'] ?? 1), $animacoes),
            'escudo_primeiro_golpe' => $this->addFlag($unit, 'escudo', $animacoes),
            'nao_pode_atacar' => $this->addEffect($context['alvo'] ?? null, 'nao_pode_atacar', $efeito, $animacoes),
            'forcar_ataque_a_si' => $this->addFlag($unit, 'taunt_self', $animacoes),
            'aura_buff_ataque' => null, // aplicado em getUnitAttack
            'aura_debuff_ataque' => null,
            'ressurreicao_unica' => $this->flagRessurreicao($estado, $slot),
            'reviver_ultimo_aliado' => $this->reviveLastAlly($estado, $slot, $animacoes),
            'retornar_aliado_mao' => $this->returnAllyToHand($estado, $slot, $context, $animacoes),
            'destruir_aleatorio_inimigo' => $this->destroyRandomEnemy($estado, $slot, $animacoes),
            'revelar_proxima_carta_deck' => $this->revealNext($estado, $slot, $animacoes),
            'confusao' => $this->addEffect($context['alvo'] ?? null, 'confusao', $efeito, $animacoes),
            'crescimento_por_morte' => $this->addFlag($unit, 'crescimento_por_morte', $animacoes),
            default => null,
        };
    }

    public function hasPassive(int $cardId, string $tipo): bool
    {
        $card = CardCatalog::get($cardId);
        if (! $card) {
            return false;
        }
        foreach ($card->skills as $skill) {
            if (($skill->efeito['tipo'] ?? '') === $tipo) {
                return true;
            }
        }

        return false;
    }

    public function auraAttackBonus(array $estado, int $slot): int
    {
        $bonus = 0;
        foreach ($estado['campo'][$slot] as $u) {
            $card = CardCatalog::get($u['card_id']);
            if (! $card) {
                continue;
            }
            foreach ($card->skills as $skill) {
                if (($skill->efeito['tipo'] ?? '') === 'aura_buff_ataque') {
                    $bonus += (int) ($skill->efeito['valor'] ?? 0);
                }
            }
        }

        return $bonus;
    }

    public function auraEnemyAttackDebuff(array $estado, int $slot): int
    {
        $opp = $slot === 1 ? 2 : 1;
        $debuff = 0;
        foreach ($estado['campo'][$opp] as $u) {
            $card = CardCatalog::get($u['card_id']);
            if (! $card) {
                continue;
            }
            foreach ($card->skills as $skill) {
                if (($skill->efeito['tipo'] ?? '') === 'aura_debuff_ataque') {
                    $debuff += (int) ($skill->efeito['valor'] ?? 0);
                }
            }
        }

        return $debuff;
    }

    private function charge(?array &$unit, array $efeito, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        $unit['pode_atacar'] = $efeito['pode_atacar_imediato'] ?? true;
        $unit['foi_invocado_neste_turno'] = false;
        $bonus = (int) ($efeito['bonus_ataque'] ?? 0);
        $unit['bonus_ataque'] = ($unit['bonus_ataque'] ?? 0) + $bonus;
        $animacoes[] = ['tipo' => 'charge', 'instancia_id' => $unit['instancia_id']];
    }

    private function damageAllEnemyUnits(array &$estado, int $slot, int $dmg, array &$animacoes): void
    {
        $opp = $slot === 1 ? 2 : 1;
        foreach ($estado['campo'][$opp] as &$u) {
            $this->engine->damageUnit($estado, $opp, $u, $dmg, $animacoes);
        }
    }

    private function addEffect(?array &$unit, string $tipo, array $efeito, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        $unit['efeitos'][] = [
            'tipo' => $tipo,
            'valor' => $efeito['valor'] ?? 1,
            'duracao' => $efeito['duracao'] ?? 1,
        ];
        if ($tipo === 'silencio' || $tipo === 'veneno') {
            $unit['silenciado'] = $tipo === 'silencio';
        }
        $animacoes[] = ['tipo' => 'efeito', 'instancia_id' => $unit['instancia_id'], 'efeito' => $tipo];
    }

    private function silence(?array &$unit, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        $unit['silenciado'] = true;
        $unit['efeitos'][] = ['tipo' => 'silencio', 'duracao' => 1];
        $animacoes[] = ['tipo' => 'silencio', 'instancia_id' => $unit['instancia_id']];
    }

    private function healRandomAlly(array &$estado, int $slot, int $amount, array &$animacoes): void
    {
        $units = $estado['campo'][$slot];
        if (empty($units)) {
            $estado['jogadores'][(string) $slot]['vida'] = min(20, $estado['jogadores'][(string) $slot]['vida'] + $amount);

            return;
        }
        $idx = array_rand($units);
        $card = CardCatalog::get($units[$idx]['card_id']);
        $max = $card?->vida ?? 10;
        $units[$idx]['vida_atual'] = min($max, $units[$idx]['vida_atual'] + $amount);
        $estado['campo'][$slot] = $units;
        $animacoes[] = ['tipo' => 'cura', 'instancia_id' => $units[$idx]['instancia_id'], 'valor' => $amount];
    }

    private function healPlayer(array &$estado, int $slot, int $amount, array &$animacoes): void
    {
        $estado['jogadores'][(string) $slot]['vida'] = min(20, $estado['jogadores'][(string) $slot]['vida'] + $amount);
        $animacoes[] = ['tipo' => 'cura_jogador', 'player' => $slot, 'valor' => $amount];
    }

    private function bonusEnergy(array &$estado, int $slot, int $val, array &$animacoes): void
    {
        $estado['jogadores'][(string) $slot]['energia_bonus_turno'] =
            ($estado['jogadores'][(string) $slot]['energia_bonus_turno'] ?? 0) + $val;
        $animacoes[] = ['tipo' => 'energia', 'player' => $slot, 'valor' => $val];
    }

    private function addFlag(?array &$unit, string $flag, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        $unit['flags'][$flag] = true;
        $animacoes[] = ['tipo' => 'flag', 'instancia_id' => $unit['instancia_id'], 'flag' => $flag];
    }

    private function flagRessurreicao(array &$estado, int $slot): void
    {
        $estado['jogadores'][(string) $slot]['ressurreicao_pendente'] = true;
    }

    private function reviveLastAlly(array &$estado, int $slot, array &$animacoes): void
    {
        $dead = $estado['ultimo_aliado_morto'][(string) $slot] ?? null;
        if (! $dead || count($estado['campo'][$slot]) >= config('game.match.field.max_units_per_player')) {
            return;
        }
        $dead['instancia_id'] = (string) Str::uuid();
        $dead['vida_atual'] = max(1, (int) floor(($dead['vida_max'] ?? 1) / 2));
        $dead['pode_atacar'] = false;
        $dead['foi_invocado_neste_turno'] = true;
        $estado['campo'][$slot][] = $dead;
        $animacoes[] = ['tipo' => 'reviver', 'instancia_id' => $dead['instancia_id']];
    }

    private function returnAllyToHand(array &$estado, int $slot, array $context, array &$animacoes): void
    {
        $targetId = $context['alvo_instancia_id'] ?? null;
        if (! $targetId) {
            return;
        }
        $unit = $this->engine->findUnit($estado, $slot, $targetId);
        if (! $unit) {
            return;
        }
        $estado['campo'][$slot] = array_values(array_filter(
            $estado['campo'][$slot],
            fn ($u) => $u['instancia_id'] !== $targetId
        ));
        if (count($estado['jogadores'][(string) $slot]['mao']) < config('game.match.field.max_hand_size')) {
            $estado['jogadores'][(string) $slot]['mao'][] = [
                'instancia_id' => (string) Str::uuid(),
                'card_id' => $unit['card_id'],
            ];
        }
        $animacoes[] = ['tipo' => 'retornar_mao', 'instancia_id' => $targetId];
    }

    private function destroyRandomEnemy(array &$estado, int $slot, array &$animacoes): void
    {
        $opp = $slot === 1 ? 2 : 1;
        if (empty($estado['campo'][$opp])) {
            return;
        }
        $idx = array_rand($estado['campo'][$opp]);
        $unit = $estado['campo'][$opp][$idx];
        if ($this->hasPassive($unit['card_id'], 'imune_remocao_direta')) {
            return;
        }
        $this->engine->killUnit($estado, $opp, $unit['instancia_id'], $animacoes);
    }

    private function revealNext(array &$estado, int $slot, array &$animacoes): void
    {
        $deck = $estado['jogadores'][(string) $slot]['deck'];
        if (empty($deck)) {
            return;
        }
        $estado['revelacoes'][(string) $slot][] = $deck[0];
        $animacoes[] = ['tipo' => 'revelar', 'player' => $slot, 'card_id' => $deck[0]];
    }
}
