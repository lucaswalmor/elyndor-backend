<?php

namespace App\Services\Game;

use App\Enums\MatchStatus;
use App\Events\ActionProcessed;
use App\Events\MatchFinished;
use App\Events\TurnChanged;
use App\Models\GameMatch;
use App\Models\MatchLog;
use App\Models\User;
use App\Services\Bot\SubstituteBotTurnDispatcher;
use App\Services\Logging\GameBalanceMatchTelemetry;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MatchEngine
{
    private EffectResolver $effects;

    public function __construct()
    {
        $this->effects = new EffectResolver;
        $this->effects->bindEngine($this);
    }

    public function effects(): EffectResolver
    {
        return $this->effects;
    }

    public function processAction(GameMatch $match, User $user, array $payload): array
    {
        $this->ensureTurnDeadline($match);

        $estado = $match->estado;

        // Usa a coleção já carregada — sem query adicional
        $slot = $this->playerSlotFromCollection($match, $user->id);
        if ($estado['jogador_da_vez'] !== $slot) {
            throw new InvalidArgumentException('Não é o seu turno');
        }

        $animacoes = [];
        $acao = $payload['acao'] ?? '';

        match ($acao) {
            'invocar' => $this->invoke($estado, $slot, $payload, $animacoes),
            'atacar_unidade' => $this->attackUnit($estado, $slot, $payload, $animacoes),
            'atacar_jogador' => $this->attackPlayer($estado, $slot, $payload, $animacoes),
            'habilidade' => $this->activeAbility($estado, $slot, $payload, $animacoes),
            'finalizar_turno' => $this->endTurn($match, $estado, $slot, $animacoes),
            default => throw new InvalidArgumentException('Ação inválida'),
        };

        // endTurn já atribui $match->estado internamente; para outros casos atribuímos aqui.
        // Um único save cobre todos os campos alterados (estado, jogador_da_vez, turno, deadline).
        if ($acao !== 'finalizar_turno') {
            $match->estado = $estado;
        }
        $match->save();

        GameBalanceMatchTelemetry::actionApplied(
            $match,
            $user->id,
            $slot,
            $acao,
            $payload,
            $animacoes,
            $match->estado ?? [],
        );

        $this->logAction($match, $user->id, $acao, $payload); // deferred

        $finished = $this->checkVictory($match, $estado, $animacoes);

        if (! $finished) {
            // Broadcast via defer() — executa APÓS a resposta HTTP ser enviada.
            $matchObj = $match;
            $broadcastPayload = $this->broadcastPayloadSnapshot($acao, $payload);
            defer(function () use ($matchObj, $acao, $slot, $animacoes, $broadcastPayload) {
                broadcast(new ActionProcessed($matchObj, $acao, $slot, $animacoes, $broadcastPayload))->toOthers();
            });
        }

        return [
            'sucesso' => true,
            'estado_atualizado' => $estado,
            'animacoes' => $animacoes,
            'finalizada' => $finished,
        ];
    }

    /**
     * Dados mínimos da ação para o oponente repetir animações via WebSocket (sem expor o body completo).
     *
     * @return array<string, string>
     */
    private function broadcastPayloadSnapshot(string $acao, array $payload): array
    {
        return match ($acao) {
            'atacar_unidade' => [
                'instancia_id' => (string) ($payload['instancia_id'] ?? ''),
                'alvo_instancia_id' => (string) ($payload['alvo_instancia_id'] ?? ''),
            ],
            'atacar_jogador' => [
                'instancia_id' => (string) ($payload['instancia_id'] ?? ''),
            ],
            'invocar' => [
                'instancia_id' => (string) ($payload['instancia_id'] ?? ''),
                'alvo_instancia_id' => (string) ($payload['alvo_instancia_id'] ?? ''),
            ],
            default => [],
        };
    }

    public function refreshTurnDeadline(GameMatch $match): void
    {
        $turno = $match->turno ?? 1;
        $cfg = config('game.match.turn_timer');
        $seconds = min(
            $cfg['max_seconds'],
            $cfg['base_seconds'] + ($turno - 1) * $cfg['increment_per_turn']
        );
        $match->turno_deadline_em = now()->addSeconds($seconds);
        $match->save();
    }

    public function ensureTurnDeadline(GameMatch $match): void
    {
        if ($match->status !== MatchStatus::EmAndamento) {
            return;
        }
        if ($match->turno_deadline_em && now()->greaterThan($match->turno_deadline_em)) {
            $estado = $match->estado;
            $slot = $estado['jogador_da_vez'];
            $animacoes = [];
            $this->endTurn($match, $estado, $slot, $animacoes, true);
            $match->save();

            return;
        }
    }

    public function damageUnit(array &$estado, int $slot, array &$unit, int $dmg, array &$animacoes): void
    {
        $id = $unit['instancia_id'];

        if ($unit['flags']['escudo'] ?? false) {
            unset($unit['flags']['escudo']);
            $this->syncUnit($estado, $slot, $unit);
            $animacoes[] = ['tipo' => 'escudo_quebrado', 'instancia_id' => $id];

            return;
        }

        $reducao = 0;
        if ($this->effects->hasPassive($unit['card_id'], 'reducao_dano')) {
            $reducao = 1;
        }
        if ($this->effects->hasPassive($unit['card_id'], 'reducao_dano_acumulativa')) {
            // FIX: nomenclatura consistente de chave + respeitar máximo de 3
            $acumulada = min(3, ($unit['reducao_acumulada'] ?? 0) + 1);
            $unit['reducao_acumulada'] = $acumulada;
            $reducao += $acumulada;
        }
        $dmg = max(1, $dmg - $reducao); // mínimo 1 de dano

        $unit['vida_atual'] -= $dmg;
        $animacoes[] = ['tipo' => 'dano', 'instancia_id' => $id, 'valor' => $dmg];

        if ($unit['vida_atual'] <= 0) {
            $this->killUnit($estado, $slot, $id, $animacoes);
        } else {
            $this->syncUnit($estado, $slot, $unit);
        }
    }

    public function killUnit(array &$estado, int $slot, string $instanciaId, array &$animacoes, bool $triggerDeathEffects = true): void
    {
        $unit = $this->findUnit($estado, $slot, $instanciaId);
        if (! $unit) {
            return;
        }

        // FIX: disparar ao_morrer somente se permitido (Aberração do Vazio suprime)
        if ($triggerDeathEffects) {
            $this->effects->triggerSkills($estado, $slot, 'ao_morrer', $unit, $animacoes);
        }

        if (! isset($unit['vida_max'])) {
            $cardMorta = CardCatalog::get($unit['card_id']);
            $unit['vida_max'] = $cardMorta?->vida ?? max(1, $unit['vida_atual']);
        }
        $estado['ultimo_aliado_morto'][(string) $slot] = $unit;
        $estado['campo'][$slot] = array_values(array_filter(
            $estado['campo'][$slot],
            fn ($u) => $u['instancia_id'] !== $instanciaId
        ));
        $estado['jogadores'][(string) $slot]['cemiterio'][] = $unit['card_id'];

        // Ressurreição única (Aranha de Sucata)
        if ($estado['jogadores'][(string) $slot]['ressurreicao_pendente'] ?? false) {
            $estado['jogadores'][(string) $slot]['ressurreicao_pendente'] = false;
            $estado['jogadores'][(string) $slot]['ressurreicao_usada']    = true;
            if (count($estado['campo'][$slot]) < config('game.match.field.max_units_per_player')) {
                // FIX: adiciona diretamente sem re-chamar apply() que setaria o flag novamente
                $revive = $unit;
                $revive['instancia_id']          = (string) Str::uuid();
                $revive['vida_atual']             = 1;
                $revive['pode_atacar']            = false;
                $revive['foi_invocado_neste_turno'] = true;
                $revive['efeitos']                = [];
                $revive['flags']                  = [];
                $estado['campo'][$slot][]         = $revive;
                $animacoes[] = ['tipo' => 'reviver', 'instancia_id' => $revive['instancia_id']];
            }
        }

        // FIX: crescimento_por_morte notifica AMBOS os lados (Devorador de Estrelas)
        foreach ([1, 2] as $s) {
            foreach ($estado['campo'][$s] as &$ally) {
                if (! ($ally['flags']['crescimento_por_morte'] ?? false)) {
                    continue;
                }
                // Respeitar máximo de +3/+3 acumulados
                $crescAtk = $ally['crescimento_atk'] ?? 0;
                $crescHp  = $ally['crescimento_hp'] ?? 0;
                if ($crescAtk < 3) {
                    $ally['bonus_ataque'] = ($ally['bonus_ataque'] ?? 0) + 1;
                    $ally['crescimento_atk'] = $crescAtk + 1;
                }
                if ($crescHp < 3) {
                    $ally['vida_atual'] += 1;
                    $ally['vida_max'] = ($ally['vida_max'] ?? CardCatalog::get($ally['card_id'])?->vida ?? 1) + 1;
                    $ally['crescimento_hp'] = $crescHp + 1;
                }
                $this->syncUnit($estado, $s, $ally);
            }
            unset($ally);
        }

        $animacoes[] = ['tipo' => 'morte', 'instancia_id' => $instanciaId];
    }

    public function findUnit(array $estado, int $slot, string $instanciaId): ?array
    {
        foreach ($estado['campo'][$slot] as $u) {
            if ($u['instancia_id'] === $instanciaId) {
                return $u;
            }
        }

        return null;
    }

    public function unitAttack(array &$estado, int $slot, array &$attacker, int $oppSlot, ?array &$defender, array &$animacoes): void
    {
        // FIX: confusão (Criança do Véu) — 50% de chance de errar o ataque
        foreach ($attacker['efeitos'] ?? [] as $fx) {
            if ($fx['tipo'] === 'confusao' && rand(0, 1) === 0) {
                $animacoes[] = ['tipo' => 'errou', 'instancia_id' => $attacker['instancia_id']];
                $attacker['pode_atacar'] = false;
                $this->syncUnit($estado, $slot, $attacker);

                return;
            }
        }

        $atk = $this->getUnitAttack($estado, $slot, $attacker);

        if ($defender) {
            $defAtk = $this->getUnitAttack($estado, $oppSlot, $defender);

            // FIX: Executor de Ferro — +2 dano CONTRA unidades com redução de dano
            if ($this->effects->hasPassive($attacker['card_id'], 'dano_bonus_vs_reducao')) {
                if ($this->effects->hasPassive($defender['card_id'], 'reducao_dano') ||
                    $this->effects->hasPassive($defender['card_id'], 'reducao_dano_acumulativa')) {
                    $atk += 2;
                }
            }

            // Rastrear dano real para lifesteal (Costureira Macabra)
            $vidaAntes = $defender['vida_atual'];
            $this->damageUnit($estado, $oppSlot, $defender, $atk, $animacoes);
            $defRef   = $this->findUnit($estado, $oppSlot, $defender['instancia_id']);
            $danoReal = $defRef !== null
                ? max(0, $vidaAntes - $defRef['vida_atual'])
                : $vidaAntes; // morreu: dano = vida que tinha

            $defensorAposDano = $this->findUnit($estado, $oppSlot, $defender['instancia_id']);
            if ($defensorAposDano && ($defensorAposDano['vida_atual'] ?? 0) > 0) {
                $noRetaliation = $this->effects->hasPassive($attacker['card_id'], 'ataque_sem_retaliacao');
                $silenced      = $defender['silenciado'] ?? false;

                // FIX: contra_ataque_extra pertence ao DEFENSOR (Hidra do Pântano)
                $defenderAtual = $this->findUnit($estado, $oppSlot, $defender['instancia_id']);
                $defenderHasExtra = $defenderAtual &&
                    $this->effects->hasPassive($defenderAtual['card_id'], 'contra_ataque_extra') &&
                    ! $silenced;

                if (! $noRetaliation && ! $silenced) {
                    $retaliation = $defenderHasExtra ? $defAtk * 2 : $defAtk;
                    $attackerRef = $this->findUnit($estado, $slot, $attacker['instancia_id']);
                    if ($attackerRef) {
                        $this->damageUnit($estado, $slot, $attackerRef, $retaliation, $animacoes);
                        $attacker['vida_atual'] = $attackerRef['vida_atual'];
                    }
                }
            }

            // Habilidades de "ao_atacar" — passa dano real para lifesteal
            $this->effects->triggerSkills($estado, $slot, 'ao_atacar', $attacker, $animacoes, [
                'alvo' => $defender,
                'dano' => $danoReal,
            ]);
        }

        $attacker['pode_atacar'] = false;
    }

    public function getUnitAttack(array $estado, int $slot, array $unit): int
    {
        $card = CardCatalog::get($unit['card_id']);
        $base = $card?->ataque ?? 0;
        $base += $unit['bonus_ataque'] ?? 0;
        // FIX: passa a facção da unidade para respeitar filtro_faccao da Tesla
        $base += $this->effects->auraAttackBonus($estado, $slot, $card?->faccao);
        $base -= $this->effects->auraEnemyAttackDebuff($estado, $slot);

        foreach ($unit['efeitos'] ?? [] as $fx) {
            if ($fx['tipo'] === 'debuff_ataque') {
                $base -= (int) ($fx['valor'] ?? 1);
            }
        }

        return max(0, $base);
    }

    private function invoke(array &$estado, int $slot, array $payload, array &$animacoes): void
    {
        if ($estado['jogadores'][(string) $slot]['ja_atacou_neste_turno'] ?? false) {
            throw new InvalidArgumentException('Não é possível invocar após realizar um ataque neste turno');
        }

        $instanciaId = $payload['instancia_id'] ?? '';
        $hand = &$estado['jogadores'][(string) $slot]['mao'];
        $idx = $this->handIndex($hand, $instanciaId);
        if ($idx === null) {
            throw new InvalidArgumentException('Carta não está na mão');
        }

        $cardId = $hand[$idx]['card_id'];
        $card = CardCatalog::get($cardId);
        if (! $card) {
            throw new InvalidArgumentException('Carta inválida');
        }

        if (count($estado['campo'][$slot]) >= config('game.match.field.max_units_per_player')) {
            throw new InvalidArgumentException('Campo cheio');
        }

        if ($this->effects->cardRequiresAllyTargetForBattleCry($card) &&
            count($estado['campo'][$slot]) > 0) {
            $alvoId = $payload['alvo_instancia_id'] ?? null;
            if (! $alvoId || ! $this->findUnit($estado, $slot, $alvoId)) {
                throw new InvalidArgumentException('Selecione um aliado em campo');
            }
        }

        $player = &$estado['jogadores'][(string) $slot];
        if ($player['energia_atual'] < $card->custo) {
            throw new InvalidArgumentException('Energia insuficiente');
        }

        $player['energia_atual'] -= $card->custo;
        array_splice($hand, $idx, 1);

        $unit = [
            'instancia_id' => (string) Str::uuid(),
            'card_id' => $cardId,
            'vida_atual' => $card->vida,
            'vida_max' => $card->vida,
            'bonus_ataque' => 0,
            'pode_atacar' => false,
            'foi_invocado_neste_turno' => true,
            'silenciado' => false,
            'efeitos' => [],
            'flags' => [],
        ];
        $this->effects->initializeUnitFlags($unit, $card);

        $estado['campo'][$slot][] = $unit;
        $animacoes[] = ['tipo' => 'invocar', 'instancia_id' => $unit['instancia_id'], 'card_id' => $cardId];

        $unitRef = &$estado['campo'][$slot][array_key_last($estado['campo'][$slot])];
        $contextoGrito = [
            'alvo_instancia_id' => $payload['alvo_instancia_id'] ?? null,
        ];
        $this->effects->applyBattleCry($estado, $slot, $unitRef, $animacoes, $contextoGrito);
    }

    private function attackUnit(array &$estado, int $slot, array $payload, array &$animacoes): void
    {
        $attacker = $this->findUnit($estado, $slot, $payload['instancia_id'] ?? '');
        if (! $attacker || ! ($attacker['pode_atacar'] ?? false)) {
            throw new InvalidArgumentException('Unidade não pode atacar');
        }
        if ($attacker['foi_invocado_neste_turno'] ?? false) {
            throw new InvalidArgumentException('Unidade acabou de ser invocada');
        }

        $opp = $slot === 1 ? 2 : 1;
        $defender = $this->findUnit($estado, $opp, $payload['alvo_instancia_id'] ?? '');
        if (! $defender) {
            throw new InvalidArgumentException('Alvo inválido');
        }

        // Taunt: se houver unidade inimiga com provocação, o atacante deve alvejá-la
        $taunters = array_filter(
            $estado['campo'][$opp],
            fn ($u) => ($u['flags']['taunt_self'] ?? false) && ! ($u['silenciado'] ?? false)
        );
        if (! empty($taunters) && ! ($defender['flags']['taunt_self'] ?? false)) {
            throw new InvalidArgumentException('Deve atacar a unidade com provocação primeiro');
        }

        $this->registrarAtaqueRealizadoNoTurno($estado, $slot);
        $this->unitAttack($estado, $slot, $attacker, $opp, $defender, $animacoes);
        $this->syncUnit($estado, $slot, $attacker);
    }

    private function attackPlayer(array &$estado, int $slot, array $payload, array &$animacoes): void
    {
        $opp = $slot === 1 ? 2 : 1;
        $attacker = $this->findUnit($estado, $slot, $payload['instancia_id'] ?? '');
        $canFace = $attacker && $this->effects->hasPassive($attacker['card_id'], 'ataque_direto_jogador');
        if (! empty($estado['campo'][$opp]) && ! $canFace) {
            throw new InvalidArgumentException('Campo inimigo não está vazio');
        }
        if (! $attacker || ! $attacker['pode_atacar']) {
            throw new InvalidArgumentException('Unidade não pode atacar');
        }

        $this->registrarAtaqueRealizadoNoTurno($estado, $slot);

        $dmg = $this->getUnitAttack($estado, $slot, $attacker);
        $estado['jogadores'][(string) $opp]['vida'] -= $dmg;
        $this->effects->triggerSkills($estado, $slot, 'ao_atacar', $attacker, $animacoes, [
            'dano' => $dmg,
            'alvo' => null,
        ]);
        $attacker['pode_atacar'] = false;
        $this->syncUnit($estado, $slot, $attacker);
        $animacoes[] = ['tipo' => 'dano_jogador', 'player' => $opp, 'valor' => $dmg];
    }

    private function activeAbility(array &$estado, int $slot, array $payload, array &$animacoes): void
    {
        $unit = $this->findUnit($estado, $slot, $payload['instancia_id'] ?? '');
        if (! $unit) {
            throw new InvalidArgumentException('Unidade inválida');
        }
        if ($unit['silenciado'] ?? false) {
            throw new InvalidArgumentException('Unidade está silenciada');
        }
        $card = CardCatalog::get($unit['card_id']);
        foreach ($card?->skills ?? [] as $skill) {
            if ($skill->tipo !== 'ativa') {
                continue;
            }
            // FIX: deduzir energia antes de aplicar o efeito
            $custo = (int) ($skill->efeito['custo_energia'] ?? 0);
            $player = &$estado['jogadores'][(string) $slot];
            if ($player['energia_atual'] < $custo) {
                throw new InvalidArgumentException('Energia insuficiente para usar habilidade');
            }
            $player['energia_atual'] -= $custo;

            $ctx = ['alvo_instancia_id' => $payload['alvo_instancia_id'] ?? null];
            $opp = $slot === 1 ? 2 : 1;
            $alvo = $ctx['alvo_instancia_id']
                ? $this->findUnit($estado, $opp, $ctx['alvo_instancia_id'])
                : null;
            $ctx['alvo'] = $alvo;
            $this->effects->apply($estado, $slot, $unit, $skill->efeito, $animacoes, $ctx);
        }
    }

    private function endTurn(GameMatch $match, array &$estado, int $slot, array &$animacoes, bool $timeout = false): void
    {
        $this->tickStatusEffects($estado, $slot);

        $next = $slot === 1 ? 2 : 1;
        $estado['jogador_da_vez'] = $next;
        $match->jogador_da_vez = $next;
        $match->turno = ($match->turno ?? 1) + 1;
        $estado['turno'] = $match->turno;

        $this->startTurn($estado, $next, $animacoes);

        // Calcula o deadline inline — sem chamar refreshTurnDeadline (que faria um save extra)
        $turno = $match->turno;
        $cfg = config('game.match.turn_timer');
        $seconds = min(
            $cfg['max_seconds'],
            $cfg['base_seconds'] + ($turno - 1) * $cfg['increment_per_turn']
        );
        $match->turno_deadline_em = now()->addSeconds($seconds);

        // Atualiza estado no modelo — processAction fará o único save necessário
        $match->estado = $estado;

        if ($timeout) {
            GameBalanceMatchTelemetry::turnTimeout($match, $slot, $animacoes);
        }

        // Broadcast deferido: executado APÓS a resposta ser enviada ao cliente
        defer(function () use ($match, $next, $timeout) {
            broadcast(new TurnChanged($match, $next, $timeout))->toOthers();
            app(SubstituteBotTurnDispatcher::class)->notify($match->id, $next);
        });
    }

    private function startTurn(array &$estado, int $slot, array &$animacoes): void
    {
        $player = &$estado['jogadores'][(string) $slot];
        $this->reshuffleIfEmpty($player);

        if (count($player['mao']) < config('game.match.field.max_hand_size')) {
            if (! empty($player['deck'])) {
                $cardId = array_shift($player['deck']);
                $player['mao'][] = [
                    'instancia_id' => (string) Str::uuid(),
                    'card_id'      => $cardId,
                ];
                $animacoes[] = ['tipo' => 'comprar', 'player' => $slot];
            }
        }

        $turno = $estado['turno'];
        $maxEnergy = min(
            config('game.match.energy.max'),
            config('game.match.energy.start') + ($turno - 1) * config('game.match.energy.gain_per_turn')
        );
        $player['energia_maxima'] = $maxEnergy;
        $player['energia_atual']  = $maxEnergy + ($player['energia_bonus_turno'] ?? 0);
        $player['energia_bonus_turno'] = 0;
        $player['ja_atacou_neste_turno'] = false;

        foreach ($estado['campo'][$slot] as &$u) {
            $hasCantAttack = false;
            foreach ($u['efeitos'] ?? [] as $fx) {
                if (($fx['tipo'] ?? '') === 'nao_pode_atacar') {
                    $hasCantAttack = true;
                    break;
                }
            }
            $u['pode_atacar']            = ! $hasCantAttack;
            $u['foi_invocado_neste_turno'] = false;
        }
        unset($u);

        // FIX: disparar habilidades de início de turno (Espírito da Raíz, Núcleo Autômato)
        $unitIds = array_column($estado['campo'][$slot], 'instancia_id');
        foreach ($unitIds as $uid) {
            $u = $this->findUnit($estado, $slot, $uid);
            if ($u) {
                $this->effects->triggerSkills($estado, $slot, 'inicio_turno_aliado', $u, $animacoes, []);
            }
        }

        $this->tickPoison($estado, $slot, $animacoes);
    }

    private function tickStatusEffects(array &$estado, int $slot): void
    {
        foreach ($estado['campo'][$slot] as &$u) {
            $u['silenciado'] = false;
            $remaining = [];
            foreach ($u['efeitos'] ?? [] as $fx) {
                $fx['duracao'] = ($fx['duracao'] ?? 1) - 1;
                if ($fx['duracao'] > 0) {
                    $remaining[] = $fx;
                }
            }
            $u['efeitos'] = $remaining;
        }
    }

    private function tickPoison(array &$estado, int $slot, array &$animacoes): void
    {
        foreach ($estado['campo'][$slot] as &$u) {
            foreach ($u['efeitos'] ?? [] as $fx) {
                if ($fx['tipo'] === 'veneno') {
                    $this->damageUnit($estado, $slot, $u, (int) ($fx['valor'] ?? 1), $animacoes);
                }
            }
        }
    }

    private function reshuffleIfEmpty(array &$player): void
    {
        if (! empty($player['deck'])) {
            return;
        }
        if (empty($player['cemiterio'])) {
            return;
        }
        $player['deck'] = $player['cemiterio'];
        $player['cemiterio'] = [];
        shuffle($player['deck']);
    }

    private function checkVictory(GameMatch $match, array &$estado, array &$animacoes): bool
    {
        foreach ([1, 2] as $s) {
            if ($estado['jogadores'][(string) $s]['vida'] <= 0) {
                $winner = $s === 1 ? 2 : 1;
                $winnerUserId = $estado['jogadores'][(string) $winner]['user_id'];
                $match->update([
                    'status' => MatchStatus::Finalizada,
                    'vencedor_id' => $winnerUserId,
                    'finalizada_em' => now(),
                ]);
                event(new MatchFinished($match, $winnerUserId, 'vida_zerada'));

                return true;
            }
        }

        return false;
    }

    /**
     * Usa a coleção já eager-loaded — zero queries.
     * Requer GameMatch::with('players') carregado antes de chamar.
     */
    private function playerSlotFromCollection(GameMatch $match, int $userId): int
    {
        $mp = $match->players->first(fn ($p) => $p->user_id === $userId);
        if (! $mp) {
            throw new InvalidArgumentException('Jogador não está na partida');
        }

        return $mp->player_slot;
    }

    /** @deprecated Use playerSlotFromCollection para evitar N+1 */
    private function playerSlot(GameMatch $match, int $userId): int
    {
        return $this->playerSlotFromCollection($match, $userId);
    }

    private function handIndex(array $hand, string $instanciaId): ?int
    {
        foreach ($hand as $i => $c) {
            if ($c['instancia_id'] === $instanciaId) {
                return $i;
            }
        }

        return null;
    }

    private function registrarAtaqueRealizadoNoTurno(array &$estado, int $slot): void
    {
        $estado['jogadores'][(string) $slot]['ja_atacou_neste_turno'] = true;
    }

    private function syncUnit(array &$estado, int $slot, array $unit): void
    {
        foreach ($estado['campo'][$slot] as $i => $u) {
            if ($u['instancia_id'] === $unit['instancia_id']) {
                $estado['campo'][$slot][$i] = $unit;
            }
        }
    }

    private function logAction(GameMatch $match, int $userId, string $acao, array $payload): void
    {
        // defer() executa APÓS a resposta HTTP ser enviada ao cliente —
        // o INSERT não bloqueia o tempo de resposta.
        $matchId = $match->id;
        $turno = $match->turno;
        $cardId = $payload['card_id'] ?? null;

        defer(function () use ($matchId, $turno, $userId, $acao, $cardId, $payload) {
            MatchLog::create([
                'match_id' => $matchId,
                'turno' => $turno,
                'user_id' => $userId,
                'acao' => $acao,
                'card_id' => $cardId,
                'meta' => $payload,
            ]);
        });
    }
}
