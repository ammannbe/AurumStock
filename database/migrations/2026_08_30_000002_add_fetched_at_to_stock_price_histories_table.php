<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_price_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_price_histories', 'fetched_at')) {
                $table->timestamp('fetched_at')->nullable()->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_price_histories', function (Blueprint $table) {
            if (Schema::hasColumn('stock_price_histories', 'fetched_at')) {
                $table->dropColumn('fetched_at');
            }
        });
    }
};
