<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_player_stats_applied', function (Blueprint $table) {
            $table->foreignId('match_id')->primary()->constrained('matches')->cascadeOnDelete();
            $table->timestamp('applied_at')->useCurrent();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('total_matches_played')->default(0)->after('ranked_losses');
            $table->json('match_mode_counts')->nullable()->after('total_matches_played');
            $table->unsignedBigInteger('playtime_seconds')->default(0)->after('match_mode_counts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['total_matches_played', 'match_mode_counts', 'playtime_seconds']);
        });

        Schema::dropIfExists('match_player_stats_applied');
    }
};
