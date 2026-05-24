<?php

namespace App\Services\Logging;

use App\Models\GameMatch;
use App\Models\MatchLog;
use App\Services\Bot\SubstituteDifficultyResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Telemetria de balanceamento em dois canais:
 * - game_balance_pvp: casual/ranqueada entre dois jogadores humanos
 * - game_balance_vs_bot: casual/ranqueada com substituto (bot)
 *
 * Estado completo em cada evento para análise de cartas, decks e IA.
 */
final class GameBalanceMatchTelemetry
{
    public const CANAL_PVP = 'game_balance_pvp';

    public const CANAL_VS_BOT = 'game_balance_vs_bot';

    /** @var list<string> */
    private const MODOS_COM_TELEMETRIA = ['normal', 'ranqueada'];

    public static function matchStarted(GameMatch $match): void
    {
        $match->loadMissing('players.user');
        $estado = $match->estado ?? [];

        $contexto = [
            'turno' => $match->turno,
            'jogador_da_vez' => $match->jogador_da_vez,
            'estado_completo' => $estado !== [] ? $estado : null,
            'resumo_estado' => $estado !== [] ? self::resumoEstado($estado) : null,
        ];

        if (self::partidaTemBot($match)) {
            $slotBot = self::slotDoBot($match);
            if ($slotBot !== null) {
                $contexto['contexto_bot'] = self::contextoBot($match, $slotBot);
            }
        }

        self::registrar($match, 'info', 'match.started', $contexto);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $animacoes
     * @param  array<string, mixed>  $estadoAfter
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
        $contexto = [
            'turno_modelo' => $match->turno,
            'jogador_da_vez' => $estadoAfter['jogador_da_vez'] ?? null,
            'actor_user_id' => $actorUserId,
            'actor_slot' => $actorSlot,
            'actor_eh_bot' => self::slotEhBot($match, $actorSlot),
            'acao' => $acao,
            'payload' => $payload,
            'animacoes' => $animacoes,
            'estado_completo' => $estadoAfter,
            'resumo_estado' => self::resumoEstado($estadoAfter),
        ];

        if (self::partidaTemBot($match)) {
            $contexto['contexto_bot'] = self::contextoBot($match, $actorSlot);
        }

        self::registrar($match, 'info', 'match.action_applied', $contexto);
    }

