<?php

namespace App\Services\Onboarding;

use App\Enums\MatchStatus;
use App\Models\Deck;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Services\Bot\CasualSubstitutePairingService;
use App\Services\Game\MatchInitializer;
use App\Services\Game\MatchViewBuilder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TutorialMatchService
{
    public function iniciar(User $user, Deck $deckHumano): array
    {
        $partidaAtiva = GameMatch::query()
            ->where('modo', 'tutorial')
            ->where('status', MatchStatus::EmAndamento)
            ->whereHas('players', fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if ($partidaAtiva) {
            return [
                'match_id' => $partidaAtiva->id,
                'estado_completo' => app(MatchViewBuilder::class)->forUser($partidaAtiva, $user),
            ];
        }

        $bot = User::query()
            ->where('is_bot', true)
            ->where('email', CasualSubstitutePairingService::BOT_EMAIL)
            ->first();

        if (! $bot) {
            throw new InvalidArgumentException('Oponente de tutorial indisponível.');
        }

        $deckBotId = $bot->decks()->where('is_padrao', true)->value('id');
        if (! $deckBotId) {
            throw new InvalidArgumentException('Deck do oponente de tutorial indisponível.');
        }

        return DB::transaction(function () use ($user, $deckHumano, $bot, $deckBotId) {
            $match = GameMatch::create([
                'modo' => 'tutorial',
                'status' => MatchStatus::Aguardando,
            ]);

            $humano = MatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $user->id,
                'deck_id' => $deckHumano->id,
                'player_slot' => 1,
                'is_bot' => false,
            ]);

            $oponente = MatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $bot->id,
                'deck_id' => $deckBotId,
                'player_slot' => 2,
                'is_bot' => true,
            ]);

            app(MatchInitializer::class)->start($match, $humano, $oponente);

            $match->refresh();
            $estado = $match->estado;
            $segundos = (int) config('game.tutorial.turn_timer_seconds', 300);
            $match->update([
                'turno_deadline_em' => now()->addSeconds($segundos),
                'estado' => $this->aplicarCenarioTutorial($estado),
            ]);
            $match->refresh();

            return [
                'match_id' => $match->id,
                'estado_completo' => app(MatchViewBuilder::class)->forUser($match, $user),
            ];
        });
    }

    /** Cenário leve: oponente com uma unidade fraca para praticar ataque. */
    private function aplicarCenarioTutorial(array $estado): array
    {
        $campoOponente = $estado['campo']['2'] ?? [];
        if ($campoOponente === [] && ! empty($estado['jogadores']['2']['mao'])) {
            $primeiraMao = $estado['jogadores']['2']['mao'][0] ?? null;
            if ($primeiraMao) {
                $estado['campo']['2'][] = [
                    'instancia_id' => $primeiraMao['instancia_id'],
                    'card_id' => $primeiraMao['card_id'],
                    'vida_atual' => 2,
                    'vida_maxima' => 2,
                    'ataque_base' => 1,
                    'bonus_ataque' => 0,
                    'bonus_ataque_turno' => 0,
                    'foi_invocado_neste_turno' => true,
                    'pode_atacar' => false,
                    'efeitos' => [],
                    'flags' => [],
                ];
                $estado['jogadores']['2']['mao'] = array_slice($estado['jogadores']['2']['mao'], 1);
            }
        }

        return $estado;
    }
}
