<?php

namespace App\Console\Commands;

use App\Enums\Material;
use App\Models\StockPrice;
use App\Models\StockPriceHistory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchStockPrices extends Command
{
    protected $signature = 'app:fetch-stock-prices {--provider=} {--from=} {--to=}';

    protected $description = 'Fetch stock prices for materials from MetalpriceAPI, optionally for a historical date range.';

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        if ($from || $to) {
            return $this->fetchHistoricalRange($from, $to);
        }

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
            $price = $this->extractPrice($data['rates'] ?? [], $symbol);

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

    protected function fetchHistoricalRange(?string $from, ?string $to): int
    {
        if ($from === null || $to === null) {
            $this->error('Use both --from and --to for historical imports.');

            return Command::FAILURE;
        }

        $baseUrl = $this->resolveHistoricalBaseUrl();
        $apiKey = config('services.metalpriceapi.key');

        if ($apiKey === null || $apiKey === '') {
            $this->error('METALPRICE_API_KEY is required for historical imports.');

            return Command::FAILURE;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->get($baseUrl, [
            'base' => 'USD',
            'start_date' => $from,
            'end_date' => $to,
            'api_key' => $apiKey,
        ]);

        $data = $response->json();

        if (($data['success'] ?? true) === false) {
            $message = $data['error']['message'] ?? 'Historical date range is not available with the current plan.';
            $this->error($message);

            return Command::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('Failed to fetch historical metal prices: '.$response->status());

            return Command::FAILURE;
        }

        $ratesByDate = $data['rates'] ?? [];
        $symbolMap = Material::symbolMap(true);

        $count = 0;

        foreach ($ratesByDate as $date => $dayRates) {
            foreach ($symbolMap as $materialValue => $symbol) {
                $price = $this->extractPrice($dayRates, $symbol);

                if ($price === null) {
                    continue;
                }

                $fetchedAt = Carbon::parse($date)->startOfDay();

                $stock = StockPrice::firstOrCreate(
                    ['material' => $materialValue],
                    ['price' => $price, 'fetched_at' => $fetchedAt]
                );

                // The latest price only moves forward, never back to an older import.
                if ($stock->fetched_at === null || $fetchedAt->greaterThanOrEqualTo($stock->fetched_at)) {
                    $stock->update(['price' => $price, 'fetched_at' => $fetchedAt]);
                }

                $updated = StockPriceHistory::updateOrCreate([
                    'stock_price_id' => $stock->id,
                    'fetched_at' => $fetchedAt,
                ], [
                    'price' => $price,
                ]);

                if ($updated->wasRecentlyCreated) {
                    $count++;
                }

                $this->line(Material::from($materialValue)->label().' '.$date.': '.number_format((float) $price, 6).' USD/oz');
            }
        }

        $this->info('Imported '.$count.' historical price entries.');

        return Command::SUCCESS;
    }

    protected function resolveHistoricalBaseUrl(): string
    {
        $baseUrl = rtrim(config('services.metalpriceapi.base_url', 'https://api.metalpriceapi.com/v1/latest'), '/');

        if (str_contains($baseUrl, '/timeframe')) {
            return $baseUrl;
        }

        if (str_contains($baseUrl, '/latest')) {
            return preg_replace('#/latest$#', '/timeframe', $baseUrl) ?? $baseUrl.'/timeframe';
        }

        return $baseUrl.'/timeframe';
    }

    /**
     * @param  array<string, mixed>  $rates
     */
    protected function extractPrice(array $rates, string $symbol): ?float
    {
        foreach (['USDX'.$symbol, 'X'.$symbol, $symbol, 'USDX'.strtolower($symbol), 'X'.strtolower($symbol)] as $key) {
            if (array_key_exists($key, $rates) && is_numeric($rates[$key])) {
                return (float) $rates[$key];
            }
        }

        return null;
    }
}
