<?php

namespace App\Services\Bot;

use App\Models\GameMatch;
use App\Services\Game\CardCatalog;
use App\Services\Game\EffectResolver;

/**
 * Heurísticas simples para substitutos na ranqueada (sem ler o cliente).
 */
class RankedSubstituteBrain
{
    public function __construct(
        private EffectResolver $effects,
    ) {}

    /**
     * @return array<string, mixed>|null null = não deve continuar este job
     */
    public function nextPayload(GameMatch $match, int $botSlot): ?array
    {
        $estado = $match->estado ?? null;
        if (! is_array($estado)) {
            return null;
        }

        $oppSlot = $botSlot === 1 ? 2 : 1;
        $turnSlot = (int) ($estado['jogador_da_vez'] ?? $botSlot);
        if ($turnSlot !== $botSlot) {
            return null;
        }

        foreach ($this->shuffleList($estado['campo'][$botSlot] ?? []) as $unit) {
            if (! ($unit['pode_atacar'] ?? false) || ($unit['foi_invocado_neste_turno'] ?? false)) {
                continue;
            }

            $attId = $unit['instancia_id'];
            $cardId = (int) ($unit['card_id'] ?? 0);

            $oppUnits = collect($estado['campo'][$oppSlot] ?? []);
            if ($oppUnits->isEmpty()) {
                if ($cardId && $this->effects->hasPassive($cardId, 'ataque_direto_jogador')) {
                    return ['acao' => 'atacar_jogador', 'instancia_id' => $attId];
                }

                continue;
            }

            $tauntIds = $this->strictTauntInstanceIds($estado, $oppSlot);
            $candidates = $oppUnits->values()->all();

            if ($tauntIds !== []) {
                $candidates = array_values(array_filter(
                    $candidates,
                    fn ($u) => in_array((string) $u['instancia_id'], $tauntIds, true)
                ));
                if ($candidates === []) {
                    continue;
                }
            }

            $targets = collect($candidates)->shuffle()->all();

            foreach ($targets as $defender) {
                return [
                    'acao' => 'atacar_unidade',
                    'instancia_id' => $attId,
                    'alvo_instancia_id' => $defender['instancia_id'],
                ];
            }
        }

        $fieldMax = (int) config('game.match.field.max_units_per_player', 5);
        $campoCount = count($estado['campo'][$botSlot] ?? []);

        if ($campoCount < $fieldMax) {
            $energy = (int) (($estado['jogadores'][(string) $botSlot]['energia_atual'] ?? 0));
            $hands = collect($estado['jogadores'][(string) $botSlot]['mao'] ?? [])
                ->map(function ($handCard) use ($energy) {
                    $cardId = (int) ($handCard['card_id'] ?? 0);
                    $catalog = CardCatalog::get($cardId);
                    if (! $catalog) {
                        return null;
                    }
                    $cost = (int) ($catalog->custo ?? 999);
                    if ($cost > $energy) {
                        return null;
                    }

                    return [
                        'instancia_id' => (string) $handCard['instancia_id'],
                        'card_id' => $cardId,
                        'custo' => $cost,
                    ];
                })
                ->filter()
                ->sortBy(fn ($row) => $row['custo'])
                ->values()
                ->all();

            if ($hands !== []) {
                shuffle($hands);
                $chosen = collect($hands)->sortBy(fn ($h) => $h['custo'])->first();

                return ['acao' => 'invocar', 'instancia_id' => $chosen['instancia_id']];
            }
        }

        return ['acao' => 'finalizar_turno'];
    }

    /** @param  array<int|string, mixed>  $units */
    private function shuffleList(array $units): array
    {
        $list = collect($units)->values()->all();
        shuffle($list);

        return $list;
    }

    /**
     * @return list<string>
     */
    private function strictTauntInstanceIds(array $estado, int $oppSlot): array
    {
        $ids = [];
        foreach ($estado['campo'][$oppSlot] ?? [] as $u) {
            $sil = (bool) ($u['silenciado'] ?? false);
            if ($sil || ! ($u['flags']['taunt_self'] ?? false)) {
                continue;
            }
            $ids[] = (string) $u['instancia_id'];
        }

        return $ids;
    }
}
