<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chests', function (Blueprint $table) {
            if (Schema::hasColumn('chests', 'legacy_open_kind')) {
                $table->dropUnique('chests_legacy_open_kind_unique');
            }
        });
        Schema::table('chests', function (Blueprint $table) {
            if (Schema::hasColumn('chests', 'legacy_open_kind')) {
                $table->dropColumn('legacy_open_kind');
            }
        });

        Schema::table('chests', function (Blueprint $table) {
            $table->unsignedTinyInteger('pity_epic_every')->nullable()->after('sort_order')->comment(
                'Se não nulo, pity de épica+ (incremento em aberturas sem épica/lendária; ao atingir o limite o próximo sorteio só considera linhas card com raridade épica ou lendária). Só faz sentido em baús com itens categoria `card`.'
            );
        });
    }

    public function down(): void
    {
        Schema::table('chests', function (Blueprint $table) {
            $table->dropColumn('pity_epic_every');
        });
        Schema::table('chests', function (Blueprint $table) {
            $table->string('legacy_open_kind', 32)
                ->nullable()
                ->after('sort_order')
                ->comment('Legado removido; recriado só para rollback.');
            $table->unique('legacy_open_kind', 'chests_legacy_open_kind_unique');
        });
    }
};
