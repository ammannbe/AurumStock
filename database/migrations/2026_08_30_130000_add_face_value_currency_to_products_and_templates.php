<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('face_value_currency', 3)->nullable()->after('face_value');
        });

        Schema::table('product_templates', function (Blueprint $table) {
            $table->string('face_value_currency', 3)->nullable()->after('face_value');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('face_value_currency');
        });

        Schema::table('product_templates', function (Blueprint $table) {
            $table->dropColumn('face_value_currency');
        });
    }
};
