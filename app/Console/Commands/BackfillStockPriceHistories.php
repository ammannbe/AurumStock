<?php

namespace App\Console\Commands;

use App\Models\StockPrice;
use Illuminate\Console\Command;

class BackfillStockPriceHistories extends Command
{
    protected $signature = 'app:backfill-stock-price-histories';

    protected $description = 'Create a one-time history entry for each existing stock price record.';

    public function handle(): int
    {
        $count = 0;

        StockPrice::query()->get()->each(function (StockPrice $stock) use (&$count): void {
            $fetchedAt = $stock->fetched_at ?? $stock->created_at ?? now();

            $exists = $stock->histories()
                ->where('price', $stock->price)
                ->whereNotNull('fetched_at')
                ->where('fetched_at', $fetchedAt->toDateTimeString())
                ->exists();

            if ($exists) {
                return;
            }

            $stock->histories()->create([
                'price' => $stock->price,
                'fetched_at' => $fetchedAt,
            ]);

            $count++;
        });

        $this->info("Backfilled {$count} stock price history entries.");

        return Command::SUCCESS;
    }
}