    /**
     * @param  list<array<string, mixed>>  $animacoes
     */
    public static function turnTimeout(GameMatch $match, int $slotQuePerdeuTurno, array $animacoes): void
    {
        $estado = $match->estado ?? [];

        self::registrar($match, 'info', 'match.turn_timeout', [
            'slot_encerrado' => $slotQuePerdeuTurno,
            'slot_encerrado_eh_bot' => self::slotEhBot($match, $slotQuePerdeuTurno),
            'turno_modelo' => $match->turno,
            'proximo_jogador' => $estado['jogador_da_vez'] ?? null,
            'animacoes' => $animacoes,
            'estado_completo' => $estado !== [] ? $estado : null,
            'resumo_estado' => $estado !== [] ? self::resumoEstado($estado) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function actionRejected(GameMatch $match, int $actorUserId, array $payload, string $reason): void
    {
        $match->refresh();
        $estado = $match->estado ?? [];
        $slotAtor = self::slotDoUsuario($match, $actorUserId);

        $contexto = [
            'actor_user_id' => $actorUserId,
            'actor_slot' => $slotAtor,
            'actor_eh_bot' => $slotAtor !== null && self::slotEhBot($match, $slotAtor),
            'payload' => $payload,
            'reason' => $reason,
            'turno_modelo' => $match->turno,
            'jogador_da_vez' => $estado['jogador_da_vez'] ?? null,
            'estado_completo' => $estado !== [] ? $estado : null,
            'resumo_estado' => $estado !== [] ? self::resumoEstado($estado) : null,
        ];

        if (self::partidaTemBot($match) && $slotAtor !== null) {
            $contexto['contexto_bot'] = self::contextoBot($match, $slotAtor);
        }

        self::registrar($match, 'warning', 'match.action_rejected', $contexto);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function flowRejected(GameMatch $match, ?int $userId, string $context, string $reason, array $extra = []): void
    {
        $match->refresh();
        $estado = $match->estado ?? [];
        $slotAtor = $userId !== null ? self::slotDoUsuario($match, $userId) : null;

        self::registrar($match, 'warning', 'match.flow_rejected', array_merge([
            'context' => $context,
            'user_id' => $userId,
            'actor_slot' => $slotAtor,
            'actor_eh_bot' => $slotAtor !== null && self::slotEhBot($match, $slotAtor),
            'reason' => $reason,
            'match_status' => $match->status->value,
            'turno_modelo' => $match->turno,
            'jogador_da_vez' => $estado['jogador_da_vez'] ?? null,
            'estado_completo' => $estado !== [] ? $estado : null,
            'resumo_estado' => $estado !== [] ? self::resumoEstado($estado) : null,
        ], $extra));
    }

    public static function matchFinished(GameMatch $match, int $vencedorUserId, string $motivo): void
    {
        $match->loadMissing('players.user');
        $estado = $match->estado ?? [];

        $contexto = [
            'vencedor_user_id' => $vencedorUserId,
            'motivo' => $motivo,
            'turnos_jogados' => $match->turno,
            'duracao_s' => ($match->iniciada_em && $match->finalizada_em)
                ? $match->iniciada_em->diffInSeconds($match->finalizada_em)
                : null,
            'estado_completo' => $estado !== [] ? $estado : null,
            'resumo_final' => $estado !== [] ? self::resumoEstado($estado) : null,
        ];

        if (self::partidaTemBot($match)) {
            $contexto['analise_vs_bot'] = self::analiseResultadoVsBot($match, $vencedorUserId);
            $contexto['contexto_bot'] = self::contextoBot($match, self::slotDoBot($match) ?? 1);
            $contexto['linha_tempo_partida'] = self::linhaTempoPartida($match);
            $contexto['estatisticas_partida'] = self::estatisticasPartida($match);
        } else {
            $contexto['analise_pvp'] = self::analiseResultadoPvp($match, $vencedorUserId);
            $contexto['linha_tempo_partida'] = self::linhaTempoPartida($match);
            $contexto['estatisticas_partida'] = self::estatisticasPartida($match);
        }

        self::registrar($match, 'info', 'match.finished', $contexto);

        Cache::forget(self::chaveSequenciaPartida($match->id));
    }

    /**
     * Resultado ranqueado (pontos/MMR) — mesmo canal da partida (PvP ou vs bot).
     *
     * @param  array<string, mixed>  $dados
     */
    public static function rankedPlayerOutcome(GameMatch $match, array $dados): void
    {
        self::registrar($match, 'info', 'ranked.match_player_outcome', $dados);
    }

    /**
     * Decisão heurística do substituto antes de enviar ao motor (apenas canal vs_bot).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function botAcaoPlanejada(GameMatch $match, int $botSlot, array $payload): void
    {
        if (self::resolverCanal($match) !== self::CANAL_VS_BOT) {
            return;
        }

        $estado = $match->estado ?? [];

        self::registrar($match, 'info', 'bot.acao_planejada', [
            'bot_slot' => $botSlot,
            'payload_planejado' => $payload,
            'contexto_bot' => self::contextoBot($match, $botSlot),
            'estado_completo' => $estado !== [] ? $estado : null,
            'resumo_estado' => $estado !== [] ? self::resumoEstado($estado) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    private static function registrar(GameMatch $match, string $nivel, string $evento, array $contexto): void
    {
        $canal = self::resolverCanal($match);
        if ($canal === null) {
            return;
        }

        Log::channel($canal)->{$nivel}($evento, array_merge(
            self::envelopePartida($match),
            ['sequencia_evento' => self::proximaSequencia($match->id)],
            $contexto,
        ));
    }

    private static function chaveSequenciaPartida(int $matchId): string
    {
        return "game_balance:sequencia:{$matchId}";
    }

    private static function proximaSequencia(int $matchId): int
    {
        $chave = self::chaveSequenciaPartida($matchId);

        if (! Cache::has($chave)) {
            Cache::put($chave, 0, now()->addHours(6));
        }

        return (int) Cache::increment($chave);
    }

    private static function slotDoBot(GameMatch $match): ?int
    {
        if (! $match->relationLoaded('players')) {
            $match->loadMissing('players');
        }

        $participanteBot = $match->players->first(fn ($participante) => $participante->is_bot);

        return $participanteBot !== null ? (int) $participanteBot->player_slot : null;
    }

    /**
     * Cronologia completa das ações persistidas (MatchLog) — útil para reconstruir a partida.
     *
     * @return list<array<string, mixed>>
     */
    private static function linhaTempoPartida(GameMatch $match): array
    {
        $match->loadMissing('players');

        return MatchLog::query()
            ->where('match_id', $match->id)
            ->orderBy('id')
            ->get()
            ->map(function (MatchLog $registro) use ($match) {
                $slotAtor = self::slotDoUsuario($match, (int) $registro->user_id);

                return [
                    'id' => $registro->id,
                    'turno' => $registro->turno,
                    'user_id' => $registro->user_id,
                    'actor_slot' => $slotAtor,
                    'actor_eh_bot' => $slotAtor !== null && self::slotEhBot($match, $slotAtor),
                    'acao' => $registro->acao,
                    'card_id' => $registro->card_id,
                    'card_alvo_id' => $registro->card_alvo_id,
                    'dano_causado' => $registro->dano_causado,
                    'vida_antes' => $registro->vida_antes,
                    'vida_depois' => $registro->vida_depois,
                    'efeito_tipo' => $registro->efeito_tipo,
                    'meta' => $registro->meta,
                    'created_at' => $registro->created_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function estatisticasPartida(GameMatch $match): array
    {
        $match->loadMissing('players');

        $registros = MatchLog::query()
            ->where('match_id', $match->id)
            ->orderBy('id')
            ->get();

        $acoesPorSlot = [1 => 0, 2 => 0];
        $acoesPorTipo = [];
        $acoesHumano = 0;
        $acoesBot = 0;

        foreach ($registros as $registro) {
            $slotAtor = self::slotDoUsuario($match, (int) $registro->user_id);
            if ($slotAtor !== null) {
                $acoesPorSlot[$slotAtor] = ($acoesPorSlot[$slotAtor] ?? 0) + 1;
                if (self::slotEhBot($match, $slotAtor)) {
                    $acoesBot++;
                } else {
                    $acoesHumano++;
                }
            }

            $tipoAcao = (string) $registro->acao;
            $acoesPorTipo[$tipoAcao] = ($acoesPorTipo[$tipoAcao] ?? 0) + 1;
        }

        return [
            'total_acoes' => $registros->count(),
            'acoes_humano' => $acoesHumano,
            'acoes_bot' => $acoesBot,
            'acoes_por_slot' => $acoesPorSlot,
            'acoes_por_tipo' => $acoesPorTipo,
            'turnos_jogados' => $match->turno,
        ];
    }

    /**
     * @return self::CANAL_PVP|self::CANAL_VS_BOT|null
     */
    public static function resolverCanal(GameMatch $match): ?string
    {
        $modo = trim((string) ($match->modo ?? ''));
        if (! in_array($modo, self::MODOS_COM_TELEMETRIA, true)) {
            return null;
        }

        $match->loadMissing('players');

        return self::partidaTemBot($match) ? self::CANAL_VS_BOT : self::CANAL_PVP;
    }

    /**
     * @return array<string, mixed>
     */
    private static function envelopePartida(GameMatch $match): array
    {
        $match->loadMissing('players.user');

        $jogadores = $match->players->sortBy('player_slot')->values()->map(fn ($participante) => [
            'slot' => $participante->player_slot,
            'user_id' => $participante->user_id,
            'nickname' => $participante->user?->nickname ?? $participante->user?->name,
            'deck_id' => $participante->deck_id,
            'is_bot' => (bool) $participante->is_bot,
            'ranked_points' => $participante->is_bot
                ? null
                : ($participante->user?->ranked_points ?? null),
        ])->all();

        $temBot = self::partidaTemBot($match);

        return [
            'match_id' => $match->id,
            'modo' => $match->modo,
            'tipo_confronto' => $temBot ? 'jogador_vs_bot' : 'pvp',
            'match_status' => $match->status->value,
            'players' => $jogadores,
        ];
    }

    private static function partidaTemBot(GameMatch $match): bool
    {
        if (! $match->relationLoaded('players')) {
            $match->loadMissing('players');
        }

        return $match->players->contains(fn ($participante) => (bool) $participante->is_bot);
    }

    private static function slotEhBot(GameMatch $match, int $slot): bool
    {
        if (! $match->relationLoaded('players')) {
            $match->loadMissing('players');
        }

        return (bool) ($match->players->firstWhere('player_slot', $slot)?->is_bot ?? false);
    }

    private static function slotDoUsuario(GameMatch $match, int $userId): ?int
    {
        if (! $match->relationLoaded('players')) {
            $match->loadMissing('players');
        }

        $participante = $match->players->firstWhere('user_id', $userId);

        return $participante !== null ? (int) $participante->player_slot : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function contextoBot(GameMatch $match, int $slotReferencia): ?array
    {
        if (! self::partidaTemBot($match)) {
            return null;
        }

        $slotBot = null;
        foreach ($match->players as $participante) {
            if ($participante->is_bot) {
                $slotBot = (int) $participante->player_slot;
                break;
            }
        }

        if ($slotBot === null) {
            return null;
        }

        $perfil = app(SubstituteDifficultyResolver::class)->forMatch($match, $slotBot);
        $participanteBot = $match->players->firstWhere('player_slot', $slotBot);

        return [
            'bot_slot' => $slotBot,
            'bot_user_id' => $participanteBot?->user_id,
            'bot_deck_id' => $participanteBot?->deck_id,
            'perfil_dificuldade' => $perfil,
            'slot_ator_referencia' => $slotReferencia,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function analiseResultadoVsBot(GameMatch $match, int $vencedorUserId): array
    {
        $participanteBot = $match->players->first(fn ($participante) => $participante->is_bot);
        $participanteHumano = $match->players->first(fn ($participante) => ! $participante->is_bot);

        $botVenceu = $participanteBot !== null && (int) $participanteBot->user_id === $vencedorUserId;
        $humanoVenceu = $participanteHumano !== null && (int) $participanteHumano->user_id === $vencedorUserId;

        $slotBot = $participanteBot !== null ? (int) $participanteBot->player_slot : null;
        $perfil = $slotBot !== null
            ? app(SubstituteDifficultyResolver::class)->forMatch($match, $slotBot)
            : null;

        return [
            'bot_venceu' => $botVenceu,
            'humano_venceu' => $humanoVenceu,
            'bot_user_id' => $participanteBot?->user_id,
            'humano_user_id' => $participanteHumano?->user_id,
            'humano_ranked_points' => $participanteHumano?->user?->ranked_points,
            'bot_deck_id' => $participanteBot?->deck_id,
            'humano_deck_id' => $participanteHumano?->deck_id,
            'perfil_bot' => $perfil,
            'indicador_balanceamento' => $botVenceu
                ? 'bot_venceu'
                : ($humanoVenceu ? 'humano_venceu' : 'indefinido'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function analiseResultadoPvp(GameMatch $match, int $vencedorUserId): array
    {
        $vencedor = $match->players->firstWhere('user_id', $vencedorUserId);
        $perdedor = $match->players->first(fn ($participante) => (int) $participante->user_id !== $vencedorUserId);

        return [
            'vencedor_user_id' => $vencedorUserId,
            'vencedor_slot' => $vencedor?->player_slot,
            'vencedor_deck_id' => $vencedor?->deck_id,
            'vencedor_ranked_points' => $vencedor?->user?->ranked_points,
            'perdedor_user_id' => $perdedor?->user_id,
            'perdedor_slot' => $perdedor?->player_slot,
            'perdedor_deck_id' => $perdedor?->deck_id,
            'perdedor_ranked_points' => $perdedor?->user?->ranked_points,
        ];
    }

    /**
     * Resumo legível do estado (complementa estado_completo em análises rápidas).
     *
     * @param  array<string, mixed>  $estado
     * @return array<string, mixed>
     */
    private static function resumoEstado(array $estado): array
    {
        $resumo = [
            'turno_estado' => $estado['turno'] ?? null,
            'jogador_da_vez' => $estado['jogador_da_vez'] ?? null,
        ];

        foreach ([1, 2] as $slot) {
            $chaveJogador = (string) $slot;
            $dadosJogador = $estado['jogadores'][$chaveJogador] ?? [];
            $campo = $estado['campo'][$slot] ?? [];

            $resumo["slot_{$slot}"] = [
                'user_id' => $dadosJogador['user_id'] ?? null,
                'vida' => $dadosJogador['vida'] ?? null,
                'energia_atual' => $dadosJogador['energia_atual'] ?? null,
                'energia_maxima' => $dadosJogador['energia_maxima'] ?? null,
                'mao' => $dadosJogador['mao'] ?? [],
                'deck_restante' => count($dadosJogador['deck'] ?? []),
                'cemiterio' => $dadosJogador['cemiterio'] ?? [],
                'campo' => array_values(array_map(static fn ($unidade) => [
                    'instancia_id' => $unidade['instancia_id'] ?? null,
                    'card_id' => $unidade['card_id'] ?? null,
                    'vida_atual' => $unidade['vida_atual'] ?? null,
                    'vida_max' => $unidade['vida_max'] ?? null,
                    'bonus_ataque' => $unidade['bonus_ataque'] ?? 0,
                    'bonus_ataque_turno' => $unidade['bonus_ataque_turno'] ?? 0,
                    'pode_atacar' => $unidade['pode_atacar'] ?? null,
                    'foi_invocado_neste_turno' => $unidade['foi_invocado_neste_turno'] ?? null,
                    'silenciado' => $unidade['silenciado'] ?? false,
                    'efeitos' => $unidade['efeitos'] ?? [],
                    'flags' => $unidade['flags'] ?? [],
                ], $campo)),
            ];
        }

        return $resumo;
    }
}
