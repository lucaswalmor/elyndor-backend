<?php

namespace App\Services\Logging;

use App\Models\GameMatch;
use Illuminate\Support\Facades\Log;

/**
 * Combat/action traces no canal {@see config/logging.php} `game_balance`.
 * Telemetria sempre activa para playtests de balanceamento (remover quando não precisar).
 */
final class GameBalanceMatchTelemetry
{
    private const DIGEST_UNITS_CAP = 12;

    public static function matchStarted(GameMatch $match): void
    {
        $match->loadMissing('players');

        Log::channel('game_balance')->info('match.started', [
            'match_id' => $match->id,
            'modo' => $match->modo,
            'players' => $match->players->map(fn ($p) => [
                'slot' => $p->player_slot,
                'user_id' => $p->user_id,
                'deck_id' => $p->deck_id,
                'is_bot' => (bool) ($p->is_bot ?? false),
            ])->sortBy('slot')->values()->all(),
            'turno' => $match->turno,
            'jogador_da_vez' => $match->jogador_da_vez,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload  Body HTTP da ação (validado pelo motor).
     * @param  list<array<string, mixed>>  $animacoes  Passos derivados no servidor (dano, morte, comprar, etc.).
     * @param  array<string, mixed>  $estadoAfter  Estado JSON completo após save (referência ao modelo).
     */
    public static function actionApplied(
        GameMatch $match,
        int $actorUserId,
        int $actorSlot,
        string $acao,
        array $payload,
        array $animacoes,
        array $estadoAfter,
    ): void {
        Log::channel('game_balance')->info('match.action_applied', [
            'match_id' => $match->id,
            'modo' => $match->modo,
            'turno_modelo' => $match->turno,
            'jogador_da_vez' => $estadoAfter['jogador_da_vez'] ?? null,
            'actor_user_id' => $actorUserId,
            'actor_slot' => $actorSlot,
            'acao' => $acao,
            'payload' => $payload,
            'animacoes' => $animacoes,
            'state_digest' => self::digestEstado($estadoAfter),
        ]);
    }

    /**
     * Turno encerrado automaticamente por timer antes da próxima ação HTTP.
     *
     * @param  list<array<string, mixed>>  $animacoes
     */
    public static function turnTimeout(GameMatch $match, int $slotQuePerdeuTurno, array $animacoes): void
    {
        $estado = $match->estado ?? [];

        $row = [
            'match_id' => $match->id,
            'modo' => $match->modo,
            'slot_encerrado' => $slotQuePerdeuTurno,
            'turno_modelo' => $match->turno,
            'proximo_jogador' => $estado['jogador_da_vez'] ?? null,
            'animacoes' => $animacoes,
        ];

        if ($estado !== []) {
            $row['state_digest'] = self::digestEstado($estado);
        }

        Log::channel('game_balance')->info('match.turn_timeout', $row);
    }

    /**
     * Pedido HTTP válido até ao motor, mas regra de jogo recusou (400).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function actionRejected(GameMatch $match, int $actorUserId, array $payload, string $reason): void
    {
        $match->refresh();
        $estado = $match->estado ?? [];

        Log::channel('game_balance')->warning('match.action_rejected', [
            'match_id' => $match->id,
            'modo' => $match->modo,
            'actor_user_id' => $actorUserId,
            'payload' => $payload,
            'reason' => $reason,
            'turno_modelo' => $match->turno,
            'jogador_da_vez' => $estado['jogador_da_vez'] ?? null,
            'state_digest' => $estado !== [] ? self::digestEstado($estado) : null,
        ]);
    }

    /**
     * Fluxo de partida bloqueado antes do motor (ex.: rendição com status inválido).
     *
     * @param  array<string, mixed>  $extra
     */
    public static function flowRejected(GameMatch $match, ?int $userId, string $context, string $reason, array $extra = []): void
    {
        $match->refresh();
        $estado = $match->estado ?? [];

        Log::channel('game_balance')->warning('match.flow_rejected', array_merge([
            'match_id' => $match->id,
            'modo' => $match->modo,
            'context' => $context,
            'user_id' => $userId,
            'reason' => $reason,
            'match_status' => $match->status->value,
            'turno_modelo' => $match->turno,
            'jogador_da_vez' => $estado['jogador_da_vez'] ?? null,
            'state_digest' => $estado !== [] ? self::digestEstado($estado) : null,
        ], $extra));
    }

    public static function matchFinished(GameMatch $match, int $vencedorUserId, string $motivo): void
    {
        $estado = $match->estado ?? [];

        $row = [
            'match_id' => $match->id,
            'modo' => $match->modo,
            'vencedor_user_id' => $vencedorUserId,
            'motivo' => $motivo,
            'turnos_jogados' => $match->turno,
            'duracao_s' => ($match->iniciada_em && $match->finalizada_em)
                ? $match->iniciada_em->diffInSeconds($match->finalizada_em)
                : null,
        ];

        if ($estado !== []) {
            $row['final_state_digest'] = self::digestEstado($estado);
        }

        Log::channel('game_balance')->info('match.finished', $row);
    }

    /**
     * @param  array<string, mixed>  $estado
     * @return array<string, mixed>
     */
    private static function digestEstado(array $estado): array
    {
        $cap = max(1, self::DIGEST_UNITS_CAP);

        $digest = [
            'turno_estado' => $estado['turno'] ?? null,
            'jogador_da_vez' => $estado['jogador_da_vez'] ?? null,
        ];

        foreach ([1, 2] as $slot) {
            $key = (string) $slot;
            $p = $estado['jogadores'][$key] ?? [];
            $campo = $estado['campo'][$slot] ?? [];

            $digest["slot_$slot"] = [
                'user_id' => $p['user_id'] ?? null,
                'vida' => $p['vida'] ?? null,
                'energia_atual' => $p['energia_atual'] ?? null,
                'energia_maxima' => $p['energia_maxima'] ?? null,
                'mao_count' => isset($p['mao']) ? count($p['mao']) : null,
                'deck_count' => isset($p['deck']) ? count($p['deck']) : null,
                'cemiterio_count' => isset($p['cemiterio']) ? count($p['cemiterio']) : null,
                'campo_units' => array_slice(array_values(array_map(static fn ($u) => [
                    'instancia_id' => $u['instancia_id'] ?? null,
                    'card_id' => $u['card_id'] ?? null,
                    'vida_atual' => $u['vida_atual'] ?? null,
                    'pode_atacar' => $u['pode_atacar'] ?? null,
                    'foi_invocado_neste_turno' => $u['foi_invocado_neste_turno'] ?? null,
                ], $campo)), 0, $cap),
            ];
        }

        return $digest;
    }
}
