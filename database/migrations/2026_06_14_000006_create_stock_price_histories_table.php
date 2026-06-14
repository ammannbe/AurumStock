<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_price_id')->constrained()->cascadeOnDelete();
            $table->decimal('price_usd_per_gram', 14, 6);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_price_histories');
    }
};
