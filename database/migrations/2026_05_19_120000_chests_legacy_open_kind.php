<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chests', function (Blueprint $table) {
            $table->string('legacy_open_kind', 32)
                ->nullable()
                ->after('sort_order')
                ->comment('Se preenchido, abertura no inventário usa a lógica antiga de cartas (cristal_basico / premium_padrao), sem chest_items.');
        });

        Schema::table('chests', function (Blueprint $table) {
            $table->unique('legacy_open_kind', 'chests_legacy_open_kind_unique');
        });
    }

    public function down(): void
    {
        Schema::table('chests', function (Blueprint $table) {
            $table->dropUnique('chests_legacy_open_kind_unique');
        });
        Schema::table('chests', function (Blueprint $table) {
            $table->dropColumn('legacy_open_kind');
        });
    }
};
