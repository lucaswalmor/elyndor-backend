<?php

namespace Database\Seeders;

use App\Enums\Raridade;
use App\Models\Avatar;
use App\Models\Card;
use App\Models\Deck;
use App\Models\DeckCard;
use App\Models\PlayerLevel;
use App\Models\User;
use App\Services\Collection\PlayerCollectionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Contas internas ranqueadas (substitutos). Nunca listadas no leaderboard.
 * Em produção: `php artisan db:seed --class=ContasSubstitutasRanqueadaSeeder` após migrações.
 */
class ContasSubstitutasRanqueadaSeeder extends Seeder
{
    public function run(): void
    {
        $collection = app(PlayerCollectionService::class);

        $divisions = config('game.ranked.divisions', []);

        $nicknames = [
            'ferro' => 'Kaelthorne',
            'bronze' => 'Myrrine',
            'prata' => 'Orros',
            'ouro' => 'Serathe',
            'platina' => 'Valdris',
            'diamante' => 'Ilythea',
            'mestre' => 'Aurekan',
        ];

        $avatarId = Avatar::query()->where('slug', 'creature_chimera')->value('id')
            ?? Avatar::query()->whereNotNull('image_file')->orderBy('sort_order')->value('id');

        foreach ($divisions as $def) {
            $key = $def['key'];
            $email = "rank_substitute_{$key}@bots.elyndor.local";
            $nickname = $nicknames[$key] ?? ucfirst($key).'Opponent';

            $midRank = isset($def['max']) && $def['max'] !== null
                ? (int) floor(($def['min'] + $def['max']) / 2)
                : ((int) $def['min'] + 200);

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $nickname,
                    'nickname' => $nickname,
                    'password' => Hash::make(bin2hex(random_bytes(24))),
                    'moedas' => 0,
                    'cristais' => 0,
                    'ranked_points' => $midRank,
                    'ranked_wins' => 0,
                    'ranked_losses' => 0,
                    'avatar_id' => $avatarId,
                    'registration_device_id' => null,
                ]
            );

            $user->is_bot = true;
            $user->nickname = $nickname;
            $user->ranked_points = $midRank;
            if ($avatarId) {
                $user->avatar_id = $avatarId;
            }
            $user->save();

            PlayerLevel::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['nivel' => $this->botLevelForDivision($key), 'xp_atual' => 0]
            );

            $deck = Deck::query()->firstOrCreate(
                ['user_id' => $user->id, 'is_padrao' => true],
                ['nome' => 'Deck Elyndor']
            );
            $deck->nome = 'Deck Elyndor';
            $deck->is_padrao = true;
            $deck->save();
            DeckCard::query()->where('deck_id', $deck->id)->delete();

            $picked = $this->pickDeckForDivision($key);

            $grant = [];
            foreach ($picked as $cardId) {
                DeckCard::create([
                    'deck_id' => $deck->id,
                    'card_id' => $cardId,
                    'quantidade' => 1,
                ]);
                $grant[$cardId] = 1;
            }

            $collection->grant($user, $grant);
        }
    }

    private function botLevelForDivision(string $division): int
    {
        return match ($division) {
            'ferro' => 25,
            'bronze' => 32,
            'prata' => 40,
            'ouro' => 50,
            'platina' => 62,
            'diamante' => 75,
            'mestre' => 90,
            default => max(40, (int) config('game.ranked.min_level', 20) + 5),
        };
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function pickDeckForDivision(string $division): \Illuminate\Support\Collection
    {
        $size = (int) config('game.progression.decks.size', 20);
        $spellLimit = (int) config('game.progression.decks.max_spells', 5);
        $plan = match ($division) {
            'ferro' => ['comum' => 17, 'rara' => 3, 'epica' => 0, 'lendaria' => 0, 'spells' => 1],
            'bronze' => ['comum' => 15, 'rara' => 4, 'epica' => 1, 'lendaria' => 0, 'spells' => 2],
            'prata' => ['comum' => 13, 'rara' => 5, 'epica' => 2, 'lendaria' => 0, 'spells' => 3],
            'ouro' => ['comum' => 12, 'rara' => 5, 'epica' => 2, 'lendaria' => 1, 'spells' => 3],
            'platina' => ['comum' => 11, 'rara' => 5, 'epica' => 3, 'lendaria' => 1, 'spells' => 4],
            'diamante', 'mestre' => ['comum' => 10, 'rara' => 6, 'epica' => 3, 'lendaria' => 1, 'spells' => 5],
            default => ['comum' => 16, 'rara' => 3, 'epica' => 1, 'lendaria' => 0, 'spells' => 2],
        };
        $plan['spells'] = min($spellLimit, (int) $plan['spells']);

        $picked = collect();
        $spellIds = Card::query()
            ->where('tipo', 'spell')
            ->where('ativo', true)
            ->inRandomOrder()
            ->limit($plan['spells'])
            ->pluck('id');
        $picked = $picked->merge($spellIds);

        foreach ([Raridade::Lendaria, Raridade::Epica, Raridade::Rara, Raridade::Comum] as $raridade) {
            $target = (int) ($plan[$raridade->value] ?? 0);
            if ($target <= 0) {
                continue;
            }
            $picked = $picked->merge(
                Card::query()
                    ->where('tipo', 'unit')
                    ->where('ativo', true)
                    ->where('raridade', $raridade->value)
                    ->inRandomOrder()
                    ->limit($target)
                    ->pluck('id')
            );
        }

        if ($picked->count() < $size) {
            $picked = $picked->merge(
                Card::query()
                    ->where('tipo', 'unit')
                    ->where('ativo', true)
                    ->whereNotIn('id', $picked->all())
                    ->inRandomOrder()
                    ->limit($size - $picked->count())
                    ->pluck('id')
            );
        }

        return $picked->unique()->take($size)->values();
    }
}
