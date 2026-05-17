<?php

namespace App\Services\Game;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class MatchViewBuilder
{
    public function forUser(GameMatch $match, User $user): array
    {
        $estado = $match->estado;

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
            ];

            if ($s === $slot) {
                $row['cartas_na_mao'] = count($p['mao']);
                $row['mao']           = $this->hydrateHand($p['mao']);
            } else {
                $row['cartas_na_mao'] = count($p['mao']);
            }

            $players[(string) $s] = $row;
        }

        return [
            'id'               => $match->id,
            'status'           => $match->status->value,
            'vencedor_id'      => $match->vencedor_id,
            'turno'            => $estado['turno'],
            'jogador_da_vez'   => $estado['jogador_da_vez'],
            'meu_player_id'    => $slot,
            'turno_deadline_em'=> $match->turno_deadline_em?->toIso8601String(),
            'jogadores'        => $players,
            'meu_campo'        => $this->hydrateField($estado['campo'][$slot]),
            'campo_inimigo'    => $this->hydrateField($estado['campo'][$opp], false),
            'revelacoes'       => $estado['revelacoes'][(string) $slot] ?? [],
        ];
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
                'faccao'      => $card?->faccao,
                'imagem_path' => $card?->imagem_path,
                'skills'      => array_map(fn ($s) => (array) $s, $card?->skills ?? []),
            ]);
        }, $mao);
    }

    private function hydrateField(array $units, bool $full = true): array
    {
        return array_map(function ($u) use ($full) {
            $card = CardCatalog::get($u['card_id']);
            $atk  = ($card?->ataque ?? 0) + ($u['bonus_ataque'] ?? 0);

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
                'faccao'                   => $card?->faccao,
                'skills'                   => array_map(fn ($s) => (array) $s, $card?->skills ?? []),
            ];

            if ($full) {
                $row['flags'] = array_keys(array_filter($u['flags'] ?? []));
            }

            return $row;
        }, $units);
    }
}
