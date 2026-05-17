<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_progression_applied', function (Blueprint $table) {
            $table->foreignId('match_id')->primary()->constrained('matches')->cascadeOnDelete();
            $table->timestamp('applied_at')->useCurrent();
        });

        Schema::create('player_weeklies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedSmallInteger('xp_earned')->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->json('offers')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'week_start']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->date('last_daily_win_bonus_date')->nullable()->after('session_version');
            $table->unsignedTinyInteger('premium_chest_pity')->default(0)->after('last_daily_win_bonus_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_daily_win_bonus_date', 'premium_chest_pity']);
        });
        Schema::dropIfExists('player_weeklies');
        Schema::dropIfExists('match_progression_applied');
    }
};
