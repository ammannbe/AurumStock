<?php

namespace App\Console\Commands;

use App\Enums\Material;
use App\Models\StockPrice;
use App\Models\StockPriceHistory;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportHistoricalStockPrices extends Command
{
    protected $signature = 'app:import-historical-stock-prices {--material=} {--date=} {--price=}';

    protected $description = 'Import one historical metal price for a specific date and material.';

    public function handle(): int
    {
        $material = $this->option('material');
        $date = $this->option('date');
        $price = $this->option('price');

        if ($material === null || $date === null || $price === null) {
            $this->error('Use --material, --date and --price.');

            return Command::FAILURE;
        }

        $materialEnum = Material::tryFrom((int) $material);

        if ($materialEnum === null) {
            $this->error('Invalid material value.');

            return Command::FAILURE;
        }

        $fetchedAt = Carbon::parse($date);
        $priceValue = (float) $price;

        $stock = StockPrice::query()->firstOrCreate(
            ['material' => $materialEnum->value],
            ['price' => $priceValue, 'fetched_at' => $fetchedAt]
        );

        // The latest price only moves forward, never back to an older import.
        if ($stock->fetched_at === null || $fetchedAt->greaterThanOrEqualTo($stock->fetched_at)) {
            $stock->update([
                'price' => $priceValue,
                'fetched_at' => $fetchedAt,
            ]);
        }

        $exists = StockPriceHistory::query()
            ->where('stock_price_id', $stock->id)
            ->where('fetched_at', $fetchedAt->toDateTimeString())
            ->exists();

        if (! $exists) {
            StockPriceHistory::create([
                'stock_price_id' => $stock->id,
                'price' => $priceValue,
                'fetched_at' => $fetchedAt,
            ]);
        }

        $this->info('Historical stock price imported for '.$materialEnum->label().' on '.$fetchedAt->toDateString().'.');

        return Command::SUCCESS;
    }
}
