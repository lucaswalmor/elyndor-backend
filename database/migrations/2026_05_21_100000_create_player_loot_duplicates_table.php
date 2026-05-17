<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_loot_duplicates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stack_key', 191);
            $table->foreignId('card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->string('asset_category', 64)->nullable();
            $table->string('asset_key', 128)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'stack_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_loot_duplicates');
    }
};
