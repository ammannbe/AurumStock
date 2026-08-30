<?php

use App\Models\StockPrice;
use App\Models\StockPriceHistory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Historical imports used to overwrite the current price, leaving
     * stock_prices holding whichever date was imported last.
     */
    public function up(): void
    {
        StockPrice::query()->each(function (StockPrice $stockPrice): void {
            /** @var StockPriceHistory|null $latest */
            $latest = $stockPrice->histories()
                ->orderByRaw('COALESCE(fetched_at, created_at) DESC')
                ->first();

            if (! $latest) {
                return;
            }

            $latestAt = $latest->fetched_at ?? $latest->created_at;

            if ($stockPrice->fetched_at !== null && $stockPrice->fetched_at->greaterThanOrEqualTo($latestAt)) {
                return;
            }

            $stockPrice->forceFill([
                'price' => $latest->price,
                'fetched_at' => $latestAt,
            ])->save();
        });
    }

    public function down(): void
    {
        // Recomputable from history; nothing to reverse.
    }
};
