<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('moedas')->default(0)->after('password');
            $table->unsignedInteger('cristais')->default(0)->after('moedas');
            $table->string('card_back_slug', 50)->default('padrao')->after('cristais');
        });

        Schema::create('player_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('nivel')->default(1);
            $table->unsignedInteger('xp_atual')->default(0);
            $table->timestamps();
        });

        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->text('descricao')->nullable();
            $table->string('faccao', 50);
            $table->string('classe', 50)->nullable();
            $table->string('raridade', 50);
            $table->string('tipo', 50)->default('unidade');
            $table->unsignedTinyInteger('custo');
            $table->unsignedSmallInteger('ataque')->default(0);
            $table->unsignedSmallInteger('vida')->default(0);
            $table->string('imagem')->nullable();
            $table->string('imagem_path')->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('colecionavel')->default(true);
            $table->string('versao_balanceamento', 20)->default('1.0.0');
            $table->timestamps();
        });

        Schema::create('card_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('tipo', 50);
            $table->string('gatilho', 50)->nullable();
            $table->json('efeito');
            $table->timestamps();
        });

        Schema::create('decks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nome')->default('Deck Principal');
            $table->boolean('is_padrao')->default(true);
            $table->timestamps();
        });

        Schema::create('deck_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deck_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('quantidade')->default(1);
            $table->timestamps();
            $table->unique(['deck_id', 'card_id']);
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->string('modo', 20)->default('normal');
            $table->string('status', 20)->default('aguardando');
            $table->foreignId('vencedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('turno')->default(1);
            $table->unsignedTinyInteger('jogador_da_vez')->nullable();
            $table->json('estado')->nullable();
            $table->timestamp('turno_deadline_em')->nullable();
            $table->timestamp('iniciada_em')->nullable();
            $table->timestamp('finalizada_em')->nullable();
            $table->timestamps();
        });

        Schema::create('match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deck_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('player_slot');
            $table->unsignedSmallInteger('vida_inicial')->default(20);
            $table->unsignedSmallInteger('vida_final')->nullable();
            $table->boolean('is_bot')->default(false);
            $table->boolean('conectado')->default(true);
            $table->timestamp('desconectado_em')->nullable();
            $table->timestamp('reconectado_em')->nullable();
            $table->timestamps();
            $table->unique(['match_id', 'player_slot']);
            $table->unique(['match_id', 'user_id']);
        });

        Schema::create('match_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedInteger('turno');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('acao', 50);
            $table->foreignId('card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('card_alvo_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->integer('dano_causado')->nullable();
            $table->integer('vida_antes')->nullable();
            $table->integer('vida_depois')->nullable();
            $table->string('efeito_tipo', 50)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('matchmaking_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('modo', 20)->default('normal');
            $table->foreignId('deck_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('nivel')->default(1);
            $table->integer('pontos_ranked')->default(0);
            $table->string('divisao', 20)->nullable();
            $table->timestamp('entrou_na_fila_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matchmaking_queue');
        Schema::dropIfExists('match_logs');
        Schema::dropIfExists('match_players');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('deck_cards');
        Schema::dropIfExists('decks');
        Schema::dropIfExists('card_skills');
        Schema::dropIfExists('cards');
        Schema::dropIfExists('player_levels');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['moedas', 'cristais', 'card_back_slug']);
        });
    }
};
