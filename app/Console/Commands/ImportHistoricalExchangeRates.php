<?php

namespace App\Console\Commands;

use App\Models\Exchange;
use App\Models\ExchangeHistory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportHistoricalExchangeRates extends Command
{
    protected $signature = 'app:import-historical-exchange-rates
        {--date= : Import a single day (Y-m-d)}
        {--from= : Start of a date range (Y-m-d)}
        {--to= : End of a date range (Y-m-d)}
        {--currencies= : Comma separated codes, defaults to the currencies already tracked}';

    protected $description = 'Import historical USD based exchange rates from ExchangeRate-API.';

    /**
     * The API serves one day per request, so a range costs one call per day.
     */
    private const MAX_DAYS_PER_RUN = 400;

    public function handle(): int
    {
        $dates = $this->resolveDates();

        if ($dates === null) {
            return Command::FAILURE;
        }

        $apiKey = config('services.exchangerate.key');

        if (! is_string($apiKey) || $apiKey === '') {
            $this->error('EXCHANGE_RATE_API_KEY is required for historical imports.');

            return Command::FAILURE;
        }

        $currencies = $this->resolveCurrencies();

        if ($currencies === []) {
            $this->error('No currencies to import. Use --currencies or fetch rates once first.');

            return Command::FAILURE;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($dates as $date) {
            $result = $this->importDate($apiKey, $date, $currencies);

            if ($result === null) {
                return Command::FAILURE;
            }

            $imported += $result['imported'];
            $skipped += $result['skipped'];
        }

        $this->info("Imported {$imported} historical exchange rate entries, skipped {$skipped}.");

        return Command::SUCCESS;
    }

    /**
     * @param  array<int, string>  $currencies
     * @return array{imported: int, skipped: int}|null
     */
    private function importDate(string $apiKey, Carbon $date, array $currencies): ?array
    {
        $baseUrl = rtrim((string) config('services.exchangerate.base_url', 'https://v6.exchangerate-api.com/v6'), '/');

        $url = sprintf(
            '%s/%s/history/USD/%d/%d/%d',
            $baseUrl,
            trim($apiKey, '/'),
            $date->year,
            $date->month,
            $date->day,
        );

        $response = Http::get($url);
        $data = $response->json();

        if (($data['result'] ?? null) === 'error') {
            $errorType = $data['error-type'] ?? 'unknown';

            if ($errorType === 'no-data-available') {
                $this->warn($date->toDateString().': no data available, skipping.');

                return ['imported' => 0, 'skipped' => count($currencies)];
            }

            $this->error($date->toDateString().': '.$this->describeError($errorType));

            return null;
        }

        if (! $response->successful()) {
            $this->error('Failed to fetch historical exchange rates: '.$response->status());

            return null;
        }

        $rates = $data['conversion_rates'] ?? [];
        $fetchedAt = $date->copy()->startOfDay();

        $imported = 0;
        $skipped = 0;

        foreach ($currencies as $currency) {
            $value = $rates[$currency] ?? null;

            if (! is_numeric($value)) {
                $skipped++;

                continue;
            }

            $exchange = Exchange::query()->firstOrCreate(
                ['currency' => $currency],
                ['value' => $value, 'fetched_at' => $fetchedAt],
            );

            $history = ExchangeHistory::updateOrCreate(
                ['exchange_id' => $exchange->id, 'fetched_at' => $fetchedAt],
                ['value' => $value],
            );

            if ($history->wasRecentlyCreated) {
                $imported++;
            }

            // The latest rate only moves forward, never backwards to an older import.
            if ($exchange->fetched_at === null || $fetchedAt->greaterThanOrEqualTo($exchange->fetched_at)) {
                $exchange->update(['value' => $value, 'fetched_at' => $fetchedAt]);
            }

            $this->line($date->toDateString().' '.$currency.': '.number_format((float) $value, 6));
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @return array<int, Carbon>|null
     */
    private function resolveDates(): ?array
    {
        $date = $this->option('date');
        $from = $this->option('from');
        $to = $this->option('to');

        if ($date !== null) {
            return [Carbon::parse($date)->startOfDay()];
        }

        if ($from === null || $to === null) {
            $this->error('Use --date, or both --from and --to.');

            return null;
        }

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $days = $start->diffInDays($end) + 1;

        if ($days > self::MAX_DAYS_PER_RUN) {
            $this->error("The range covers {$days} days and each day costs one API request. Limit is ".self::MAX_DAYS_PER_RUN.'.');

            return null;
        }

        $dates = [];

        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addDay()) {
            $dates[] = $cursor->copy();
        }

        return $dates;
    }

    /**
     * @return array<int, string>
     */
    private function resolveCurrencies(): array
    {
        $option = $this->option('currencies');

        if (is_string($option) && $option !== '') {
            return collect(explode(',', $option))
                ->map(fn (string $code): string => strtoupper(trim($code)))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return Exchange::query()->orderBy('currency')->pluck('currency')->all();
    }

    private function describeError(string $errorType): string
    {
        return match ($errorType) {
            'plan-upgrade-required' => 'Historical data requires a paid ExchangeRate-API plan.',
            'invalid-key' => 'The configured API key is not valid.',
            'inactive-account' => 'The ExchangeRate-API account is not confirmed.',
            'quota-reached' => 'The request quota for this plan has been reached.',
            'unsupported-code' => 'The requested currency code is not supported.',
            'malformed-request' => 'The request URL was rejected as malformed.',
            default => 'Request failed: '.$errorType,
        };
    }
}
