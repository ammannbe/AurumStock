<?php

namespace Tests\Feature;

use App\Models\Exchange;
use App\Models\ExchangeHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HistoricalExchangeRatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.exchangerate.key', 'test-key');
        config()->set('services.exchangerate.base_url', 'https://v6.exchangerate-api.com/v6');
    }

    public function test_it_imports_rates_for_a_single_date(): void
    {
        Http::fake([
            '*/history/USD/2024/2/22' => Http::response([
                'result' => 'success',
                'year' => 2024,
                'month' => 2,
                'day' => 22,
                'base_code' => 'USD',
                'conversion_rates' => ['CHF' => 0.9744, 'EUR' => 0.8929],
            ]),
        ]);

        $this->artisan('app:import-historical-exchange-rates', [
            '--date' => '2024-02-22',
            '--currencies' => 'CHF,EUR',
        ])->assertSuccessful();

        $this->assertDatabaseCount('exchange_histories', 2);

        $chf = Exchange::where('currency', 'CHF')->firstOrFail();

        $this->assertSame('0.974400', $chf->histories()->firstOrFail()->value);
        $this->assertSame('2024-02-22', $chf->histories()->firstOrFail()->fetched_at->toDateString());
    }

    public function test_it_imports_a_date_range(): void
    {
        Http::fake([
            '*' => Http::response([
                'result' => 'success',
                'conversion_rates' => ['CHF' => 0.9],
            ]),
        ]);

        $this->artisan('app:import-historical-exchange-rates', [
            '--from' => '2024-02-20',
            '--to' => '2024-02-22',
            '--currencies' => 'CHF',
        ])->assertSuccessful();

        $this->assertDatabaseCount('exchange_histories', 3);
    }

    public function test_it_is_idempotent(): void
    {
        Http::fake([
            '*' => Http::response([
                'result' => 'success',
                'conversion_rates' => ['CHF' => 0.9],
            ]),
        ]);

        foreach (range(1, 2) as $ignored) {
            $this->artisan('app:import-historical-exchange-rates', [
                '--date' => '2024-02-22',
                '--currencies' => 'CHF',
            ])->assertSuccessful();
        }

        $this->assertDatabaseCount('exchange_histories', 1);
    }

    public function test_it_fails_cleanly_when_the_plan_does_not_allow_historical_data(): void
    {
        Http::fake([
            '*' => Http::response(['result' => 'error', 'error-type' => 'plan-upgrade-required']),
        ]);

        $this->artisan('app:import-historical-exchange-rates', [
            '--date' => '2024-02-22',
            '--currencies' => 'CHF',
        ])
            ->expectsOutputToContain('paid ExchangeRate-API plan')
            ->assertFailed();

        $this->assertDatabaseCount('exchange_histories', 0);
    }

    public function test_it_skips_days_without_data(): void
    {
        Http::fake([
            '*' => Http::response(['result' => 'error', 'error-type' => 'no-data-available']),
        ]);

        $this->artisan('app:import-historical-exchange-rates', [
            '--date' => '1985-01-01',
            '--currencies' => 'CHF',
        ])->assertSuccessful();

        $this->assertDatabaseCount('exchange_histories', 0);
    }

    public function test_it_requires_an_api_key(): void
    {
        config()->set('services.exchangerate.key', null);

        $this->artisan('app:import-historical-exchange-rates', [
            '--date' => '2024-02-22',
            '--currencies' => 'CHF',
        ])
            ->expectsOutputToContain('EXCHANGE_RATE_API_KEY')
            ->assertFailed();
    }

    public function test_it_rejects_ranges_that_would_exhaust_the_quota(): void
    {
        $this->artisan('app:import-historical-exchange-rates', [
            '--from' => '2020-01-01',
            '--to' => '2024-01-01',
            '--currencies' => 'CHF',
        ])
            ->expectsOutputToContain('one API request')
            ->assertFailed();
    }

    public function test_an_older_import_does_not_overwrite_the_current_rate(): void
    {
        $exchange = Exchange::create([
            'currency' => 'CHF',
            'value' => 0.81,
            'fetched_at' => now(),
        ]);

        Http::fake([
            '*' => Http::response([
                'result' => 'success',
                'conversion_rates' => ['CHF' => 0.5],
            ]),
        ]);

        $this->artisan('app:import-historical-exchange-rates', [
            '--date' => '2015-02-22',
            '--currencies' => 'CHF',
        ])->assertSuccessful();

        $this->assertSame('0.810000', $exchange->fresh()->value);
        $this->assertSame('0.500000', ExchangeHistory::firstOrFail()->value);
    }

    public function test_the_daily_fetch_records_history(): void
    {
        Http::fake([
            '*' => Http::response([
                'result' => 'success',
                'conversion_rates' => ['CHF' => 0.81, 'EUR' => 0.92],
            ]),
        ]);

        $this->artisan('app:fetch-exchange-rates')->assertSuccessful();

        $this->assertDatabaseCount('exchange_histories', 2);
    }
}
