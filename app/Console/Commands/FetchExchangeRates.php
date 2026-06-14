<?php

namespace App\Console\Commands;

use App\Models\Exchange;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchExchangeRates extends Command
{
    protected $signature = 'app:fetch-exchange-rates {--provider=}';

    protected $description = 'Fetch exchange rates based on USD (placeholder)';

    public function handle(): int
    {
        $baseUrl = config('services.exchangerate.base_url', 'https://v6.exchangerate-api.com/v6');
        $apiKey = config('services.exchangerate.key');

        $this->info("Fetching exchange rates based on USD from {$baseUrl}" . ($apiKey ? ' (using configured API key)' : ''));

        // Build request depending on provider conventions.
        // For exchangerate-api (v6) the URL is: /v6/YOUR-API-KEY/latest/USD
        $isExchangerateApi = str_contains($baseUrl, 'exchangerate-api.com');

        if ($isExchangerateApi && $apiKey) {
            $url = rtrim($baseUrl, '/').'/'.trim($apiKey, '/').'/latest/USD';
            $response = Http::get($url);
        } else {
            $symbols = implode(',', ['USD', 'EUR', 'CHF']);
            $params = ['base' => 'USD', 'symbols' => $symbols];

            if ($apiKey) {
                $params['api_key'] = $apiKey;
            }

            $response = Http::get(rtrim($baseUrl, '/').'/latest', $params);
        }

        if (! $response->successful()) {
            $this->error('Failed to fetch exchange rates: '.$response->status());

            return Command::FAILURE;
        }

        $data = $response->json();
        // Support both common shapes: 'rates' and 'conversion_rates'
        $rates = $data['rates'] ?? $data['conversion_rates'] ?? [];

        foreach ($rates as $currency => $value) {
            Exchange::updateOrCreate(['currency' => $currency], ['value' => $value, 'fetched_at' => now()]);
            $this->info("{$currency}: {$value}");
        }

        return Command::SUCCESS;
    }
}
