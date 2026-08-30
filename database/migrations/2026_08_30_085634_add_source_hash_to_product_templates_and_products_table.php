<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_templates', function (Blueprint $table) {
            $table->string('source_hash')->nullable()->after('id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('source_hash')->nullable()->after('template_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('source_hash');
        });

        Schema::table('product_templates', function (Blueprint $table) {
            $table->dropColumn('source_hash');
        });
    }
};
