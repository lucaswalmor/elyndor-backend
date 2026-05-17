<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('cards')
            ->where('slug', 'aberracao-do-vazio')
            ->update(['imagem_path' => 'celestiais/aberracao_do_vazio_card_fixed.png']);

        DB::table('cards')
            ->where('slug', 'devorador-de-estrelas')
            ->update(['imagem_path' => 'celestiais/devorador_de_estrelas_card_fixed.png']);
    }

    public function down(): void
    {
        DB::table('cards')
            ->where('slug', 'aberracao-do-vazio')
            ->update(['imagem_path' => 'celestiais/aberracao_do_vazio.png']);

        DB::table('cards')
            ->where('slug', 'devorador-de-estrelas')
            ->update(['imagem_path' => 'celestiais/devorador_de_estrelas.png']);
    }
};
