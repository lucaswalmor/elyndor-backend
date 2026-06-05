<?php

namespace App\Services\Onboarding;

use App\Models\Card;
use App\Models\Chest;
use App\Models\Deck;
use App\Models\PlayerChestStack;
use App\Models\User;
use App\Services\Collection\PlayerCollectionService;
use App\Services\Deck\DeckService;
use App\Services\Onboarding\TutorialMatchService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OnboardingService
{
    public function __construct(
        private PlayerCollectionService $colecao,
        private DeckService $decks,
        private TutorialMatchService $partidaTutorial,
    ) {}

    public function status(User $user): array
    {
        return [
            'onboarding_deck_escolhido' => $user->onboarding_deck_escolhido_em !== null,
            'tutorial_concluido' => $user->tutorial_concluido_em !== null,
            'tutorial_pulado' => $user->tutorial_pulado_em !== null,
            'tutorial_recompensa_resgatada' => $user->tutorial_recompensa_resgatada_em !== null,
            'precisa_onboarding' => $this->precisaOnboarding($user),
            'pode_resgatar_recompensa' => $user->tutorial_concluido_em !== null
                && $user->tutorial_recompensa_resgatada_em === null,
            'presets' => $this->listarPresets(),
        ];
    }

    public function precisaOnboarding(User $user): bool
    {
        return $user->onboarding_deck_escolhido_em === null;
    }

    public function registrarColecaoTreino(User $user): void
    {
        $slugs = config('game.tutorial.slugs_colecao_treino', []);
        $grant = $this->mapaSlugsParaIds($slugs);
        if ($grant !== []) {
            $this->colecao->grant($user, $grant);
        }
    }

    // public function garantirDeckTreino(User $user): Deck
    // {
    //     $deck = Deck::query()
    //         ->where('user_id', $user->id)
    //         ->where('nome', config('game.tutorial.deck_treino_nome', 'Deck de Treino'))
    //         ->first();

    //     if ($deck) {
    //         return $deck;
    //     }

    //     $slugs = config('game.tutorial.slugs_colecao_treino', []);
    //     $cartas = array_map(fn (int $cardId) => ['card_id' => $cardId, 'quantidade' => 1], array_values($this->mapaSlugsParaIds($slugs)));

    //     $formatado = $this->decks->create(
    //         $user,
    //         config('game.tutorial.deck_treino_nome', 'Deck de Treino'),
    //         $cartas,
    //         true,
    //     );

    //     return Deck::query()->findOrFail($formatado['id']);
    // }

    public function garantirDeckTreino(User $user): Deck
    {
        $nomeDeck = config('game.tutorial.deck_treino_nome', 'Deck de Treino');
    
        $deck = Deck::query()
            ->where('user_id', $user->id)
            ->where('nome', $nomeDeck)
            ->first();
    
        if ($deck) {
            return $deck;
        }
    
        $slugs = config('game.tutorial.slugs_colecao_treino', []);
        $cartaIds = array_values($this->mapaSlugsParaIds($slugs));
    
        return DB::transaction(function () use ($user, $nomeDeck, $cartaIds, $slugs) {
            // ✅ ADICIONADO: garante player_cards antes de criar o deck
            $grant = $this->mapaSlugsParaIds($slugs);
            if ($grant !== []) {
                $this->colecao->grant($user, $grant);
            }
    
            $user->decks()->update(['is_padrao' => false]);
    
            $deck = Deck::create([
                'user_id'   => $user->id,
                'nome'      => $nomeDeck,
                'is_padrao' => true,
            ]);
    
            foreach ($cartaIds as $cardId) {
                \App\Models\DeckCard::firstOrCreate(
                    ['deck_id' => $deck->id, 'card_id' => $cardId],
                    ['quantidade' => 1]
                );
            }
    
            return $deck;
        });
    }

    public function pularTutorial(User $user): array
    {
        if ($user->onboarding_deck_escolhido_em !== null) {
            throw new InvalidArgumentException('Onboarding já concluído.');
        }

        $user->tutorial_pulado_em = now();
        $user->save();

        return $this->status($user->fresh());
    }

    public function marcarTutorialConcluido(User $user): array
    {
        if ($user->tutorial_concluido_em === null) {
            $user->tutorial_concluido_em = now();
            $user->save();
        }

        return $this->status($user->fresh());
    }

    public function iniciarPartidaTutorial(User $user): array
    {
        if ($user->onboarding_deck_escolhido_em !== null) {
            throw new InvalidArgumentException('Onboarding já concluído.');
        }

        $deck = $this->garantirDeckTreino($user);

        return $this->partidaTutorial->iniciar($user, $deck);
    }

    public function escolherDeckInicial(User $user, string $preset): array
    {
        $presets = config('game.starter_decks', []);
        if (! isset($presets[$preset])) {
            throw new InvalidArgumentException('Preset de deck inválido.');
        }

        return DB::transaction(function () use ($user, $preset, $presets) {
            $cfg = $presets[$preset];
            $slugs = $cfg['slugs'] ?? [];
            $grant = $this->mapaSlugsParaIds($slugs);
            $this->colecao->grant($user, $grant);

            Deck::query()->where('user_id', $user->id)->delete();

            $cartas = array_map(
                fn (int $cardId) => ['card_id' => $cardId, 'quantidade' => 1],
                array_values($grant),
            );

            $deckFormatado = $this->decks->create($user, 'Deck Inicial', $cartas, true);

            $user->onboarding_deck_escolhido_em = now();
            $user->save();

            return [
                'deck' => $deckFormatado,
                'status' => $this->status($user->fresh()),
            ];
        });
    }

    public function resgatarRecompensaTutorial(User $user): array
    {
        if ($user->tutorial_concluido_em === null) {
            throw new InvalidArgumentException('Conclua o tutorial antes de resgatar.');
        }

        if ($user->tutorial_recompensa_resgatada_em !== null) {
            throw new InvalidArgumentException('Recompensa já resgatada.');
        }

        return DB::transaction(function () use ($user) {
            $cristais = (int) config('game.tutorial.recompensa_cristais', 400);
            $slugBaú = (string) config('game.tutorial.chest_slug', 'chest_cristal_basico');

            $user->cristais = (int) ($user->cristais ?? 0) + $cristais;
            $user->tutorial_recompensa_resgatada_em = now();
            $user->save();

            $baú = Chest::query()->where('slug', $slugBaú)->where('active', true)->first();
            if (! $baú) {
                throw new InvalidArgumentException('Baú de recompensa indisponível.');
            }

            $pilha = PlayerChestStack::query()->firstOrCreate(
                ['user_id' => $user->id, 'chest_id' => $baú->id],
                ['quantity' => 0],
            );
            $pilha->increment('quantity');

            return [
                'cristais_ganhos' => $cristais,
                'chest' => [
                    'slug' => $baú->slug,
                    'name' => $baú->name,
                ],
                'status' => $this->status($user->fresh()),
            ];
        });
    }

    /** @return list<array<string, mixed>> */
    private function listarPresets(): array
    {
        $saida = [];
        foreach (config('game.starter_decks', []) as $chave => $cfg) {
            $saida[] = [
                'id' => $chave,
                'nome' => $cfg['nome'] ?? $chave,
                'arquetipo' => $cfg['arquetipo'] ?? '',
                'descricao' => $cfg['descricao'] ?? '',
                'cartas_chave' => $cfg['cartas_chave'] ?? [],
            ];
        }

        return $saida;
    }

    /** @param  list<string>  $slugs
     * @return array<int, int> card_id => quantidade */
    private function mapaSlugsParaIds(array $slugs): array
    {
        $ids = Card::query()->whereIn('slug', $slugs)->pluck('id', 'slug');
        $grant = [];
        foreach ($slugs as $slug) {
            $id = $ids[$slug] ?? null;
            if ($id) {
                $grant[(int) $id] = 1;
            }
        }

        if (count($grant) < count($slugs)) {
            throw new InvalidArgumentException('Algumas cartas do preset não existem no catálogo.');
        }

        return $grant;
    }
}
