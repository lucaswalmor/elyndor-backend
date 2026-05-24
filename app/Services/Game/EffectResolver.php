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
            if (($skill->efeito['tipo'] ?? '') === 'provocar') {
                $unit['flags']['taunt_self'] = true;
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
            case 'dano_alvo':
                if ($alvo !== null) {
                    $this->engine->damageUnit($estado, $slot === 1 ? 2 : 1, $alvo, (int) ($efeito['valor'] ?? 0), $animacoes);
                }
                break;
            case 'debuff_ataque':
                // FIX: aplica no ALVO (defensor), não no atacante
                $this->addEffect($estado, $alvo, 'debuff_ataque', $efeito, $animacoes);
                break;
            case 'buff_ataque_turno':
                $this->buffAttackTurn($estado, $alvo, (int) ($efeito['valor'] ?? 1), $animacoes);
                break;
            case 'cura_alvo':
                $this->healTargetUnit($estado, $alvo, (int) ($efeito['valor'] ?? 1), $animacoes);
                break;
            case 'cura_todos_aliados':
                $this->healAllAllies($estado, $slot, (int) ($efeito['valor'] ?? 1), $animacoes);
                break;
            case 'veneno':
                if ($alvo !== null) {
                    $this->addEffect($estado, $alvo, 'veneno', $efeito, $animacoes);
                } elseif (array_key_exists('dano', $context)) {
                    $slotOponente = $slot === 1 ? 2 : 1;
                    $this->addPlayerEffect($estado, $slotOponente, 'veneno', $efeito, $animacoes);
                }
                break;
            case 'silencio':
                $this->silence($estado, $alvo, $animacoes);
                break;
            case 'paralisia':
                $this->addEffect($estado, $alvo, 'paralisia', $efeito, $animacoes);
                break;
            case 'veu_arcano':
                if ($alvo !== null) {
                    $this->addFlag($estado, $alvo, 'escudo', $animacoes);
                } else {
                    $this->addFlag($estado, $unit, 'escudo', $animacoes);
                }
                break;
            case 'liberar_ataque_extra':
                $this->releaseExtraAttack($estado, $alvo, $animacoes);
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
            case 'provocar':
                $this->addFlag($estado, $unit, 'taunt_self', $animacoes);
                break;

            // ── Novos feitiços v2.1 ───────────────────────────────────────────────────

            case 'buff_hp_veu_arcano':
                // Escudo de Emergência: +N HP permanente + Véu Arcano na aliada alvo
                $this->buffHpPermanente($estado, $alvo, (int) ($efeito['valor'] ?? 3), $animacoes);
                if ($alvo !== null) {
                    $alvoCopia = $alvo;
                    $this->addFlag($estado, $alvoCopia, 'escudo', $animacoes);
                }
                break;

            case 'buff_ataque_hp_permanente':
                // Canalização de Poder / Ascensão: +N ATK e +N HP permanentes na aliada alvo
                $this->buffAtaqueHpPermanente($estado, $alvo, (int) ($efeito['ataque'] ?? 2), (int) ($efeito['hp'] ?? 2), $animacoes);
                break;

            case 'confusao_2_aleatorios_inimigos':
                // Névoa da Confusão: confusão em N inimigas aleatórias
                $this->confusaoAleatoriosInimigos($estado, $slot, (int) ($efeito['quantidade'] ?? 2), $animacoes);
                break;

            case 'paralisia_2_aleatorios_inimigos':
                // Onda de Choque: paralisia em N inimigas aleatórias
                $this->paralisia2AleatoriosInimigos($estado, $slot, (int) ($efeito['quantidade'] ?? 2), (int) ($efeito['duracao'] ?? 1), $animacoes);
                break;

            case 'cura_max_remover_debuffs':
                // Restauração Completa: cura HP máximo + remove todos os debuffs da aliada alvo
                $this->curaMaxRemoverDebuffs($estado, $alvo, $animacoes);
                break;

            case 'silencio_todos_inimigos':
                // Silêncio em Massa: silencia todas as unidades inimigas em campo
                $this->silencioTodosInimigos($estado, $slot, $animacoes);
                break;

            case 'inversao_ataque_hp':
                // Inversão de Força: troca ATK e HP da unidade inimiga alvo
                $this->inversaoAtaqueHp($estado, $alvo, $animacoes);
                break;

            case 'sacrificio_buff_proxima_invocacao':
                // Sacrifício Tático: destrói aliada alvo, próxima invocação ganha +N/+N
                $this->sacrificioBuff($estado, $slot, $alvo, (int) ($efeito['ataque'] ?? 2), (int) ($efeito['hp'] ?? 2), $animacoes);
                break;

            case 'tempestade_arcana':
                // Tempestade Arcana: N dano a todas inimigas + paralisia nas sobreviventes
                $this->tempestadeArcana($estado, $slot, (int) ($efeito['dano'] ?? 3), (int) ($efeito['duracao_paralisia'] ?? 1), $animacoes);
                break;

            case 'pacto_de_sangue':
                // Pacto de Sangue: -N HP ao jogador + +N/+N + Véu Arcano em todos os aliados
                $this->pactoDeSangue($estado, $slot, (int) ($efeito['dano_proprio'] ?? 5), (int) ($efeito['ataque'] ?? 2), (int) ($efeito['hp'] ?? 2), $animacoes);
                break;

            case 'destruir_maior_hp_inimigo':
                // Ruptura Dimensional: destrói a unidade inimiga com maior HP (sem véu, sem provocar)
                $this->destruirMaiorHpInimigo($estado, $slot, $animacoes);
                break;

            case 'colapso_do_vazio':
                // Colapso do Vazio: destrói todas as unidades em campo, recupera HP por aliada destruída
                $this->colapsoDovazio($estado, $slot, (int) ($efeito['cura_por_aliada'] ?? 3), $animacoes);
                break;
        }
    }

    /**
     * Valida pré-requisitos de feitiços antes de gastar energia.
     * Lança InvalidArgumentException se a condição não for satisfeita.
     */
    public function checkSpellPrerequisites(array $estado, int $slot, array $efeito): void
    {
        $tipo = $efeito['tipo'] ?? '';

        if ($tipo === 'colapso_do_vazio' && count($estado['campo'][$slot]) < 2) {
            throw new \InvalidArgumentException('Colapso do Vazio requer ao menos 2 unidades aliadas em campo');
        }

        if ($tipo === 'sacrificio_buff_proxima_invocacao') {
            if (empty($estado['campo'][$slot])) {
                throw new \InvalidArgumentException('Sacrifício Tático requer ao menos 1 unidade aliada em campo');
            }
        }

        if ($tipo === 'pacto_de_sangue') {
            $vidaAtual = $estado['jogadores'][(string) $slot]['vida'] ?? 0;
            if ($vidaAtual <= 5) {
                throw new \InvalidArgumentException('HP insuficiente para usar Pacto de Sangue');
            }
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

    private function addPlayerEffect(array &$estado, int $slot, string $tipo, array $efeito, array &$animacoes): void
    {
        $jogador = &$estado['jogadores'][(string) $slot];
        if (! isset($jogador['efeitos']) || ! is_array($jogador['efeitos'])) {
            $jogador['efeitos'] = [];
        }
        $jogador['efeitos'][] = [
            'tipo' => $tipo,
            'valor' => $efeito['valor'] ?? 1,
            'duracao' => $efeito['duracao'] ?? 1,
        ];
        $animacoes[] = ['tipo' => 'efeito_jogador', 'player' => $slot, 'efeito' => $tipo];
    }

    private function addEffect(array &$estado, ?array &$unit, string $tipo, array $efeito, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        // FIX: unidades imunes a controle ignoram silêncio, nao_pode_atacar e confusao
        $tiposControle = ['silencio', 'nao_pode_atacar', 'confusao', 'paralisia'];
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
        if ($tipo === 'paralisia') {
            $unit['pode_atacar'] = false;
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

    private function healTargetUnit(array &$estado, ?array &$unit, int $amount, array &$animacoes): void
    {
        if (! $unit || $amount <= 0) {
            return;
        }
        $maxHp = (int) ($unit['vida_max'] ?? CardCatalog::get($unit['card_id'])?->vida ?? 1);
        $old = (int) ($unit['vida_atual'] ?? 0);
        $unit['vida_atual'] = min($maxHp, $old + $amount);
        $real = $unit['vida_atual'] - $old;
        if ($real > 0) {
            $animacoes[] = ['tipo' => 'cura', 'instancia_id' => $unit['instancia_id'], 'valor' => $real];
        }
        $this->syncToState($estado, $unit);
    }

    private function healAllAllies(array &$estado, int $slot, int $amount, array &$animacoes): void
    {
        $ids = array_column($estado['campo'][$slot] ?? [], 'instancia_id');
        foreach ($ids as $id) {
            $unit = $this->engine->findUnit($estado, $slot, $id);
            if ($unit) {
                $this->healTargetUnit($estado, $unit, $amount, $animacoes);
            }
        }
    }

    private function buffAttackTurn(array &$estado, ?array &$unit, int $amount, array &$animacoes): void
    {
        if (! $unit || $amount === 0) {
            return;
        }
        $unit['bonus_ataque_turno'] = (int) ($unit['bonus_ataque_turno'] ?? 0) + $amount;
        $animacoes[] = ['tipo' => 'buff_ataque', 'instancia_id' => $unit['instancia_id'], 'valor' => $amount];
        $this->syncToState($estado, $unit);
    }

    private function releaseExtraAttack(array &$estado, ?array &$unit, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        if ($unit['flags']['impeto_momentaneo_usado_turno'] ?? false) {
            return;
        }
        $unit['pode_atacar'] = true;
        $unit['foi_invocado_neste_turno'] = false;
        $unit['flags']['impeto_momentaneo_usado_turno'] = true;
        $animacoes[] = ['tipo' => 'ataque_extra', 'instancia_id' => $unit['instancia_id']];
        $this->syncToState($estado, $unit);
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
        foreach ($estado['campo'][$slot] as &$unidadeLoop) {
            if ($unidadeLoop['instancia_id'] === $unit['instancia_id']) {
                $unidadeLoop['vida_atual'] = $novaVida;
                break;
            }
        }
        unset($unidadeLoop);
        $animacoes[] = ['tipo' => 'cura', 'instancia_id' => $unit['instancia_id'], 'valor' => $healReal];
    }

    // ── Helpers para os novos feitiços v2.1 ──────────────────────────────────────

    /**
     * Aumenta permanentemente o HP máximo e atual de uma unidade.
     */
    private function buffHpPermanente(array &$estado, ?array &$unit, int $quantidade, array &$animacoes): void
    {
        if (! $unit || $quantidade <= 0) {
            return;
        }
        $unit['vida_max']   = ($unit['vida_max'] ?? 1) + $quantidade;
        $unit['vida_atual'] = ($unit['vida_atual'] ?? 1) + $quantidade;
        $animacoes[] = ['tipo' => 'buff_hp', 'instancia_id' => $unit['instancia_id'], 'valor' => $quantidade];
        $this->syncToState($estado, $unit);
    }

    /**
     * Aumenta permanentemente o ATK (bonus_ataque) e o HP de uma unidade.
     */
    private function buffAtaqueHpPermanente(array &$estado, ?array &$unit, int $ataque, int $hp, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        if ($ataque > 0) {
            $unit['bonus_ataque'] = ($unit['bonus_ataque'] ?? 0) + $ataque;
            $animacoes[] = ['tipo' => 'buff_ataque', 'instancia_id' => $unit['instancia_id'], 'valor' => $ataque];
        }
        if ($hp > 0) {
            $unit['vida_max']   = ($unit['vida_max'] ?? 1) + $hp;
            $unit['vida_atual'] = ($unit['vida_atual'] ?? 1) + $hp;
            $animacoes[] = ['tipo' => 'buff_hp', 'instancia_id' => $unit['instancia_id'], 'valor' => $hp];
        }
        $this->syncToState($estado, $unit);
    }

    /**
     * Névoa da Confusão: aplica Confusão em N unidades inimigas aleatórias.
     */
    private function confusaoAleatoriosInimigos(array &$estado, int $slot, int $quantidade, array &$animacoes): void
    {
        $oponente   = $slot === 1 ? 2 : 1;
        $instancias = array_column($estado['campo'][$oponente] ?? [], 'instancia_id');
        shuffle($instancias);
        $alvos = array_slice($instancias, 0, $quantidade);

        foreach ($alvos as $instanciaId) {
            $unidade = $this->engine->findUnit($estado, $oponente, $instanciaId);
            if ($unidade && ! $this->hasPassive($unidade['card_id'], 'imune_controle')) {
                $this->addEffect($estado, $unidade, 'confusao', ['duracao' => 1], $animacoes);
            }
        }
    }

    /**
     * Onda de Choque: aplica Paralisia em N unidades inimigas aleatórias.
     */
    private function paralisia2AleatoriosInimigos(array &$estado, int $slot, int $quantidade, int $duracao, array &$animacoes): void
    {
        $oponente   = $slot === 1 ? 2 : 1;
        $instancias = array_column($estado['campo'][$oponente] ?? [], 'instancia_id');
        shuffle($instancias);
        $alvos = array_slice($instancias, 0, $quantidade);

        foreach ($alvos as $instanciaId) {
            $unidade = $this->engine->findUnit($estado, $oponente, $instanciaId);
            if ($unidade && ! $this->hasPassive($unidade['card_id'], 'imune_controle')) {
                $this->addEffect($estado, $unidade, 'paralisia', ['duracao' => $duracao], $animacoes);
            }
        }
    }

    /**
     * Restauração Completa: cura unidade ao HP máximo e remove todos os debuffs.
     */
    private function curaMaxRemoverDebuffs(array &$estado, ?array &$unit, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        $hpMax              = (int) ($unit['vida_max'] ?? CardCatalog::get($unit['card_id'])?->vida ?? 1);
        $curaReal           = $hpMax - (int) ($unit['vida_atual'] ?? 0);
        $unit['vida_atual'] = $hpMax;
        $unit['silenciado'] = false;
        $unit['efeitos']    = [];
        if ($curaReal > 0) {
            $animacoes[] = ['tipo' => 'cura', 'instancia_id' => $unit['instancia_id'], 'valor' => $curaReal];
        }
        $animacoes[] = ['tipo' => 'efeito', 'instancia_id' => $unit['instancia_id'], 'efeito' => 'remover_debuffs'];
        $this->syncToState($estado, $unit);
    }

    /**
     * Silêncio em Massa: aplica Silêncio em todas as unidades inimigas.
     */
    private function silencioTodosInimigos(array &$estado, int $slot, array &$animacoes): void
    {
        $oponente   = $slot === 1 ? 2 : 1;
        $instancias = array_column($estado['campo'][$oponente] ?? [], 'instancia_id');

        foreach ($instancias as $instanciaId) {
            $unidade = $this->engine->findUnit($estado, $oponente, $instanciaId);
            if ($unidade) {
                $this->silence($estado, $unidade, $animacoes);
            }
        }
    }

    /**
     * Inversão de Força: troca os valores de ATK e HP da unidade inimiga alvo.
     * O ATK efetivo vira o novo HP máximo/atual, e o HP atual vira o novo ATK permanente.
     */
    private function inversaoAtaqueHp(array &$estado, ?array &$unit, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        $carta      = CardCatalog::get($unit['card_id']);
        $atkBase    = (int) ($carta?->ataque ?? 0);
        $bonusAtk   = (int) ($unit['bonus_ataque'] ?? 0) + (int) ($unit['bonus_ataque_turno'] ?? 0);
        $atkEfetivo = $atkBase + $bonusAtk;
        $hpAtual    = (int) ($unit['vida_atual'] ?? 1);

        // Novo ATK = HP atual; novo HP = ATK efetivo
        $unit['bonus_ataque']       = $hpAtual - $atkBase;
        $unit['bonus_ataque_turno'] = 0;
        $unit['vida_max']           = max(1, $atkEfetivo);
        $unit['vida_atual']         = max(1, $atkEfetivo);

        $animacoes[] = ['tipo' => 'efeito', 'instancia_id' => $unit['instancia_id'], 'efeito' => 'inversao_ataque_hp'];
        $this->syncToState($estado, $unit);
    }

    /**
     * Sacrifício Tático: destrói a unidade aliada alvo e registra buff para a próxima invocação.
     */
    private function sacrificioBuff(array &$estado, int $slot, ?array &$alvo, int $ataque, int $hp, array &$animacoes): void
    {
        if (! $alvo) {
            return;
        }
        $instanciaId = $alvo['instancia_id'];
        $this->engine->killUnit($estado, $slot, $instanciaId, $animacoes);
        $estado['jogadores'][(string) $slot]['proximo_invocado_buff'] = [
            'ataque' => $ataque,
            'hp'     => $hp,
        ];
    }

    /**
     * Tempestade Arcana: causa N dano a todas as unidades inimigas e aplica
     * Paralisia nas que sobreviverem.
     */
    private function tempestadeArcana(array &$estado, int $slot, int $dano, int $duracaoParalisia, array &$animacoes): void
    {
        $oponente   = $slot === 1 ? 2 : 1;
        $instancias = array_column($estado['campo'][$oponente] ?? [], 'instancia_id');

        // Aplica dano iterando por referência direta no campo (como damageAllEnemyUnits)
        foreach ($instancias as $instanciaId) {
            foreach ($estado['campo'][$oponente] as &$unidadeCampo) {
                if ($unidadeCampo['instancia_id'] === $instanciaId) {
                    $this->engine->damageUnit($estado, $oponente, $unidadeCampo, $dano, $animacoes);
                    break;
                }
            }
            unset($unidadeCampo);
        }

        // Paralisia nas sobreviventes
        $sobreviventes = array_column($estado['campo'][$oponente] ?? [], 'instancia_id');
        foreach ($sobreviventes as $instanciaId) {
            $unidade = $this->engine->findUnit($estado, $oponente, $instanciaId);
            if ($unidade && ! $this->hasPassive($unidade['card_id'], 'imune_controle')) {
                $this->addEffect($estado, $unidade, 'paralisia', ['duracao' => $duracaoParalisia], $animacoes);
            }
        }
    }

    /**
     * Pacto de Sangue: o jogador perde N HP; todos os aliados ganham +N/+N permanentes e Véu Arcano.
     */
    private function pactoDeSangue(array &$estado, int $slot, int $danoPropio, int $ataque, int $hp, array &$animacoes): void
    {
        // Dano ao jogador
        $jogador          = &$estado['jogadores'][(string) $slot];
        $jogador['vida']  = max(0, ($jogador['vida'] ?? 0) - $danoPropio);
        $animacoes[]      = ['tipo' => 'dano_jogador', 'player' => $slot, 'valor' => $danoPropio];

        // Buff em todos os aliados em campo
        $instancias = array_column($estado['campo'][$slot] ?? [], 'instancia_id');
        foreach ($instancias as $instanciaId) {
            $unidade = $this->engine->findUnit($estado, $slot, $instanciaId);
            if (! $unidade) {
                continue;
            }
            $this->buffAtaqueHpPermanente($estado, $unidade, $ataque, $hp, $animacoes);
            $unidadeAtualizada = $this->engine->findUnit($estado, $slot, $instanciaId);
            if ($unidadeAtualizada) {
                $this->addFlag($estado, $unidadeAtualizada, 'escudo', $animacoes);
            }
        }
    }

    /**
     * Ruptura Dimensional: destrói a unidade inimiga com maior HP que não possua
     * Véu Arcano nem Provocar.
     */
    private function destruirMaiorHpInimigo(array &$estado, int $slot, array &$animacoes): void
    {
        $oponente  = $slot === 1 ? 2 : 1;
        $candidata = null;
        $maiorHp   = -1;

        foreach ($estado['campo'][$oponente] as $unidade) {
            $temVeu      = $unidade['flags']['escudo'] ?? false;
            $temProvocar = ($unidade['flags']['taunt_self'] ?? false) && ! ($unidade['silenciado'] ?? false);
            if ($temVeu || $temProvocar) {
                continue;
            }
            $hpAtual = (int) ($unidade['vida_atual'] ?? 0);
            if ($hpAtual > $maiorHp) {
                $maiorHp   = $hpAtual;
                $candidata = $unidade;
            }
        }

        if ($candidata) {
            $this->engine->killUnit($estado, $oponente, $candidata['instancia_id'], $animacoes);
        }
    }

    /**
     * Colapso do Vazio: destrói todas as unidades em campo (aliadas e inimigas).
     * Recupera N HP por cada unidade aliada destruída.
     */
    private function colapsoDovazio(array &$estado, int $slot, int $curaPorAliada, array &$animacoes): void
    {
        // Conta aliados antes de destruir
        $totalAliados = count($estado['campo'][$slot]);

        // Destrói todos (aliados e inimigos)
        foreach ([1, 2] as $ladoCampo) {
            $instancias = array_column($estado['campo'][$ladoCampo], 'instancia_id');
            foreach ($instancias as $instanciaId) {
                $this->engine->killUnit($estado, $ladoCampo, $instanciaId, $animacoes);
            }
        }

        // Recupera HP por aliado destruído
        if ($totalAliados > 0 && $curaPorAliada > 0) {
            $totalCura = $totalAliados * $curaPorAliada;
            $this->healPlayer($estado, $slot, $totalCura, $animacoes);
        }
    }
}
