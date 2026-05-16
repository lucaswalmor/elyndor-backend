<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\CardSkill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CardSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('../roadmap/cards_seed.json');
        if (! File::exists($path)) {
            $path = base_path('roadmap/cards_seed.json');
        }

        $data = json_decode(File::get($path), true);
        $cartas = $data['cartas'] ?? [];

        foreach ($cartas as $c) {
            $card = Card::updateOrCreate(
                ['slug' => $c['slug']],
                [
                    'nome' => $c['nome'],
                    'descricao' => $c['descricao'] ?? null,
                    'faccao' => $c['faccao'],
                    'classe' => $c['classe'] ?? null,
                    'raridade' => $c['raridade'],
                    'tipo' => $c['tipo'] ?? 'unidade',
                    'custo' => $c['custo'],
                    'ataque' => $c['ataque'] ?? 0,
                    'vida' => $c['vida'] ?? 0,
                    'imagem' => $c['imagem'] ?? $c['slug'],
                    'imagem_path' => $c['imagem_path'] ?? null,
                    'ativo' => true,
                    'colecionavel' => true,
                ]
            );

            $card->skills()->delete();

            foreach ($c['habilidades'] ?? [] as $h) {
                CardSkill::create([
                    'card_id' => $card->id,
                    'nome' => $h['nome'],
                    'tipo' => $h['tipo'],
                    'gatilho' => $h['gatilho'] ?? null,
                    'efeito' => $h['efeito'],
                ]);
            }
        }
    }
}
