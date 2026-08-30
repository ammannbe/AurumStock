<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('product_shares');
        Schema::dropIfExists('depository_shares');
    }

    public function down(): void
    {
        Schema::create('product_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_with_user_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('permission');
            $table->timestamps();

            $table->unique(['product_id', 'shared_with_user_id']);
        });

        Schema::create('depository_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('depository_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_with_user_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('permission');
            $table->timestamps();

            $table->unique(['depository_id', 'shared_with_user_id']);
        });
    }
};
