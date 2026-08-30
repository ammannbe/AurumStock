<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_templates', function (Blueprint $table) {
            $table->dropColumn([
                'amount',
                'certificate',
                'packaging',
                'price',
                'price_currency',
                'reference_number',
                'purchase_date',
                'tax_year',
                'quality',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('product_templates', function (Blueprint $table) {
            $table->unsignedInteger('amount')->default(1);
            $table->boolean('certificate')->default(false);
            $table->string('packaging')->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->string('price_currency', 3)->nullable();
            $table->string('reference_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->unsignedSmallInteger('tax_year')->nullable();
            $table->string('quality')->nullable();
        });
    }
};
