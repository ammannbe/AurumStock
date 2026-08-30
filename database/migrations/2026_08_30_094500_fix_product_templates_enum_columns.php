<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_templates', function (Blueprint $table) {
            $table->string('quality')->nullable()->change();
            $table->string('packaging')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_templates', function (Blueprint $table) {
            $table->tinyInteger('quality')->nullable()->change();
            $table->tinyInteger('packaging')->nullable()->change();
        });
    }
};
