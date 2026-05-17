<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('accept_deadline_at')->nullable()->after('status');
        });

        Schema::table('match_players', function (Blueprint $table) {
            $table->timestamp('match_accepted_at')->nullable()->after('player_slot');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('accept_deadline_at');
        });

        Schema::table('match_players', function (Blueprint $table) {
            $table->dropColumn('match_accepted_at');
        });
    }
};
