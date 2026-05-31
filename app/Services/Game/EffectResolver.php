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
                    $origemDano = ($unit && ! empty($unit['instancia_id']))
                        ? ['slot' => $slot, 'instancia_id' => $unit['instancia_id']]
                        : null;
                    $this->engine->damageUnit(
                        $estado,
                        $slot === 1 ? 2 : 1,
                        $alvo,
                        (int) ($efeito['valor'] ?? 0),
                        $animacoes,
                        $origemDano,
                    );
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
            case 'silencio_paralisia':
                $this->aplicarSilencioEParalisia($estado, $alvo, $efeito, $animacoes);
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

            // ── Habilidades de gatilho ao_matar ──────────────────────────────────────

            case 'ganho_ataque_ao_matar':
                // Berserker das Brasas: ao matar, ganha +N ATK permanente
                $this->buffAtaquePermanente($estado, $unit, (int) ($efeito['valor'] ?? 1), $animacoes);
                break;

            case 'cura_si_e_bonus_ataque_turno':
                // Ceifador das Almas: ao matar, cura HP próprio e ganha +N ATK no turno
                $this->curaSiEBonusAtaqueTurno($estado, $slot, $unit, (int) ($efeito['cura'] ?? 2), (int) ($efeito['bonus_ataque'] ?? 1), $animacoes);
                break;

            case 'ataque_extra_ao_matar':
                // Drakhar Sombrio, Mantis Caçador, Predador Estelar: segundo ataque após matar
                $this->liberarAtaqueExtraAoMatar($estado, $unit, $animacoes);
                break;

            // ── Habilidades de gatilho ao_morrer ─────────────────────────────────────

            case 'dano_jogador_inimigo':
                // Cinzeiro Rastejante: ao morrer, causa N dano direto ao jogador inimigo
                $opp = $slot === 1 ? 2 : 1;
                $this->danoJogadorDireto($estado, $opp, (int) ($efeito['valor'] ?? 1), $animacoes);
                break;

            case 'reviver_ate_tres_do_cemiterio_linhagem':
                // O Profanado: ao morrer, invoca até N da linhagem do cemitério com N HP
                $this->reviverDoCemiterioLinhagem($estado, $slot, $efeito, $animacoes);
                break;

            // ── Habilidades de batalha_cry / ao_invocar ───────────────────────────────

            case 'buff_linhagem_ataque_turno':
                // Comandante Vulcânico: ao invocar, aliados da linhagem ganham +N ATK no turno
                $invocadorId = $context['invocador_instancia_id'] ?? ($unit['instancia_id'] ?? null);
                $this->buffLinhagemAtaqueTurno($estado, $slot, $efeito, $invocadorId, $animacoes);
                break;

            // ── Habilidades de inicio_turno_aliado ────────────────────────────────────

            case 'bonus_ataque_turno_aleatorio':
                // Fragmento do Caos: ATK aleatório entre min e max até fim do turno
                $this->bonusAtaqueTurnoAleatorio($estado, $unit, (int) ($efeito['min'] ?? -2), (int) ($efeito['max'] ?? 4), $animacoes);
                break;

            // ── Habilidades ativas ────────────────────────────────────────────────────

            case 'sacrificio_buff_aliado_turno':
                // Cultista do Nexus: sacrifica HP próprio para dar +N ATK a aliado no turno
                $this->sacrificioBuffAliado($estado, $slot, $unit, $alvo, (int) ($efeito['custo_vida'] ?? 2), (int) ($efeito['valor'] ?? 2), $animacoes);
                break;

            // ── Habilidades de ao_atacar ──────────────────────────────────────────────

            case 'dano_adjacente':
                // Lança-Chamas Infernal, Anjo Fragmentado: 1 dano à unidade inimiga ao lado do alvo
                if ($alvo !== null) {
                    $origemDano = ($unit && ! empty($unit['instancia_id']))
                        ? ['slot' => $slot, 'instancia_id' => $unit['instancia_id']]
                        : null;
                    $this->danoAdjacenteInimigo(
                        $estado,
                        $slot === 1 ? 2 : 1,
                        $alvo,
                        (int) ($efeito['valor'] ?? 1),
                        $animacoes,
                        $origemDano,
                    );
                }
                break;

            case 'transferir_excesso_dano':
                // Artilharia Elétrica: overkill ao matar transfere para inimigo aleatório
                $excesso = (int) ($context['overkill'] ?? 0);
                if ($excesso > 0) {
                    $this->transferirExcessoDanoInimigoAleatorio($estado, $slot, $excesso, $context, $animacoes);
                }
                break;

            case 'reviver_ultimo_aliado_linhagem':
                // Engenheiro Chefe: reconstrói unidade da linhagem do cemitério com HP percentual
                $this->reviverUltimoAliadoLinhagem($estado, $slot, $efeito, $animacoes);
                break;

            case 'veu_arcano_aliado_aleatorio':
                // Escudo Autômato: Véu Arcano em aliado aleatório ao invocar
                $this->veuArcanoAliadoAleatorio($estado, $slot, $unit, $animacoes);
                break;

            case 'veneno_todas_inimigas':
                // Fungo Devorador: ao morrer, Veneno em todas as unidades inimigas
                $this->venenoTodasInimigas($estado, $slot, $efeito, $animacoes);
                break;
        }
    }

    /**
     * Valida pré-requisitos de habilidades ativas antes de gastar energia.
     */
    public function checkActiveAbilityPrerequisites(array $estado, int $slot, array $efeito): void
    {
        $tipo = $efeito['tipo'] ?? '';

        if ($tipo === 'reviver_ultimo_aliado_linhagem') {
            $linhagem = $efeito['linhagem'] ?? null;
            if (! $this->possuiUnidadeLinhagemNoCemiterio($estado, $slot, $linhagem)) {
                throw new \InvalidArgumentException('Nenhuma unidade elegível no cemitério para reconstruir');
            }
            if (count($estado['campo'][$slot]) >= config('game.match.field.max_units_per_player')) {
                throw new \InvalidArgumentException('Campo cheio — não há espaço para reconstruir');
            }
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
        return $this->getPassiveValor($cardId, $tipo) !== null;
    }

    public function possuiImunidadeRemocaoDireta(int $cardId): bool
    {
        $carta = CardCatalog::get($cardId);
        if (! $carta) {
            return false;
        }
        foreach ($carta->skills as $skill) {
            if (($skill->efeito['tipo'] ?? '') === 'imune_remocao_direta') {
                return true;
            }
        }

        return false;
    }

    public function getPassiveValor(int $cardId, string $tipo): ?int
    {
        $card = CardCatalog::get($cardId);
        if (! $card) {
            return null;
        }
        foreach ($card->skills as $skill) {
            if (($skill->efeito['tipo'] ?? '') === $tipo) {
                return (int) ($skill->efeito['valor'] ?? 0);
            }
        }

        return null;
    }

    /**
     * Decomposição (Zumbi Colossus): perde ATK permanente ao receber dano (mínimo 0).
     */
    public function aplicarPerdeAtaqueAoReceberDano(
        array &$estado,
        int $slot,
        array &$unit,
        int $danoRecebido,
        array &$animacoes,
    ): void {
        if ($danoRecebido <= 0 || ($unit['silenciado'] ?? false)) {
            return;
        }

        $valorPerda = $this->getPassiveValor($unit['card_id'], 'perde_ataque_ao_receber_dano');
        if ($valorPerda === null || $valorPerda <= 0 || ! $this->engine) {
            return;
        }

        $ataqueAtual = $this->engine->getUnitAttack($estado, $slot, $unit);
        if ($ataqueAtual <= 0) {
            return;
        }

        $perdaReal = min($valorPerda, $ataqueAtual);
        $unit['bonus_ataque'] = ($unit['bonus_ataque'] ?? 0) - $perdaReal;
        $animacoes[] = [
            'tipo' => 'debuff_ataque',
            'instancia_id' => $unit['instancia_id'],
            'valor' => $perdaReal,
        ];
    }

    /**
     * Pele Incandescente / Espinhos: ao receber dano de uma unidade, causa N de volta ao atacante.
     */
    public function aplicarReflexoDano(
        array &$estado,
        int $slotDefensor,
        array $unit,
        array $origemDano,
        array &$animacoes,
    ): void {
        if ($unit['silenciado'] ?? false) {
            return;
        }

        $valorReflexo = $this->getPassiveValor($unit['card_id'], 'reflexo_dano');
        if ($valorReflexo === null || $valorReflexo <= 0 || ! $this->engine) {
            return;
        }

        $slotAtacante = (int) ($origemDano['slot'] ?? 0);
        $instanciaAtacante = (string) ($origemDano['instancia_id'] ?? '');
        if ($slotAtacante < 1 || $slotAtacante > 2 || $instanciaAtacante === '') {
            return;
        }

        $atacante = $this->engine->findUnit($estado, $slotAtacante, $instanciaAtacante);
        if (! $atacante) {
            return;
        }

        $animacoes[] = [
            'tipo' => 'reflexo_dano',
            'instancia_id' => $unit['instancia_id'],
            'alvo_instancia_id' => $instanciaAtacante,
            'valor' => $valorReflexo,
        ];

        // Sem origemDano no reflexo — evita loop infinito de espelhamento.
        $this->engine->damageUnit($estado, $slotAtacante, $atacante, $valorReflexo, $animacoes);
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
        if ($tipo === 'silencio' && $this->possuiImunidadeRemocaoDireta($unit['card_id'])) {
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
        // Titã Magmático: imune a Silêncio e remoção direta
        if ($this->possuiImunidadeRemocaoDireta($unit['card_id'])) {
            return;
        }
        $unit['silenciado'] = true;
        $unit['efeitos'][] = ['tipo' => 'silencio', 'duracao' => 1];
        $animacoes[] = ['tipo' => 'silencio', 'instancia_id' => $unit['instancia_id']];
        $this->syncToState($estado, $unit);
    }

    /**
     * Sombra Vinculada: aplica Silêncio e Paralisia no mesmo alvo (habilidade ativa).
     */
    private function aplicarSilencioEParalisia(array &$estado, ?array &$unit, array $efeito, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }

        $duracao = (int) ($efeito['duracao'] ?? 1);
        $instanciaAlvo = $unit['instancia_id'];
        $imuneSilencio = $this->possuiImunidadeRemocaoDireta($unit['card_id']);

        if (! $imuneSilencio) {
            $this->addEffect($estado, $unit, 'silencio', ['duracao' => $duracao], $animacoes);
        }

        foreach ([1, 2] as $slotCampo) {
            foreach ($estado['campo'][$slotCampo] as &$unidadeCampo) {
                if ($unidadeCampo['instancia_id'] !== $instanciaAlvo) {
                    continue;
                }
                $this->addEffect($estado, $unidadeCampo, 'paralisia', ['duracao' => $duracao], $animacoes);

                return;
            }
        }
        unset($unidadeCampo);
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
        if ($this->possuiImunidadeRemocaoDireta($unit['card_id'])) {
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
            if ($temVeu || $temProvocar || $this->possuiImunidadeRemocaoDireta($unidade['card_id'])) {
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

    // ── Habilidades de gatilho ao_matar ──────────────────────────────────────────

    /**
     * Berserker das Brasas: ao matar uma unidade, ganha +N ATK permanente.
     */
    private function buffAtaquePermanente(array &$estado, ?array &$unit, int $valor, array &$animacoes): void
    {
        if (! $unit || $valor === 0) {
            return;
        }
        $unit['bonus_ataque'] = ($unit['bonus_ataque'] ?? 0) + $valor;
        $animacoes[] = ['tipo' => 'buff_ataque', 'instancia_id' => $unit['instancia_id'], 'valor' => $valor];
        $this->syncToState($estado, $unit);
    }

    /**
     * Ceifador das Almas: ao matar, cura HP próprio e ganha +N ATK no turno atual.
     */
    private function curaSiEBonusAtaqueTurno(array &$estado, int $slot, ?array &$unit, int $cura, int $bonusAtaque, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        if ($cura > 0) {
            $maxHp   = (int) ($unit['vida_max'] ?? CardCatalog::get($unit['card_id'])?->vida ?? 1);
            $novaVida = min($maxHp, $unit['vida_atual'] + $cura);
            $curaReal = $novaVida - $unit['vida_atual'];
            if ($curaReal > 0) {
                $unit['vida_atual'] = $novaVida;
                $animacoes[] = ['tipo' => 'cura', 'instancia_id' => $unit['instancia_id'], 'valor' => $curaReal];
            }
        }
        if ($bonusAtaque > 0) {
            $unit['bonus_ataque_turno'] = ($unit['bonus_ataque_turno'] ?? 0) + $bonusAtaque;
            $animacoes[] = ['tipo' => 'buff_ataque', 'instancia_id' => $unit['instancia_id'], 'valor' => $bonusAtaque];
        }
        $this->syncToState($estado, $unit);
    }

    /**
     * Drakhar Sombrio / Mantis Caçador / Predador Estelar:
     * após matar uma unidade, libera um segundo ataque neste turno (limitado a 1 por turno).
     */
    private function liberarAtaqueExtraAoMatar(array &$estado, ?array &$unit, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        if ($unit['flags']['ataque_extra_ao_matar_usado_turno'] ?? false) {
            return;
        }
        $unit['pode_atacar']                              = true;
        $unit['flags']['ataque_extra_ao_matar_usado_turno'] = true;
        $animacoes[] = ['tipo' => 'ataque_extra', 'instancia_id' => $unit['instancia_id']];
        $this->syncToState($estado, $unit);
    }

    // ── Habilidades de gatilho ao_morrer ─────────────────────────────────────────

    /**
     * Cinzeiro Rastejante: ao morrer, causa N dano direto ao jogador inimigo.
     */
    private function danoJogadorDireto(array &$estado, int $slotInimigo, int $valor, array &$animacoes): void
    {
        $estado['jogadores'][(string) $slotInimigo]['vida'] = max(0, ($estado['jogadores'][(string) $slotInimigo]['vida'] ?? 0) - $valor);
        $animacoes[] = ['tipo' => 'dano_jogador', 'player' => $slotInimigo, 'valor' => $valor];
    }

    /**
     * O Profanado: ao morrer, invoca até N unidades da linhagem do cemitério aliado com vida fixada.
     * Só invoca quantas houver disponíveis (não exige o máximo).
     * Unidades invocadas não podem atacar no turno de entrada.
     */
    private function reviverDoCemiterioLinhagem(array &$estado, int $slot, array $efeito, array &$animacoes): void
    {
        $linhagem   = $efeito['linhagem'] ?? null;
        $quantidade = (int) ($efeito['quantidade'] ?? 3);
        $vidaFixa   = (int) ($efeito['vida'] ?? 1);
        $maxField   = config('game.match.field.max_units_per_player');

        $cemiterio = $estado['jogadores'][(string) $slot]['cemiterio'] ?? [];
        if (empty($cemiterio)) {
            return;
        }

        $candidatos = [];
        foreach ($cemiterio as $cardId) {
            $card = CardCatalog::get((int) $cardId);
            if (! $card || $card->tipo === 'spell') {
                continue;
            }
            if ($linhagem && $card->linhagem !== $linhagem) {
                continue;
            }
            $candidatos[] = (int) $cardId;
        }

        if (empty($candidatos)) {
            return;
        }

        shuffle($candidatos);
        $invocados = 0;

        foreach ($candidatos as $cardId) {
            if ($invocados >= $quantidade) {
                break;
            }
            if (count($estado['campo'][$slot]) >= $maxField) {
                break;
            }
            $card = CardCatalog::get($cardId);
            if (! $card) {
                continue;
            }

            $novaUnidade = [
                'instancia_id'           => (string) Str::uuid(),
                'card_id'                => $cardId,
                'vida_atual'             => $vidaFixa,
                'vida_max'               => $card->vida,
                'bonus_ataque'           => 0,
                'bonus_ataque_turno'     => 0,
                'pode_atacar'            => false,
                'foi_invocado_neste_turno' => true,
                'silenciado'             => false,
                'efeitos'                => [],
                'flags'                  => [],
            ];
            $this->initializeUnitFlags($novaUnidade, $card);
            $estado['campo'][$slot][] = $novaUnidade;
            $animacoes[] = ['tipo' => 'invocar', 'instancia_id' => $novaUnidade['instancia_id'], 'card_id' => $cardId];
            $invocados++;
        }
    }

    // ── Habilidades de batalha_cry / ao_invocar ───────────────────────────────────

    /**
     * Comandante Vulcânico: ao invocar, todos os aliados da linhagem (exceto o próprio)
     * ganham +N ATK neste turno.
     */
    private function buffLinhagemAtaqueTurno(array &$estado, int $slot, array $efeito, ?string $invocadorId, array &$animacoes): void
    {
        $linhagem = $efeito['linhagem'] ?? null;
        $valor    = (int) ($efeito['valor'] ?? 1);

        foreach ($estado['campo'][$slot] as &$aliado) {
            if ($invocadorId && $aliado['instancia_id'] === $invocadorId) {
                continue;
            }
            $card = CardCatalog::get($aliado['card_id']);
            if ($linhagem && $card?->linhagem !== $linhagem) {
                continue;
            }
            $aliado['bonus_ataque_turno'] = ($aliado['bonus_ataque_turno'] ?? 0) + $valor;
            $animacoes[] = ['tipo' => 'buff_ataque', 'instancia_id' => $aliado['instancia_id'], 'valor' => $valor];
        }
        unset($aliado);
    }

    // ── Habilidades de inicio_turno_aliado ────────────────────────────────────────

    /**
     * Fragmento do Caos: no início do turno aliado, recebe ATK aleatório entre min e max
     * (pode ser negativo). O bonus_ataque_turno é zerado no fim do turno pelo MatchEngine.
     */
    private function bonusAtaqueTurnoAleatorio(array &$estado, ?array &$unit, int $min, int $max, array &$animacoes): void
    {
        if (! $unit) {
            return;
        }
        $valor                       = rand($min, $max);
        $unit['bonus_ataque_turno']  = ($unit['bonus_ataque_turno'] ?? 0) + $valor;
        $animacoes[] = ['tipo' => 'buff_ataque', 'instancia_id' => $unit['instancia_id'], 'valor' => $valor];
        $this->syncToState($estado, $unit);
    }

    // ── Habilidades ativas ────────────────────────────────────────────────────────

    /**
     * Cultista do Nexus: deduz custo_vida do próprio HP (mínimo 1) e aplica
     * +N ATK neste turno no aliado alvo.
     */
    private function sacrificioBuffAliado(array &$estado, int $slot, ?array &$unit, ?array &$alvo, int $custoVida, int $valorBuff, array &$animacoes): void
    {
        if (! $unit || ! $alvo) {
            return;
        }
        // Deduz HP do Cultista (mínimo 1 para não matar o próprio)
        $novaVida          = max(1, $unit['vida_atual'] - $custoVida);
        $danoReal          = $unit['vida_atual'] - $novaVida;
        $unit['vida_atual'] = $novaVida;
        if ($danoReal > 0) {
            $animacoes[] = ['tipo' => 'dano', 'instancia_id' => $unit['instancia_id'], 'valor' => $danoReal];
        }
        $this->syncToState($estado, $unit);
        // Aplica buff ATK no turno ao aliado alvo
        $this->buffAttackTurn($estado, $alvo, $valorBuff, $animacoes);
    }

    // ── Habilidades de ao_atacar ──────────────────────────────────────────────────

    /**
     * Lança-Chamas Infernal / Anjo Fragmentado:
     * ao atacar, causa N dano à unidade inimiga imediatamente ao lado do alvo.
     * Se houver duas adjacentes (esquerda e direita), escolhe aleatoriamente.
     */
    private function danoAdjacenteInimigo(
        array &$estado,
        int $slotInimigo,
        array $alvoAtacado,
        int $valor,
        array &$animacoes,
        ?array $origemDano = null,
    ): void {
        $campo    = $estado['campo'][$slotInimigo];
        $posicao  = null;

        foreach ($campo as $idx => $u) {
            if ($u['instancia_id'] === $alvoAtacado['instancia_id']) {
                $posicao = $idx;
                break;
            }
        }

        if ($posicao === null) {
            return;
        }

        $adjacentes = [];
        if (isset($campo[$posicao - 1])) {
            $adjacentes[] = $posicao - 1;
        }
        if (isset($campo[$posicao + 1])) {
            $adjacentes[] = $posicao + 1;
        }

        if (empty($adjacentes)) {
            return;
        }

        // Escolhe uma adjacente aleatoriamente se houver duas
        $idxAdj = $adjacentes[array_rand($adjacentes)];
        $adjacente = &$estado['campo'][$slotInimigo][$idxAdj];
        $this->engine->damageUnit($estado, $slotInimigo, $adjacente, $valor, $animacoes, $origemDano);
        unset($adjacente);
    }

    /**
     * Artilharia Elétrica: transfere dano excedente (overkill) para unidade inimiga aleatória.
     */
    private function transferirExcessoDanoInimigoAleatorio(
        array &$estado,
        int $slot,
        int $excesso,
        array $context,
        array &$animacoes,
    ): void {
        if ($excesso <= 0 || ! $this->engine) {
            return;
        }

        $slotInimigo = $slot === 1 ? 2 : 1;
        $instanciaMorta = $context['alvo']['instancia_id'] ?? null;
        $candidatos = array_values(array_filter(
            $estado['campo'][$slotInimigo] ?? [],
            fn ($unidade) => $unidade['instancia_id'] !== $instanciaMorta,
        ));

        if (empty($candidatos)) {
            return;
        }

        $indiceAleatorio = array_rand($candidatos);
        $alvoSecundario = $candidatos[$indiceAleatorio];
        $origemDano = ($context['sourceUnit'] ?? null);

        foreach ($estado['campo'][$slotInimigo] as &$unidadeCampo) {
            if ($unidadeCampo['instancia_id'] !== $alvoSecundario['instancia_id']) {
                continue;
            }
            $this->engine->damageUnit($estado, $slotInimigo, $unidadeCampo, $excesso, $animacoes, $origemDano);
            break;
        }
        unset($unidadeCampo);

        $animacoes[] = [
            'tipo' => 'overkill',
            'instancia_id' => $alvoSecundario['instancia_id'],
            'valor' => $excesso,
        ];
    }

    /**
     * Engenheiro Chefe: revive a unidade mais recente da linhagem no cemitério com HP percentual.
     */
    private function reviverUltimoAliadoLinhagem(array &$estado, int $slot, array $efeito, array &$animacoes): void
    {
        $linhagem     = $efeito['linhagem'] ?? null;
        $percentual   = max(1, min(100, (int) ($efeito['hp_percentual'] ?? 50)));
        $maxField     = config('game.match.field.max_units_per_player');

        if (count($estado['campo'][$slot]) >= $maxField) {
            return;
        }

        $cardId = $this->ultimaUnidadeLinhagemNoCemiterio($estado, $slot, $linhagem);
        if ($cardId === null) {
            return;
        }

        $card = CardCatalog::get($cardId);
        if (! $card || $card->tipo === 'spell') {
            return;
        }

        $hpMaximo = $card->vida;
        $novaUnidade = [
            'instancia_id'             => (string) Str::uuid(),
            'card_id'                  => $cardId,
            'vida_atual'               => max(1, (int) floor($hpMaximo * $percentual / 100)),
            'vida_max'                 => $hpMaximo,
            'bonus_ataque'             => 0,
            'bonus_ataque_turno'       => 0,
            'pode_atacar'              => false,
            'foi_invocado_neste_turno' => true,
            'silenciado'               => false,
            'efeitos'                  => [],
            'flags'                  => [],
        ];
        $this->initializeUnitFlags($novaUnidade, $card);
        $estado['campo'][$slot][] = $novaUnidade;
        $animacoes[] = ['tipo' => 'reviver', 'instancia_id' => $novaUnidade['instancia_id'], 'card_id' => $cardId];
    }

    /**
     * Escudo Autômato: aplica Véu Arcano em aliado aleatório (exclui o invocador se houver outro).
     */
    private function veuArcanoAliadoAleatorio(array &$estado, int $slot, ?array $unit, array &$animacoes): void
    {
        $invocadorId = $unit['instancia_id'] ?? null;
        $aliados = $estado['campo'][$slot] ?? [];

        if (empty($aliados)) {
            return;
        }

        $candidatos = array_values(array_filter(
            $aliados,
            fn ($aliado) => $aliado['instancia_id'] !== $invocadorId,
        ));

        if (empty($candidatos)) {
            $candidatos = $aliados;
        }

        $indiceAleatorio = array_rand($candidatos);
        $alvo = $candidatos[$indiceAleatorio];

        foreach ($estado['campo'][$slot] as &$unidadeCampo) {
            if ($unidadeCampo['instancia_id'] !== $alvo['instancia_id']) {
                continue;
            }
            $this->addFlag($estado, $unidadeCampo, 'escudo', $animacoes);
            break;
        }
        unset($unidadeCampo);
    }

    /**
     * Fungo Devorador: aplica Veneno em todas as unidades inimigas em campo.
     */
    private function venenoTodasInimigas(array &$estado, int $slot, array $efeito, array &$animacoes): void
    {
        $slotInimigo = $slot === 1 ? 2 : 1;
        $instancias  = array_column($estado['campo'][$slotInimigo] ?? [], 'instancia_id');

        foreach ($instancias as $instanciaId) {
            $unidade = $this->engine->findUnit($estado, $slotInimigo, $instanciaId);
            if ($unidade) {
                $this->addEffect($estado, $unidade, 'veneno', $efeito, $animacoes);
            }
        }
    }

    private function possuiUnidadeLinhagemNoCemiterio(array $estado, int $slot, ?string $linhagem): bool
    {
        return $this->ultimaUnidadeLinhagemNoCemiterio($estado, $slot, $linhagem) !== null;
    }

    private function ultimaUnidadeLinhagemNoCemiterio(array $estado, int $slot, ?string $linhagem): ?int
    {
        $cemiterio = array_reverse($estado['jogadores'][(string) $slot]['cemiterio'] ?? []);

        foreach ($cemiterio as $cardId) {
            $card = CardCatalog::get((int) $cardId);
            if (! $card || $card->tipo === 'spell') {
                continue;
            }
            if ($linhagem && $card->linhagem !== $linhagem) {
                continue;
            }

            return (int) $cardId;
        }

        return null;
    }
}
