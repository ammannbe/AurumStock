<?php

namespace Tests\Feature;

use App\Models\StockPrice;
use App\Models\StockPriceHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StockPriceFreshnessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.metalpriceapi.key', 'test-key');
        config()->set('services.metalpriceapi.base_url', 'https://api.metalpriceapi.com/v1/latest');
    }

    public function test_importing_an_old_day_does_not_replace_the_current_price(): void
    {
        $stockPrice = StockPrice::create([
            'material' => 1,
            'price' => 4455.17,
            'fetched_at' => now(),
        ]);

        $this->artisan('app:import-historical-stock-prices', [
            '--material' => 1,
            '--date' => '2011-12-30',
            '--price' => 1563.80,
        ])->assertSuccessful();

        $stockPrice->refresh();

        $this->assertSame('4455.170000', $stockPrice->price);
        $this->assertTrue($stockPrice->fetched_at->isToday());

        // The old day is still recorded in the history.
        $this->assertDatabaseHas('stock_price_histories', [
            'stock_price_id' => $stockPrice->id,
            'price' => 1563.800000,
        ]);
    }

    public function test_importing_a_newer_day_does_replace_the_current_price(): void
    {
        $stockPrice = StockPrice::create([
            'material' => 1,
            'price' => 1563.80,
            'fetched_at' => '2011-12-30',
        ]);

        $this->artisan('app:import-historical-stock-prices', [
            '--material' => 1,
            '--date' => '2026-08-30',
            '--price' => 4455.17,
        ])->assertSuccessful();

        $this->assertSame('4455.170000', $stockPrice->fresh()->price);
    }

    public function test_a_historical_range_import_keeps_the_newest_price(): void
    {
        $stockPrice = StockPrice::create([
            'material' => 1,
            'price' => 4455.17,
            'fetched_at' => now(),
        ]);

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'rates' => [
                    '2011-12-29' => ['XAU' => 1 / 1560.0],
                    '2011-12-30' => ['XAU' => 1 / 1563.8],
                ],
            ]),
        ]);

        $this->artisan('app:fetch-stock-prices', [
            '--from' => '2011-12-29',
            '--to' => '2011-12-30',
        ])->assertSuccessful();

        $this->assertSame('4455.170000', $stockPrice->fresh()->price);
        $this->assertSame(2, StockPriceHistory::where('stock_price_id', $stockPrice->id)->count());
    }
}
