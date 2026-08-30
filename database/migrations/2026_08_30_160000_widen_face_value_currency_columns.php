<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('face_value_currency', 16)->nullable()->change();
        });

        Schema::table('product_templates', function (Blueprint $table) {
            $table->string('face_value_currency', 16)->nullable()->change();
        });
    }

    // Reverting truncates historic non-ISO codes such as SCHILLING.
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('face_value_currency', 3)->nullable()->change();
        });

        Schema::table('product_templates', function (Blueprint $table) {
            $table->string('face_value_currency', 3)->nullable()->change();
        });
    }
};
