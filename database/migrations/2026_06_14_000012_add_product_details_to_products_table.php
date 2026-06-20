<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('quality')->nullable()->after('notes');
            $table->boolean('certificate')->default(false)->after('quality');
            $table->string('manufacturer')->nullable()->after('certificate');
            $table->string('country_of_origin', 3)->nullable()->after('manufacturer');
            $table->string('fineness')->nullable()->after('country_of_origin');
            $table->string('packaging')->nullable()->after('fineness');
            $table->unsignedBigInteger('mintage')->nullable()->after('packaging');
            $table->date('purchase_date')->nullable()->after('mintage');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'quality',
                'certificate',
                'manufacturer',
                'country_of_origin',
                'fineness',
                'packaging',
                'mintage',
                'purchase_date',
            ]);
        });
    }
};
