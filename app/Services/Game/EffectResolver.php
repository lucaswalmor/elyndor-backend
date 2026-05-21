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

    public function applyBattleCry(
        array &$estado,
        int $slot,
        array &$unit,
        array &$animacoes,
        array $context = [],
    ): void {
        $card = CardCatalog::get($unit['card_id']);
        if (! $card) {
            return;
        }
        $context['invocador_instancia_id'] = $unit['instancia_id'];
        foreach ($card->skills as $skill) {
            if ($skill->tipo === 'batalha_cry' || $skill->gatilho === 'ao_invocar') {
                $this->apply($estado, $slot, $unit, $skill->efeito, $animacoes, $context);
            }
        }
    }

    /**
     * Flags persistentes definidas no seed (ex.: Devorador — crescimento por morte).
     */
    public function initializeUnitFlags(array &$unit, $card): void
    {
        foreach ($card->skills ?? [] as $skill) {
            if (($skill->efeito['tipo'] ?? '') === 'crescimento_por_morte') {
                $unit['flags']['crescimento_por_morte'] = true;
                $unit['crescimento_atk'] = 0;
                $unit['crescimento_hp'] = 0;
            }
        }
    }

    public function cardRequiresAllyTargetForBattleCry($card): bool
    {
        foreach ($card->skills ?? [] as $skill) {
            if ($skill->tipo !== 'batalha_cry' && $skill->gatilho !== 'ao_invocar') {
                continue;
            }
            $efeito = $skill->efeito ?? [];
            if (($efeito['tipo'] ?? '') === 'retornar_aliado_mao' &&
                ($efeito['selecao'] ?? '') === 'aliado_campo') {
                return true;
            }
        }

        return false;
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

        // Extrair alvos em variáveis antes de qualquer chamada que use referência.
        $alvo       = $context['alvo'] ?? null;

        switch ($tipo) {
            case 'charge':
                $this->charge($estado, $unit, $efeito, $animacoes);
                break;
            case 'dano_todas_inimigas':
                $this->damageAllEnemyUnits($estado, $slot, (int) ($efeito['valor'] ?? 0), $animacoes);
                break;
            case 'debuff_ataque':
                // FIX: aplica no ALVO (defensor), não no atacante
                $this->addEffect($estado, $alvo, 'debuff_ataque', $efeito, $animacoes);
                break;
            case 'veneno':
                // FIX: aplica no ALVO (defensor), não no atacante
                $this->addEffect($estado, $alvo, 'veneno', $efeito, $animacoes);
                break;
            case 'silencio':
                $this->silence($estado, $alvo, $animacoes);
                break;
            case 'cura_aleatorio_aliado':
                $this->healRandomAlly($estado, $slot, (int) ($efeito['valor'] ?? 1), $animacoes);
                break;
            case 'cura_por_dano':
                // FIX: cura a UNIDADE atacante (Costureira), não o jogador
                $this->healUnitBySelf($estado, $slot, $unit, (int) ($context['dano'] ?? 0), (int) ($efeito['maximo'] ?? 99), $animacoes);
                break;
            case 'energia_temporaria':
                $this->bonusEnergy($estado, $slot, (int) ($efeito['valor'] ?? 1), $animacoes);
                break;
            case 'escudo_primeiro_golpe':
                $this->addFlag($estado, $unit, 'escudo', $animacoes);
                break;
            case 'nao_pode_atacar':
                $this->addEffect($estado, $alvo, 'nao_pode_atacar', $efeito, $animacoes);
                break;
            case 'forcar_ataque_a_si':
                $this->addFlag($estado, $unit, 'taunt_self', $animacoes);
                break;
            case 'aura_buff_ataque':   // aplicado em getUnitAttack
            case 'aura_debuff_ataque': // aplicado em getUnitAttack
                break;
            case 'ressurreicao_unica':
                $this->flagRessurreicao($estado, $slot);
                break;
            case 'reviver_ultimo_aliado':
                $this->reviveLastAlly($estado, $slot, $efeito, $animacoes);
                break;
            case 'retornar_aliado_mao':
                $this->returnAllyToHand($estado, $slot, $context, $animacoes);
                break;
            case 'destruir_aleatorio_inimigo':
                // FIX: passa flag para não disparar ao_morrer da vítima (Aberração)
                $this->destroyRandomEnemy($estado, $slot, $animacoes, (bool) ($efeito['dispara_ao_morrer'] ?? true));
                break;
            case 'revelar_proxima_carta_deck':
                // FIX: passa efeito para respeitar quantidade e alvo correto
                $this->revealNext($estado, $slot, $efeito, $animacoes);
                break;
            case 'confusao':
                $this->addEffect($estado, $alvo, 'confusao', $efeito, $animacoes);
                break;
            case 'crescimento_por_morte':
                $this->addFlag($estado, $unit, 'crescimento_por_morte', $animacoes);
                break;
        }
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

    public function auraAttackBonus(array $estado, int $slot, ?string $targetLinhagem = null): int
    {
        $bonus = 0;
        foreach ($estado['campo'][$slot] as $u) {
            $card = CardCatalog::get($u['card_id']);
            if (! $card) {
                continue;
            }
            foreach ($card->skills as $skill) {
                if (($skill->efeito['tipo'] ?? '') === 'aura_buff_ataque') {
                    $filtro = $skill->efeito['filtro_linhagem'] ?? null;
                    if ($filtro && $targetLinhagem && $filtro !== $targetLinhagem) {
                        continue;
                    }
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

    private function syncToState(array &$estado, array $unit): void
    {
        if (empty($unit['instancia_id'])) {
            return;
        }
        foreach ([1, 2] as $s) {
            foreach ($estado['campo'][$s] as $i => $u) {
                if ($u['instancia_id'] === $unit['instancia_id']) {
                    $estado['campo'][$s][$i] = $unit;
                    return;
                }
            }
        }
    }

    private function charge(array &$estado, ?array &$unit, array $efeito, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        $unit['pode_atacar'] = $efeito['pode_atacar_imediato'] ?? true;
        $unit['foi_invocado_neste_turno'] = false;
        $bonus = (int) ($efeito['bonus_ataque'] ?? 0);
        if ($bonus > 0) {
            // +ATK só no turno da invocação (ex.: Cão Vulcânico) — limpo em MatchEngine::endTurn
            $unit['bonus_ataque_turno'] = ($unit['bonus_ataque_turno'] ?? 0) + $bonus;
        }
        $animacoes[] = ['tipo' => 'charge', 'instancia_id' => $unit['instancia_id']];
        $this->syncToState($estado, $unit);
    }

    private function damageAllEnemyUnits(array &$estado, int $slot, int $dmg, array &$animacoes): void
    {
        $opp = $slot === 1 ? 2 : 1;
        foreach ($estado['campo'][$opp] as &$u) {
            $this->engine->damageUnit($estado, $opp, $u, $dmg, $animacoes);
        }
    }

    private function addEffect(array &$estado, ?array &$unit, string $tipo, array $efeito, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        // FIX: unidades imunes a controle ignoram silêncio, nao_pode_atacar e confusao
        $tiposControle = ['silencio', 'nao_pode_atacar', 'confusao'];
        if (in_array($tipo, $tiposControle) && $this->hasPassive($unit['card_id'], 'imune_controle')) {
            return;
        }
        $unit['efeitos'][] = [
            'tipo'    => $tipo,
            'valor'   => $efeito['valor'] ?? 1,
            'duracao' => $efeito['duracao'] ?? 1,
        ];
        if ($tipo === 'silencio') {
            $unit['silenciado'] = true;
        }
        $animacoes[] = ['tipo' => 'efeito', 'instancia_id' => $unit['instancia_id'], 'efeito' => $tipo];
        $this->syncToState($estado, $unit);
    }

    private function silence(array &$estado, ?array &$unit, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        // FIX: respeitar imunidade a controle (Cavaleiro Sem Face)
        if ($this->hasPassive($unit['card_id'], 'imune_controle')) {
            return;
        }
        $unit['silenciado'] = true;
        $unit['efeitos'][] = ['tipo' => 'silencio', 'duracao' => 1];
        $animacoes[] = ['tipo' => 'silencio', 'instancia_id' => $unit['instancia_id']];
        $this->syncToState($estado, $unit);
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

    private function addFlag(array &$estado, ?array &$unit, string $flag, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        $unit['flags'][$flag] = true;
        $animacoes[] = ['tipo' => 'flag', 'instancia_id' => $unit['instancia_id'], 'flag' => $flag];
        $this->syncToState($estado, $unit);
    }

    private function flagRessurreicao(array &$estado, int $slot): void
    {
        // FIX: só seta se o limite de 1 uso por partida ainda não foi consumido
        if (! ($estado['jogadores'][(string) $slot]['ressurreicao_usada'] ?? false)) {
            $estado['jogadores'][(string) $slot]['ressurreicao_pendente'] = true;
        }
    }

    private function reviveLastAlly(array &$estado, int $slot, array $efeito, array &$animacoes): void
    {
        $dead = $estado['ultimo_aliado_morto'][(string) $slot] ?? null;
        if (! $dead || count($estado['campo'][$slot]) >= config('game.match.field.max_units_per_player')) {
            return;
        }
        $card = CardCatalog::get($dead['card_id'] ?? 0);
        $hpMax = (int) ($dead['vida_max'] ?? $card?->vida ?? 1);
        $percentual = max(1, min(100, (int) ($efeito['hp_percentual'] ?? 50)));

        $dead['instancia_id'] = (string) Str::uuid();
        $dead['vida_max'] = $hpMax;
        $dead['vida_atual'] = max(1, (int) floor($hpMax * $percentual / 100));
        $dead['pode_atacar'] = false;
        $dead['foi_invocado_neste_turno'] = true;
        $estado['campo'][$slot][] = $dead;
        $animacoes[] = ['tipo' => 'reviver', 'instancia_id' => $dead['instancia_id']];
    }

    private function returnAllyToHand(array &$estado, int $slot, array $context, array &$animacoes): void
    {
        $targetId = $context['alvo_instancia_id'] ?? null;
        $invocadorId = $context['invocador_instancia_id'] ?? null;
        if (! $targetId || $targetId === $invocadorId) {
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
        $jogador = &$estado['jogadores'][(string) $slot];
        $maoCheia = count($jogador['mao']) >= config('game.match.field.max_hand_size');
        if ($maoCheia) {
            $jogador['cemiterio'][] = $unit['card_id'];
            $animacoes[] = ['tipo' => 'retornar_cemiterio', 'instancia_id' => $targetId];
        } else {
            $jogador['mao'][] = [
                'instancia_id' => (string) Str::uuid(),
                'card_id' => $unit['card_id'],
            ];
            $animacoes[] = ['tipo' => 'retornar_mao', 'instancia_id' => $targetId];
        }
    }

    private function destroyRandomEnemy(array &$estado, int $slot, array &$animacoes, bool $disparaAoMorrer = true): void
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
        // FIX: Aberração do Vazio: dispara_ao_morrer=false suprime gatilhos de morte
        $this->engine->killUnit($estado, $opp, $unit['instancia_id'], $animacoes, $disparaAoMorrer);
    }

    private function revealNext(array &$estado, int $slot, array $efeito, array &$animacoes): void
    {
        // FIX: sempre revela do deck INIMIGO; respeita quantidade (1 para Corvo, 3 para Oráculo)
        $opp = $slot === 1 ? 2 : 1;
        $targetSlot = ($efeito['alvo'] ?? '') === 'deck_inimigo' ? $opp : $slot;
        $quantidade = max(1, (int) ($efeito['quantidade'] ?? 1));

        $deck = $estado['jogadores'][(string) $targetSlot]['deck'];
        if (empty($deck)) {
            return;
        }

        $estado['revelacoes'][(string) $slot] = [];
        $segundos = (int) config('game.match.revelacoes_duration_seconds', 60);
        $estado['revelacoes_expira_em'][(string) $slot] = now()->addSeconds($segundos)->toIso8601String();

        $revelados = 0;
        foreach ($deck as $cardId) {
            if ($revelados >= $quantidade) {
                break;
            }
            $estado['revelacoes'][(string) $slot][] = $cardId;
            $animacoes[] = ['tipo' => 'revelar', 'player' => $slot, 'card_id' => $cardId];
            $revelados++;
        }
    }

    /**
     * Cura a própria unidade (Costureira Macabra: lifesteal).
     * Respeita o HP máximo da carta e o teto do efeito.
     */
    private function healUnitBySelf(array &$estado, int $slot, ?array &$unit, int $dano, int $maximo, array &$animacoes): void
    {
        if (! $unit || $dano <= 0) {
            return;
        }
        $heal = min($dano, $maximo);
        $card = CardCatalog::get($unit['card_id']);
        $maxHp = (int) ($unit['vida_max'] ?? $card?->vida ?? 99);
        $novaVida = min($maxHp, $unit['vida_atual'] + $heal);
        $healReal = $novaVida - $unit['vida_atual'];
        if ($healReal <= 0) {
            return;
        }
        $unit['vida_atual'] = $novaVida;
        // Sincroniza no estado
        foreach ($estado['campo'][$slot] as &$u) {
            if ($u['instancia_id'] === $unit['instancia_id']) {
                $u['vida_atual'] = $novaVida;
                break;
            }
        }
        unset($u);
        $animacoes[] = ['tipo' => 'cura', 'instancia_id' => $unit['instancia_id'], 'valor' => $healReal];
    }
}
