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

        $mapping = Material::symbolMap();
        $symbols = implode(',', $mapping);

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
            $rateKey = 'USD'.$symbol;
            $price = isset($data['rates'][$rateKey])
                ? $data['rates'][$rateKey]
                : null;

            if ($price === null) {
                $this->warn("No price for {$symbol}; skipping.");

                continue;
            }

            // assume provider returns per troy ounce by default (MetalpriceAPI often returns oz)
            $pricePerGram = $price / 31.1034768;

            $stock = StockPrice::updateOrCreate(
                ['material' => $mat],
                ['price_usd_per_gram' => $pricePerGram, 'fetched_at' => now()]
            );

            StockPriceHistory::create(['stock_price_id' => $stock->id, 'price_usd_per_gram' => $pricePerGram]);
            $this->info("Updated {$symbol}: ".number_format($pricePerGram, 6).' USD/g');
        }

        return Command::SUCCESS;
    }
}
