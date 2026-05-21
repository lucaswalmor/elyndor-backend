<?php

namespace App\Services\Game;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class MatchViewBuilder
{
    public function forUser(GameMatch $match, User $user): array
    {
        if ($match->status === \App\Enums\MatchStatus::Aguardando) {
            return [
                'id' => $match->id,
                'status' => $match->status->value,
                'aguardando_aceite' => true,
            ];
        }

        $estado = $match->estado;
        $motor = app(MatchEngine::class);
        $motor->purgeExpiredRevelacoes($estado);
        if ($estado !== $match->estado) {
            $match->estado = $estado;
            $match->saveQuietly();
        }

        // Usa a coleção já carregada — sem disparar novas queries.
        // Requer: GameMatch::with('players.user')
        /** @var EloquentCollection $allPlayers */
        $allPlayers = $match->relationLoaded('players')
            ? $match->players
            : $match->players()->with('user')->get();

        $myRecord = $allPlayers->first(fn ($p) => $p->user_id === $user->id);
        $slot     = $myRecord?->player_slot;
        $opp      = $slot === 1 ? 2 : 1;

        $players = [];
        foreach ([1, 2] as $s) {
            $mp = $allPlayers->firstWhere('player_slot', $s);
            $p  = $estado['jogadores'][(string) $s];

            $row = [
                'user_id'           => $p['user_id'],
                'nome'              => $mp?->user?->nickname ?? 'Jogador',
                'vida'              => $p['vida'],
                'energia_atual'     => $p['energia_atual'],
                'energia_maxima'    => $p['energia_maxima'],
                'energia_reservada' => $p['energia_reservada'] ?? 0,
                'cartas_no_deck'    => count($p['deck']),
                'card_back_slug'    => $mp?->user?->card_back_slug ?? 'padrao',
                'match_board_slug'  => $mp?->user?->match_board_slug ?? 'padrao',
                'avatar_image_file' => $mp?->user?->avatar?->image_file,
            ];

            if ($s === $slot) {
                $row['cartas_na_mao'] = count($p['mao']);
                $row['mao']           = $this->hydrateHand($p['mao']);
                $row['pode_invocar']  = ! ($p['ja_atacou_neste_turno'] ?? false);
            } else {
                $row['cartas_na_mao'] = count($p['mao']);
            }

            $players[(string) $s] = $row;
        }

        return [
            'id'               => $match->id,
            'status'           => $match->status->value,
            'arena_match_board_slug' => $match->arena_match_board_slug,
            'vencedor_id'      => $match->vencedor_id,
            'turno'            => $estado['turno'],
            'jogador_da_vez'   => $estado['jogador_da_vez'],
            'meu_player_id'    => $slot,
            'turno_deadline_em'=> $match->turno_deadline_em?->toIso8601String(),
            'config_partida'   => $this->configPartidaParaCliente(),
            'jogadores'        => $players,
            'meu_campo'        => $this->hydrateField($estado['campo'][$slot], $estado, $slot),
            'campo_inimigo'    => $this->hydrateField($estado['campo'][$opp], $estado, $opp, false),
            'revelacoes'            => $this->hydrateRevelacoes($estado['revelacoes'][(string) $slot] ?? []),
            'revelacoes_expira_em'  => $estado['revelacoes_expira_em'][(string) $slot] ?? null,
        ];
    }

    /**
     * Regras numéricas de partida para o cliente (espelha config/game/match.php).
     *
     * @return array<string, mixed>
     */
    private function configPartidaParaCliente(): array
    {
        $energia = config('game.match.energy', []);
        $timer = config('game.match.turn_timer', []);
        $campo = config('game.match.field', []);

        return [
            'energia' => [
                'inicio' => (int) ($energia['start'] ?? 1),
                'maxima' => (int) ($energia['max'] ?? 8),
                'ganho_por_turno' => (int) ($energia['gain_per_turn'] ?? 1),
            ],
            'timer_turno' => [
                'base_segundos' => (int) ($timer['base_seconds'] ?? 60),
                'incremento_por_turno' => (int) ($timer['increment_per_turn'] ?? 0),
                'max_segundos' => (int) ($timer['max_seconds'] ?? 60),
            ],
            'campo' => [
                'max_unidades' => (int) ($campo['max_units_per_player'] ?? 5),
                'max_mao' => (int) ($campo['max_hand_size'] ?? 7),
            ],
        ];
    }

    private function hydrateRevelacoes(array $revelacoes): array
    {
        return array_map(function ($cardId) {
            $card = CardCatalog::get($cardId);

            return [
                'card_id'     => $cardId,
                'nome'        => $card?->nome,
                'descricao'   => $card?->descricao,
                'custo'       => $card?->custo,
                'ataque'      => $card?->ataque,
                'vida'        => $card?->vida,
                'linhagem'      => $card?->linhagem,
                'imagem_path' => $card?->imagem_path,
                'skills'      => array_map(fn ($s) => (array) $s, $card?->skills ?? []),
            ];
        }, $revelacoes);
    }

    private function hydrateHand(array $mao): array
    {
        return array_map(function ($c) {
            $card = CardCatalog::get($c['card_id']);

            return array_merge($c, [
                'nome'        => $card?->nome,
                'descricao'   => $card?->descricao,
                'custo'       => $card?->custo,
                'ataque'      => $card?->ataque,
                'vida'        => $card?->vida,
                'linhagem'      => $card?->linhagem,
                'imagem_path' => $card?->imagem_path,
                'skills'      => array_map(fn ($s) => (array) $s, $card?->skills ?? []),
            ]);
        }, $mao);
    }

    private function hydrateField(array $units, array $estado, int $slot, bool $full = true): array
    {
        $engine = app(MatchEngine::class);

        return array_map(function ($u) use ($engine, $estado, $slot, $full) {
            $card = CardCatalog::get($u['card_id']);
            $atk  = $engine->getUnitAttack($estado, $slot, $u);

            // Informações da carta são sempre públicas no campo (visible to both players)
            $row = [
                'instancia_id'             => $u['instancia_id'],
                'card_id'                  => $u['card_id'],
                'vida_atual'               => $u['vida_atual'],
                'ataque'                   => $atk,
                'pode_atacar'              => $u['pode_atacar'] ?? false,
                'foi_invocado_neste_turno' => $u['foi_invocado_neste_turno'] ?? false,
                'efeitos'                  => array_column($u['efeitos'] ?? [], 'tipo'),
                'nome'                     => $card?->nome,
                'imagem_path'              => $card?->imagem_path,
                'descricao'                => $card?->descricao,
                'linhagem'                   => $card?->linhagem,
                'skills'                   => array_map(fn ($s) => (array) $s, $card?->skills ?? []),
            ];

            if ($full) {
                $row['flags'] = array_keys(array_filter($u['flags'] ?? []));
            }

            return $row;
        }, $units);
    }
}
