<?php

namespace App\Console\Commands;

use App\Models\Deck;
use App\Models\User;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Console\Command;

class FixColecaoTreino extends Command
{
    protected $signature   = 'fix:colecao-treino';
    protected $description = 'Garante player_cards para usuários com deck de treino sem coleção';

    public function handle(OnboardingService $onboarding): void
    {
        $nomeDeck = config('game.tutorial.deck_treino_nome', 'Deck de Treino');

        // Usuários que têm o deck de treino mas onboarding ainda não concluído
        $users = User::whereHas('decks', fn ($q) => $q->where('nome', $nomeDeck))
            ->whereNull('onboarding_deck_escolhido_em')
            ->get();

        $this->info("Encontrados: {$users->count()} usuários para corrigir.");

        foreach ($users as $user) {
            try {
                $onboarding->registrarColecaoTreino($user);
                $this->info("✅ Corrigido: [{$user->id}] {$user->name}");
            } catch (\Throwable $e) {
                $this->error("❌ Erro no usuário {$user->id}: {$e->getMessage()}");
            }
        }

        $this->info('Concluído.');
    }
}