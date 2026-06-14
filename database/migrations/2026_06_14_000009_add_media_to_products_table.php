<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('front_image_path')->nullable()->after('price_currency');
            $table->string('back_image_path')->nullable()->after('front_image_path');
            $table->json('assets')->nullable()->after('back_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['front_image_path', 'back_image_path', 'assets']);
        });
    }
};
