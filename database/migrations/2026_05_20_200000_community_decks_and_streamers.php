<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_content_creator')->default(false)->after('is_bot');
            $table->string('streamer_invite_token', 64)->nullable()->unique()->after('is_content_creator');
            $table->string('streamer_invite_claim', 120)->nullable()->after('streamer_invite_token');
        });

        Schema::create('streamer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('youtube_url', 500)->nullable();
            $table->string('instagram_url', 500)->nullable();
            $table->string('whatsapp_group_url', 500)->nullable();
            $table->string('twitch_url', 500)->nullable();
            $table->string('other_url', 500)->nullable();
            $table->string('bio', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('community_decks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_deck_id')->nullable()->constrained('decks')->nullOnDelete();
            $table->string('nome', 80);
            $table->string('descricao', 500)->nullable();
            $table->string('faccao_principal', 40);
            $table->string('game_version', 32);
            $table->string('ely_code', 24)->unique();
            $table->boolean('is_streamer_deck')->default(false);
            $table->json('tags')->nullable();
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('copies_count')->default(0);
            $table->timestamp('published_at');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['published_at', 'deleted_at']);
            $table->index(['is_streamer_deck', 'published_at']);
            $table->index(['faccao_principal', 'published_at']);
        });

        Schema::create('community_deck_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_deck_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('quantidade');
            $table->unique(['community_deck_id', 'card_id']);
        });

        Schema::create('community_deck_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_deck_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'community_deck_id']);
        });

        Schema::create('community_deck_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_deck_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->unique(['user_id', 'community_deck_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_deck_views');
        Schema::dropIfExists('community_deck_likes');
        Schema::dropIfExists('community_deck_cards');
        Schema::dropIfExists('community_decks');
        Schema::dropIfExists('streamer_profiles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_content_creator', 'streamer_invite_token', 'streamer_invite_claim']);
        });
    }
};
