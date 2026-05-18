<?php

namespace Database\Seeders;

use App\Models\Avatar;
use App\Models\PlayerAvatar;
use App\Models\User;
use Illuminate\Database\Seeder;

class AvataresSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['file' => 'avatar_creature_chimera.png', 'label' => 'Quimera', 'slug' => 'creature_chimera'],
            ['file' => 'avatar_creature_dragon.png', 'label' => 'Dragão', 'slug' => 'creature_dragon'],
            ['file' => 'avatar_creature_golem.png', 'label' => 'Golem', 'slug' => 'creature_golem'],
            ['file' => 'avatar_creature_phoenix.png', 'label' => 'Fénix', 'slug' => 'creature_phoenix'],
            ['file' => 'avatar_creature_wraith.png', 'label' => 'Espectro', 'slug' => 'creature_wraith'],
            ['file' => 'avatar_element_earth.png', 'label' => 'Terra', 'slug' => 'element_earth'],
            ['file' => 'avatar_element_fire.png', 'label' => 'Fogo', 'slug' => 'element_fire'],
            ['file' => 'avatar_element_ice.png', 'label' => 'Gelo', 'slug' => 'element_ice'],
            ['file' => 'avatar_element_lightning.png', 'label' => 'Relâmpago', 'slug' => 'element_lightning'],
            ['file' => 'avatar_element_void.png', 'label' => 'Vazio', 'slug' => 'element_void'],
            ['file' => 'avatar_faction_celestial.png', 'label' => 'Celestial', 'slug' => 'faction_celestial'],
            ['file' => 'avatar_faction_infernal.png', 'label' => 'Infernal', 'slug' => 'faction_infernal'],
            ['file' => 'avatar_faction_mechanics.png', 'label' => 'Mecânico', 'slug' => 'faction_mechanics'],
            ['file' => 'avatar_faction_nature.png', 'label' => 'Natureza', 'slug' => 'faction_nature'],
            ['file' => 'avatar_faction_undead.png', 'label' => 'Morto-vivo', 'slug' => 'faction_undead'],
            ['file' => 'avatar_premium_crystal.png', 'label' => 'Cristal', 'slug' => 'premium_crystal'],
            ['file' => 'avatar_premium_gold.png', 'label' => 'Ouro radiante', 'slug' => 'premium_gold'],
            ['file' => 'avatar_premium_heavenly.png', 'label' => 'Céu dourado', 'slug' => 'premium_heavenly'],
            ['file' => 'avatar_premium_jade.png', 'label' => 'Jade', 'slug' => 'premium_jade'],
            ['file' => 'avatar_premium_obsidian.png', 'label' => 'Obsidiana', 'slug' => 'premium_obsidian'],
            ['file' => 'avatar_rank_bronze.png', 'label' => 'Bronze', 'slug' => 'rank_bronze'],
            ['file' => 'avatar_rank_diamond.png', 'label' => 'Diamante', 'slug' => 'rank_diamond'],
            ['file' => 'avatar_rank_gold.png', 'label' => 'Liga Ouro', 'slug' => 'rank_gold'],
            ['file' => 'avatar_rank_platinum.png', 'label' => 'Platina', 'slug' => 'rank_platinum'],
            ['file' => 'avatar_rank_silver.png', 'label' => 'Prata', 'slug' => 'rank_silver'],
        ];

        foreach ($definitions as $i => $d) {
            Avatar::query()->updateOrCreate(
                ['slug' => $d['slug']],
                [
                    'label' => $d['label'],
                    'image_file' => $d['file'],
                    'sort_order' => $i,
                    'is_starter' => true,
                ]
            );
        }

        Avatar::query()->whereNull('image_file')->update(['is_starter' => false]);

        $default = Avatar::query()->where('slug', 'creature_chimera')->first()
            ?? Avatar::query()->whereNotNull('image_file')->orderBy('sort_order')->first();

        if ($default) {
            $validIds = Avatar::query()->whereNotNull('image_file')->pluck('id')->all();
            User::query()
                ->where(function ($q) use ($validIds) {
                    $q->whereNull('avatar_id');
                    if ($validIds !== []) {
                        $q->orWhereNotIn('avatar_id', $validIds);
                    }
                })
                ->update(['avatar_id' => $default->id]);
        }

        foreach (User::query()->get() as $user) {
            foreach (Avatar::query()->whereNotNull('image_file')->pluck('id') as $aid) {
                PlayerAvatar::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'avatar_id' => $aid,
                ]);
            }
        }

        // Remove avatares antigos (ex.: sentinela, vigia) sem PNG — ficavam na BD e na grelha só com letra.
        $staleIds = Avatar::query()->whereNull('image_file')->pluck('id')->all();
        if ($staleIds !== [] && $default) {
            User::query()->whereIn('avatar_id', $staleIds)->update(['avatar_id' => $default->id]);
            PlayerAvatar::query()->whereIn('avatar_id', $staleIds)->delete();
            Avatar::query()->whereIn('id', $staleIds)->delete();
        }
    }
}
