<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depository_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->tinyInteger('type');
            $table->tinyInteger('material');
            $table->decimal('weight', 12, 4);
            $table->tinyInteger('weight_unit');
            $table->unsignedInteger('amount')->default(1);
            $table->decimal('face_value', 12, 2)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('size')->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->string('price_currency', 3)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
