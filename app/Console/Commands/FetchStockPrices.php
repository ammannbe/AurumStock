<?php

namespace App\Console\Commands;

use App\Enums\Material;
use App\Models\StockPrice;
use App\Models\StockPriceHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchStockPrices extends Command
{
    protected $signature = 'app:fetch-stock-prices {--provider=}';

    protected $description = 'Fetch stock prices for materials (placeholder)';

    public function handle(): int
    {
        $this->info('Fetching metal prices using configured provider (MetalpriceAPI expected).');

        $baseUrl = config('services.metalpriceapi.base_url');
        $apiKey = config('services.metalpriceapi.key');

        $mapping = Material::symbolMap(true);
        $symbols = implode(',', array_map(fn ($m) => 'X'.$m, $mapping));

        $response = Http::withHeaders(array_filter([
            'Authorization' => $apiKey ? "Bearer {$apiKey}" : null,
        ]))->get($baseUrl, [
            'base' => 'USD',
            'symbols' => $symbols,
            'api_key' => $apiKey,
        ]);

        if (! $response->successful()) {
            $this->error('Failed to fetch metal prices: '.$response->status());

            return Command::FAILURE;
        }

        $data = $response->json();
        foreach ($mapping as $mat => $symbol) {
            $rateKey = 'USDX'.$symbol;
            $price = isset($data['rates'][$rateKey])
                ? $data['rates'][$rateKey]
                : null;

            if ($price === null) {
                $this->warn("No price for {$symbol}; skipping.");

                continue;
            }

            $stock = StockPrice::updateOrCreate(
                ['material' => $mat],
                ['price' => $price, 'fetched_at' => now()]
            );

            $historyTimestamp = $stock->fetched_at ?? now();

            StockPriceHistory::updateOrCreate([
                'stock_price_id' => $stock->id,
                'price' => $price,
                'fetched_at' => $historyTimestamp,
            ]);

            $this->info("Updated {$symbol}: ".number_format($price, 6).' USD/oz');
        }

        return Command::SUCCESS;
    }
}
