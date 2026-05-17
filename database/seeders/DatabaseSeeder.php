<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CardSeeder::class,
            AvatarSeeder::class,
            ChestAndWeeklyPoolSeeder::class,
            RankedSubstituteAccountsSeeder::class,
        ]);
    }
}
