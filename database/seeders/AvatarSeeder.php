<?php

namespace Database\Seeders;

use App\Models\Avatar;
use App\Models\User;
use App\Models\PlayerAvatar;
use Illuminate\Database\Seeder;

class AvatarSeeder extends Seeder
{
    public function run(): void
    {
        $starters = [
            ['slug' => 'sentinela', 'label' => 'Sentinela'],
            ['slug' => 'vigia', 'label' => 'Vigia'],
            ['slug' => 'erudito', 'label' => 'Erudito'],
            ['slug' => 'cacador', 'label' => 'Caçador'],
            ['slug' => 'oraculo', 'label' => 'Oráculo'],
            ['slug' => 'capitao', 'label' => 'Capitão'],
            ['slug' => 'forjador', 'label' => 'Forjador'],
            ['slug' => 'viajante', 'label' => 'Viajante'],
            ['slug' => 'guardiao', 'label' => 'Guardião'],
            ['slug' => 'estrategista', 'label' => 'Estrategista'],
        ];

        foreach ($starters as $i => $s) {
            Avatar::query()->updateOrCreate(
                ['slug' => $s['slug']],
                ['label' => $s['label'], 'sort_order' => $i, 'is_starter' => true]
            );
        }

        $extra = [
            'sombras', 'aurora', 'templario', 'arcanista', 'devoto',
            'vingar', 'harmonia', 'tempestade', 'eclipse', 'cometa',
            'renascido', 'titã', 'vórtice', 'coroa', 'abismo',
        ];
        foreach ($extra as $i => $slug) {
            Avatar::query()->updateOrCreate(
                ['slug' => $slug],
                ['label' => ucfirst($slug), 'sort_order' => 100 + $i, 'is_starter' => false]
            );
        }

        $firstStarter = Avatar::query()->where('is_starter', true)->orderBy('sort_order')->first();
        if ($firstStarter) {
            User::query()->whereNull('avatar_id')->update(['avatar_id' => $firstStarter->id]);
        }

        foreach (User::query()->get() as $user) {
            foreach (Avatar::query()->where('is_starter', true)->pluck('id') as $aid) {
                PlayerAvatar::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'avatar_id' => $aid,
                ]);
            }
        }
    }
}
