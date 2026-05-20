<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CartasSeeder::class,
            AvataresSeeder::class,
            BausPoolsSemanalSeeder::class,
            ContasSubstitutasRanqueadaSeeder::class,
            ContaSubstitutaCasualSeeder::class,
            ProjectVersionSeeder::class,
        ]);
    }
}
