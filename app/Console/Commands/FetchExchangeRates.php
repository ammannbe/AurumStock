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
        $this->info('Fetching exchange rates based on USD from exchangerate.host');

        $symbols = implode(',', ['USD', 'EUR', 'CHF']);
        $response = Http::get('https://api.exchangerate.host/latest', ['base' => 'USD', 'symbols' => $symbols]);

        if (! $response->successful()) {
            $this->error('Failed to fetch exchange rates: '.$response->status());

            return Command::FAILURE;
        }

        $data = $response->json();
        $rates = $data['rates'] ?? [];

        foreach ($rates as $currency => $value) {
            Exchange::updateOrCreate(['currency' => $currency], ['value' => $value, 'fetched_at' => now()]);
            $this->info("{$currency}: {$value}");
        }

        return Command::SUCCESS;
    }
}
