<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avatars', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('label', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_starter')->default(false);
            $table->timestamps();
        });

        Schema::create('player_avatars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('avatar_id')->constrained('avatars')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'avatar_id']);
        });

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_id', 80)->nullable();
            $table->string('client_type', 20)->default('web');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ranked_match_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('venceu');
            $table->integer('delta');
            $table->unsignedInteger('pontos_antes');
            $table->unsignedInteger('pontos_depois');
            $table->string('divisao_oponente', 20)->nullable();
            $table->timestamps();
            $table->unique(['match_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('ranked_points')->default(0)->after('card_back_slug');
            $table->unsignedInteger('ranked_wins')->default(0)->after('ranked_points');
            $table->unsignedInteger('ranked_losses')->default(0)->after('ranked_wins');
            $table->foreignId('avatar_id')->nullable()->after('ranked_losses')->constrained('avatars')->nullOnDelete();
            $table->string('profile_bg_slug', 50)->default('padrao')->after('avatar_id');
            $table->string('registration_device_id', 80)->nullable()->after('profile_bg_slug');
            $table->unsignedBigInteger('session_version')->default(0)->after('registration_device_id');
        });

        Schema::table('matchmaking_queue', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('divisao');
            $table->string('device_id', 80)->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('matchmaking_queue', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'device_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['avatar_id']);
            $table->dropColumn([
                'ranked_points', 'ranked_wins', 'ranked_losses',
                'avatar_id', 'profile_bg_slug', 'registration_device_id', 'session_version',
            ]);
        });

        Schema::dropIfExists('ranked_match_outcomes');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('player_avatars');
        Schema::dropIfExists('avatars');
    }
};
