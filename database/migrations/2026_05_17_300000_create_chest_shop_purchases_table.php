<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chest_shop_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chest_id')->constrained('chests')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('currency', 16);
            $table->unsignedInteger('unit_price');
            $table->unsignedBigInteger('total_paid');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chest_shop_purchases');
    }
};
