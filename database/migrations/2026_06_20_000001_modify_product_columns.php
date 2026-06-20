<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Adjust lengths and numeric precision
            $table->string('country_of_origin', 2)->nullable()->change();
            $table->unsignedInteger('mintage')->nullable()->change();
            $table->decimal('price', 12, 2)->nullable()->change();
            $table->decimal('weight', 12, 3)->nullable()->change();

            // Add indexes for common filters
            $table->index('user_id');
            $table->index('depository_id');
            $table->index('material');
            $table->index('type');
            $table->index('year');
            $table->index('price_currency');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('country_of_origin', 3)->nullable()->change();
            $table->unsignedBigInteger('mintage')->nullable()->change();
            $table->string('price')->nullable()->change();
            $table->string('weight')->nullable()->change();

            $table->dropIndex(['user_id']);
            $table->dropIndex(['depository_id']);
            $table->dropIndex(['material']);
            $table->dropIndex(['type']);
            $table->dropIndex(['year']);
            $table->dropIndex(['price_currency']);
        });
    }
};
