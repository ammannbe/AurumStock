<?php

namespace Tests\Feature;

use App\Models\StockPrice;
use App\Models\StockPriceHistory;
use GuzzleHttp\Promise\PromiseInterface as PromiseResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchStockPricesIntervalTest extends TestCase
{
    use RefreshDatabase;

    private float $goldPrice = 4400.0;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.metalpriceapi.key', 'test-key');
        config()->set('services.metalpriceapi.base_url', 'https://api.metalpriceapi.com/v1/latest');

        // A single stub, because a later Http::fake() would not replace an earlier one.
        Http::fake(fn (): PromiseResponse => Http::response([
            'success' => true,
            'rates' => [
                'XAU' => $this->goldPrice,
                'XAG' => 30.0,
                'XPT' => 900.0,
                'XPD' => 1000.0,
            ],
        ]));
    }

    private function fakeRates(float $gold): void
    {
        $this->goldPrice = $gold;
    }

    private function goldHistoryCount(): int
    {
        $stock = StockPrice::where('material', 1)->firstOrFail();

        return StockPriceHistory::where('stock_price_id', $stock->id)->count();
    }

    public function test_daily_interval_keeps_one_history_row_per_day(): void
    {
        config()->set('services.metalpriceapi.interval', 'daily');

        Carbon::setTestNow('2026-08-31 08:00:00');
        $this->fakeRates(4400.0);
        $this->artisan('app:fetch-stock-prices')->assertSuccessful();

        Carbon::setTestNow('2026-08-31 15:00:00');
        $this->fakeRates(4500.0);
        $this->artisan('app:fetch-stock-prices')->assertSuccessful();

        $this->assertSame(1, $this->goldHistoryCount());
        $this->assertSame('4500.000000', StockPrice::where('material', 1)->firstOrFail()->price);
    }

    public function test_hourly_interval_records_one_history_row_per_hour(): void
    {
        config()->set('services.metalpriceapi.interval', 'hourly');

        Carbon::setTestNow('2026-08-31 08:00:00');
        $this->fakeRates(4400.0);
        $this->artisan('app:fetch-stock-prices')->assertSuccessful();

        Carbon::setTestNow('2026-08-31 09:30:00');
        $this->fakeRates(4450.0);
        $this->artisan('app:fetch-stock-prices')->assertSuccessful();

        $this->assertSame(2, $this->goldHistoryCount());
    }

    public function test_hourly_interval_collapses_runs_within_the_same_hour(): void
    {
        config()->set('services.metalpriceapi.interval', 'hourly');

        Carbon::setTestNow('2026-08-31 08:05:00');
        $this->fakeRates(4400.0);
        $this->artisan('app:fetch-stock-prices')->assertSuccessful();

        Carbon::setTestNow('2026-08-31 08:45:00');
        $this->fakeRates(4420.0);
        $this->artisan('app:fetch-stock-prices')->assertSuccessful();

        $this->assertSame(1, $this->goldHistoryCount());

        $stock = StockPrice::where('material', 1)->firstOrFail();

        $this->assertDatabaseHas('stock_price_histories', [
            'stock_price_id' => $stock->id,
            'price' => 4420.000000,
            'fetched_at' => '2026-08-31 08:00:00',
        ]);
    }

    public function test_the_interval_option_overrides_the_configured_interval(): void
    {
        config()->set('services.metalpriceapi.interval', 'daily');

        Carbon::setTestNow('2026-08-31 08:00:00');
        $this->fakeRates(4400.0);
        $this->artisan('app:fetch-stock-prices', ['--interval' => 'hourly'])->assertSuccessful();

        Carbon::setTestNow('2026-08-31 09:00:00');
        $this->fakeRates(4450.0);
        $this->artisan('app:fetch-stock-prices', ['--interval' => 'hourly'])->assertSuccessful();

        $this->assertSame(2, $this->goldHistoryCount());
    }
}
