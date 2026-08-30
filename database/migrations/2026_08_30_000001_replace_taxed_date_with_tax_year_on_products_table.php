<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('tax_year')->nullable()->after('purchase_date');
        });

        DB::table('products')
            ->whereNotNull('taxed_date')
            ->select('id', 'taxed_date')
            ->orderBy('id')
            ->chunk(500, function ($products) {
                foreach ($products as $product) {
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['tax_year' => (int) substr((string) $product->taxed_date, 0, 4)]);
                }
            });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('taxed_date');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->date('taxed_date')->nullable()->after('purchase_date');
        });

        DB::table('products')
            ->whereNotNull('tax_year')
            ->select('id', 'tax_year')
            ->orderBy('id')
            ->chunk(500, function ($products) {
                foreach ($products as $product) {
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['taxed_date' => $product->tax_year.'-01-01']);
                }
            });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tax_year');
        });
    }
};
