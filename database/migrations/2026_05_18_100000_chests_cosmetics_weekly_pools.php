<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chests', function (Blueprint $table) {
            $table->id()->comment('Chave primária do baú.');
            $table->string('slug', 64)->unique()->comment('Identificador estável do baú (API, seeders, logs). Ex.: bau_cosmetico_semanal.');
            $table->string('name', 120)->comment('Nome exibido ao jogador.');
            $table->string('description', 500)->nullable()->comment('Texto opcional para loja ou detalhe do baú.');
            $table->unsignedInteger('cost_moedas')->nullable()->comment('Preço em moedas na loja. NULL se o baú não se compra com moedas.');
            $table->unsignedInteger('cost_cristais')->nullable()->comment('Preço em cristais na loja. NULL se o baú não se compra com cristais.');
            $table->boolean('available_in_shop')->default(true)->comment('Se falso, o baú não aparece na loja (ex.: só recompensa semanal ou evento).');
            $table->boolean('active')->default(true)->comment('Baú inativo não entra em sorteios nem vendas.');
            $table->unsignedSmallInteger('sort_order')->default(0)->comment('Ordem de exibição em listagens administrativas ou UI.');
            $table->timestamps();
        });

        Schema::create('chest_items', function (Blueprint $table) {
            $table->id()->comment('Chave primária da linha de loot do baú.');
            $table->foreignId('chest_id')->comment('Baú a que este item pertence.')->constrained('chests')->cascadeOnDelete();
            $table->string('asset_category', 40)->comment('Categoria do cosmético para o frontend resolver pasta/recursos. Ex.: card_back, profile_bg, faction_icon.');
            $table->string('asset_key', 80)->comment('Chave do asset dentro da categoria (ex.: slug do verso, sem path de ficheiro).');
            $table->string('display_tier', 20)->comment('Tier só para UI (cor de borda, brilho). Valores típicos: comum, rara, epica, lendaria. Não expõe probabilidade.');
            $table->unsignedInteger('drop_weight')->default(1)->comment('Peso relativo no sorteio dentro deste baú. Quanto maior, mais frequente. Não precisa somar 100.');
            $table->unsignedSmallInteger('sort_order')->default(0)->comment('Ordem de exibição na grelha “o que pode sair deste baú”.');
            $table->timestamps();
        });

        Schema::create('player_chest_stacks', function (Blueprint $table) {
            $table->id()->comment('Chave primária da pilha de baús por jogador.');
            $table->foreignId('user_id')->comment('Dono do inventário.')->constrained()->cascadeOnDelete();
            $table->foreignId('chest_id')->comment('Tipo de baú acumulado (quantidade na coluna quantity).')->constrained('chests')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0)->comment('Quantidade de baús deste tipo ainda não abertos.');
            $table->timestamps();
            $table->unique(['user_id', 'chest_id']);
        });

        Schema::create('player_cosmetic_unlocks', function (Blueprint $table) {
            $table->id()->comment('Chave primária do desbloqueio de cosmético.');
            $table->foreignId('user_id')->comment('Jogador que desbloqueou o item.')->constrained()->cascadeOnDelete();
            $table->string('asset_category', 40)->comment('Igual a chest_items.asset_category.');
            $table->string('asset_key', 80)->comment('Igual a chest_items.asset_key.');
            $table->timestamps();
            $table->unique(['user_id', 'asset_category', 'asset_key'], 'player_cosmetic_unlock_unique');
        });

        Schema::create('weekly_chest_pools', function (Blueprint $table) {
            $table->id()->comment('Chave primária do conjunto de baús possíveis na recompensa semanal.');
            $table->string('slug', 64)->unique()->comment('Identificador da pool (config: qual pool usar ao resgatar semana).');
            $table->string('name', 120)->comment('Nome interno para painel administrativo.');
            $table->boolean('active')->default(true)->comment('Pool inativa não deve ser usada no resgate.');
            $table->timestamps();
        });

        Schema::create('weekly_chest_pool_chests', function (Blueprint $table) {
            $table->id()->comment('Chave primária da associação pool ↔ baú.');
            $table->foreignId('weekly_chest_pool_id')->comment('Pool de recompensa semanal.')->constrained('weekly_chest_pools')->cascadeOnDelete();
            $table->foreignId('chest_id')->comment('Baú que pode ser sorteado ao resgatar esta semana.')->constrained('chests')->cascadeOnDelete();
            $table->unsignedInteger('weight')->default(1)->comment('Peso relativo: qual baú sai com mais frequência dentro desta pool.');
            $table->timestamps();
            $table->unique(['weekly_chest_pool_id', 'chest_id'], 'weekly_pool_chest_unique');
        });

        Schema::table('player_weeklies', function (Blueprint $table) {
            $table->foreignId('granted_chest_id')->nullable()->after('offers')->comment('Baú entregue no resgate semanal (auditoria e UI).')->constrained('chests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('player_weeklies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('granted_chest_id');
        });
        Schema::dropIfExists('weekly_chest_pool_chests');
        Schema::dropIfExists('weekly_chest_pools');
        Schema::dropIfExists('player_cosmetic_unlocks');
        Schema::dropIfExists('player_chest_stacks');
        Schema::dropIfExists('chest_items');
        Schema::dropIfExists('chests');
    }
};
