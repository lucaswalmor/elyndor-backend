<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_deck_escolhido_em')->nullable()->after('registration_device_id');
            $table->timestamp('tutorial_concluido_em')->nullable()->after('onboarding_deck_escolhido_em');
            $table->timestamp('tutorial_pulado_em')->nullable()->after('tutorial_concluido_em');
            $table->timestamp('tutorial_recompensa_resgatada_em')->nullable()->after('tutorial_pulado_em');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'onboarding_deck_escolhido_em',
                'tutorial_concluido_em',
                'tutorial_pulado_em',
                'tutorial_recompensa_resgatada_em',
            ]);
        });
    }
};
