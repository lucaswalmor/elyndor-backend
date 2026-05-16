<?php

namespace App\Services\Game;

use App\Enums\MatchStatus;
use App\Models\Deck;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use Illuminate\Support\Str;

class MatchInitializer
{
    public function start(GameMatch $match, MatchPlayer $p1, MatchPlayer $p2): void
    {
        $deck1 = $this->buildDeckList(Deck::with('deckCards')->find($p1->deck_id));
        $deck2 = $this->buildDeckList(Deck::with('deckCards')->find($p2->deck_id));

        shuffle($deck1);
        shuffle($deck2);

        $hand1 = $this->draw($deck1, 3);
        $hand2 = $this->draw($deck2, 4);

        $estado = [
            'turno' => 1,
            'jogador_da_vez' => 1,
            'jogadores' => [
                '1' => $this->playerBlock($p1, $hand1, $deck1),
                '2' => $this->playerBlock($p2, $hand2, $deck2),
            ],
            'campo' => ['1' => [], '2' => []],
            'ultimo_aliado_morto' => ['1' => null, '2' => null],
            'revelacoes' => ['1' => [], '2' => []],
        ];

        $match->update([
            'status' => MatchStatus::EmAndamento,
            'turno' => 1,
            'jogador_da_vez' => 1,
            'estado' => $estado,
            'iniciada_em' => now(),
        ]);

        app(MatchEngine::class)->refreshTurnDeadline($match);
    }

    private function playerBlock(MatchPlayer $mp, array $mao, array $deck): array
    {
        return [
            'user_id' => $mp->user_id,
            'vida' => 20,
            'energia_atual' => config('game.match.energy.start', 3),
            'energia_maxima' => config('game.match.energy.start', 3),
            'energia_reservada' => 0,
            'energia_bonus_turno' => 0,
            'mao' => $mao,
            'deck' => $deck,
            'cemiterio' => [],
            'ressurreicao_usada' => false,
        ];
    }

    private function buildDeckList(?Deck $deck): array
    {
        if (! $deck) {
            return [];
        }

        $list = [];
        foreach ($deck->deckCards as $dc) {
            for ($i = 0; $i < $dc->quantidade; $i++) {
                $list[] = $dc->card_id;
            }
        }

        return $list;
    }

    private function draw(array &$deck, int $count): array
    {
        $hand = [];
        for ($i = 0; $i < $count; $i++) {
            if (count($deck) === 0) {
                break;
            }
            $cardId = array_shift($deck);
            $hand[] = [
                'instancia_id' => (string) Str::uuid(),
                'card_id' => $cardId,
            ];
        }

        return $hand;
    }
}
