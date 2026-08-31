<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('stock_price_histories')
            ->select('stock_price_id', 'fetched_at', DB::raw('MIN(id) as keep_id'))
            ->groupBy('stock_price_id', 'fetched_at')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('stock_price_histories')
                ->where('stock_price_id', $duplicate->stock_price_id)
                ->where('fetched_at', $duplicate->fetched_at)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('stock_price_histories', function (Blueprint $table) {
            $table->unique(['stock_price_id', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_price_histories', function (Blueprint $table) {
            $table->dropUnique(['stock_price_id', 'fetched_at']);
        });
    }
};
