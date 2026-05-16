<?php

namespace App\Services\Game;

use App\Enums\MatchStatus;
use App\Events\ActionProcessed;
use App\Events\MatchFinished;
use App\Events\TurnChanged;
use App\Models\GameMatch;
use App\Models\MatchLog;
use App\Models\User;
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
        $t0 = hrtime(true);

        $this->ensureTurnDeadline($match);

        $estado = $match->estado;

        // Usa a coleção já carregada — sem query adicional
        $slot = $this->playerSlotFromCollection($match, $user->id);
        if ($estado['jogador_da_vez'] !== $slot) {
            throw new InvalidArgumentException('Não é o seu turno');
        }

        $animacoes = [];
        $acao = $payload['acao'] ?? '';

        $t1 = hrtime(true);

        match ($acao) {
            'invocar'         => $this->invoke($estado, $slot, $payload, $animacoes),
            'atacar_unidade'  => $this->attackUnit($estado, $slot, $payload, $animacoes),
            'atacar_jogador'  => $this->attackPlayer($estado, $slot, $payload, $animacoes),
            'habilidade'      => $this->activeAbility($estado, $slot, $payload, $animacoes),
            'finalizar_turno' => $this->endTurn($match, $estado, $slot, $animacoes),
            default           => throw new InvalidArgumentException('Ação inválida'),
        };

        $t2 = hrtime(true);

        // endTurn já atribui $match->estado internamente; para outros casos atribuímos aqui.
        // Um único save cobre todos os campos alterados (estado, jogador_da_vez, turno, deadline).
        if ($acao !== 'finalizar_turno') {
            $match->estado = $estado;
        }
        $match->save();

        $t3 = hrtime(true);

        $this->logAction($match, $user->id, $acao, $payload); // deferred

        $finished = $this->checkVictory($match, $estado, $animacoes);

        $t4 = hrtime(true);

        if (! $finished) {
            // Broadcast via defer() — executa APÓS a resposta HTTP ser enviada,
            // não bloqueia o tempo de resposta do jogador atual.
            $matchSnap   = $match->id;
            $acoaSnap    = $acao;
            $slotSnap    = $slot;
            $animSnap    = $animacoes;
            $matchObj    = $match;
            defer(function () use ($matchObj, $acoaSnap, $slotSnap, $animSnap) {
                broadcast(new ActionProcessed($matchObj, $acoaSnap, $slotSnap, $animSnap))->toOthers();
            });
        }

        $t5 = hrtime(true);

        \Illuminate\Support\Facades\Log::info('[processAction] timings (ms)', [
            'acao'        => $acao,
            'setup'       => round(($t1 - $t0) / 1e6, 2),
            'game_logic'  => round(($t2 - $t1) / 1e6, 2),
            'db_save'     => round(($t3 - $t2) / 1e6, 2),
            'victory'     => round(($t4 - $t3) / 1e6, 2),
            'broadcast'   => round(($t5 - $t4) / 1e6, 2),
            'total'       => round(($t5 - $t0) / 1e6, 2),
        ]);

        return [
            'sucesso'          => true,
            'estado_atualizado'=> $estado,
            'animacoes'        => $animacoes,
            'finalizada'       => $finished,
        ];
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
            $match->refresh();
        }
    }

    public function damageUnit(array &$estado, int $slot, array &$unit, int $dmg, array &$animacoes): void
    {
        $id = $unit['instancia_id'];

        if ($unit['flags']['escudo'] ?? false) {
            // Remove o escudo da cópia local E persiste no $estado imediatamente.
            // Sem o syncUnit aqui o escudo nunca seria consumido.
            unset($unit['flags']['escudo']);
            $this->syncUnit($estado, $slot, $unit);
            $animacoes[] = ['tipo' => 'escudo_quebrado', 'instancia_id' => $id];

            \Illuminate\Support\Facades\Log::info('[damageUnit] escudo absorveu ataque', [
                'instancia_id' => $id,
                'card_id'      => $unit['card_id'],
            ]);

            return;
        }

        $reducao = 0;
        if ($this->effects->hasPassive($unit['card_id'], 'reducao_dano')) {
            $reducao = 1;
        }
        if ($this->effects->hasPassive($unit['card_id'], 'reducao_dano_acumulativa')) {
            $unit['reducao_acumulada'] = ($unit['reducao_acumulativa'] ?? 0) + 1;
            $reducao += $unit['reducao_acumulada'];
        }
        $dmg = max(0, $dmg - $reducao);

        $unit['vida_atual'] -= $dmg;
        $animacoes[] = ['tipo' => 'dano', 'instancia_id' => $id, 'valor' => $dmg];

        \Illuminate\Support\Facades\Log::info('[damageUnit] dano aplicado', [
            'instancia_id' => $id,
            'card_id'      => $unit['card_id'],
            'dmg'          => $dmg,
            'vida_restante' => $unit['vida_atual'],
        ]);

        if ($unit['vida_atual'] <= 0) {
            $this->killUnit($estado, $slot, $id, $animacoes);
        } else {
            // Persiste a vida reduzida no $estado imediatamente para que
            // chamadas subsequentes (findUnit, syncUnit, etc.) vejam o valor correto.
            $this->syncUnit($estado, $slot, $unit);
        }
    }

    public function killUnit(array &$estado, int $slot, string $instanciaId, array &$animacoes): void
    {
        $unit = $this->findUnit($estado, $slot, $instanciaId);
        if (! $unit) {
            return;
        }

        \Illuminate\Support\Facades\Log::info('[killUnit] unidade eliminada', [
            'instancia_id' => $instanciaId,
            'card_id'      => $unit['card_id'],
            'slot'         => $slot,
        ]);

        $this->effects->triggerSkills($estado, $slot, 'ao_morrer', $unit, $animacoes);

        $estado['ultimo_aliado_morto'][(string) $slot] = $unit;
        $estado['campo'][$slot] = array_values(array_filter(
            $estado['campo'][$slot],
            fn ($u) => $u['instancia_id'] !== $instanciaId
        ));
        $estado['jogadores'][(string) $slot]['cemiterio'][] = $unit['card_id'];

        if ($estado['jogadores'][(string) $slot]['ressurreicao_pendente'] ?? false) {
            $estado['jogadores'][(string) $slot]['ressurreicao_pendente'] = false;
            $estado['jogadores'][(string) $slot]['ressurreicao_usada'] = true;
            $revive = $unit;
            $revive['vida_max'] = CardCatalog::get($unit['card_id'])?->vida ?? 1;
            $estado['ultimo_aliado_morto'][(string) $slot] = $revive;
            if (count($estado['campo'][$slot]) < config('game.match.field.max_units_per_player')) {
                $this->effects->apply($estado, $slot, $revive, ['tipo' => 'ressurreicao_unica'], $animacoes);
            }
        }

        foreach ($estado['campo'][$slot] as &$ally) {
            if ($ally['flags']['crescimento_por_morte'] ?? false) {
                $ally['bonus_ataque'] = ($ally['bonus_ataque'] ?? 0) + 1;
                $ally['vida_atual'] += 1;
            }
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
        $atk = $this->getUnitAttack($estado, $slot, $attacker);
        if ($defender) {
            $defAtk = $this->getUnitAttack($estado, $oppSlot, $defender);

            // damageUnit modifica $defender localmente E persiste no $estado (syncUnit interno)
            $this->damageUnit($estado, $oppSlot, $defender, $atk, $animacoes);

            if ($defender['vida_atual'] > 0) {
                // Contra-ataque (defensor sobreviveu)
                $noRetaliation = $this->effects->hasPassive($attacker['card_id'], 'ataque_sem_retaliacao');
                $silenced = $defender['silenciado'] ?? false;
                $extra = $this->effects->hasPassive($attacker['card_id'], 'contra_ataque_extra');
                if (! $noRetaliation && ! $silenced) {
                    $retaliation = $extra ? $defAtk * 2 : $defAtk;
                    if ($this->effects->hasPassive($attacker['card_id'], 'dano_bonus_vs_reducao')) {
                        $retaliation = max($retaliation, $atk);
                    }
                    // Busca o atacante direto do $estado (já sincronizado)
                    $attackerRef = $this->findUnit($estado, $slot, $attacker['instancia_id']);
                    if ($attackerRef) {
                        $this->damageUnit($estado, $slot, $attackerRef, $retaliation, $animacoes);
                        // Propaga vida atualizada para $attacker (usado em syncUnit do caller)
                        $attacker['vida_atual'] = $attackerRef['vida_atual'];
                    }
                }
            }
            // Habilidades de "ao_atacar" (gatilho pós-combate)
            $this->effects->triggerSkills($estado, $slot, 'ao_atacar', $attacker, $animacoes, ['alvo' => $defender]);
        }
        $attacker['pode_atacar'] = false;
        // Caller (attackUnit) faz syncUnit do $attacker com pode_atacar=false e vida correta
    }

    public function getUnitAttack(array $estado, int $slot, array $unit): int
    {
        $card = CardCatalog::get($unit['card_id']);
        $base = $card?->ataque ?? 0;
        $base += $unit['bonus_ataque'] ?? 0;
        $base += $this->effects->auraAttackBonus($estado, $slot);
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
            'bonus_ataque' => 0,
            'pode_atacar' => false,
            'foi_invocado_neste_turno' => true,
            'silenciado' => false,
            'efeitos' => [],
            'flags' => [],
        ];

        $estado['campo'][$slot][] = $unit;
        $animacoes[] = ['tipo' => 'invocar', 'instancia_id' => $unit['instancia_id'], 'card_id' => $cardId];

        $unitRef = &$estado['campo'][$slot][array_key_last($estado['campo'][$slot])];
        $this->effects->applyBattleCry($estado, $slot, $unitRef, $animacoes);
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

        \Illuminate\Support\Facades\Log::info('[attackUnit] início do ataque', [
            'atacante'  => ['id' => $attacker['instancia_id'], 'card' => $attacker['card_id'], 'atk' => $attacker['ataque'] ?? '?', 'vida' => $attacker['vida_atual']],
            'defensor'  => ['id' => $defender['instancia_id'], 'card' => $defender['card_id'], 'vida' => $defender['vida_atual'], 'flags' => $defender['flags'] ?? []],
        ]);

        $this->unitAttack($estado, $slot, $attacker, $opp, $defender, $animacoes);
        $this->syncUnit($estado, $slot, $attacker);

        \Illuminate\Support\Facades\Log::info('[attackUnit] após ataque — defensor no estado', [
            'defensor_atual' => $this->findUnit($estado, $opp, $defender['instancia_id']) ?? 'REMOVIDO (morto)',
        ]);
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

        $dmg = $this->getUnitAttack($estado, $slot, $attacker);
        $estado['jogadores'][(string) $opp]['vida'] -= $dmg;
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
        $card = CardCatalog::get($unit['card_id']);
        foreach ($card?->skills ?? [] as $skill) {
            if ($skill->tipo !== 'ativa') {
                continue;
            }
            $ctx = ['alvo_instancia_id' => $payload['alvo_instancia_id'] ?? null];
            $opp = $slot === 1 ? 2 : 1;
            $alvo = $ctx['alvo_instancia_id']
                ? $this->findUnit($estado, $opp, $ctx['alvo_instancia_id'])
                : null;
            $ctx['alvo'] = &$alvo;
            $this->effects->apply($estado, $slot, $unit, $skill->efeito, $animacoes, $ctx);
        }
    }

    private function endTurn(GameMatch $match, array &$estado, int $slot, array &$animacoes, bool $timeout = false): void
    {
        $this->tickStatusEffects($estado, $slot);

        $next = $slot === 1 ? 2 : 1;
        $estado['jogador_da_vez'] = $next;
        $match->jogador_da_vez   = $next;
        $match->turno            = ($match->turno ?? 1) + 1;
        $estado['turno']         = $match->turno;

        $this->startTurn($estado, $next, $animacoes);

        // Calcula o deadline inline — sem chamar refreshTurnDeadline (que faria um save extra)
        $turno   = $match->turno;
        $cfg     = config('game.match.turn_timer');
        $seconds = min(
            $cfg['max_seconds'],
            $cfg['base_seconds'] + ($turno - 1) * $cfg['increment_per_turn']
        );
        $match->turno_deadline_em = now()->addSeconds($seconds);

        // Atualiza estado no modelo — processAction fará o único save necessário
        $match->estado = $estado;

        // Broadcast deferido: executado APÓS a resposta ser enviada ao cliente
        defer(function () use ($match, $next, $timeout) {
            broadcast(new TurnChanged($match, $next, $timeout))->toOthers();
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
                    'card_id' => $cardId,
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
        $player['energia_atual'] = $maxEnergy + ($player['energia_bonus_turno'] ?? 0);
        $player['energia_bonus_turno'] = 0;

        foreach ($estado['campo'][$slot] as &$u) {
            $u['pode_atacar'] = true;
            $u['foi_invocado_neste_turno'] = false;
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
                broadcast(new MatchFinished($match, $winnerUserId, 'vida_zerada'))->toOthers();

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
        $turno   = $match->turno;
        $cardId  = $payload['card_id'] ?? null;

        defer(function () use ($matchId, $turno, $userId, $acao, $cardId, $payload) {
            MatchLog::create([
                'match_id' => $matchId,
                'turno'    => $turno,
                'user_id'  => $userId,
                'acao'     => $acao,
                'card_id'  => $cardId,
                'meta'     => $payload,
            ]);
        });
    }
}
