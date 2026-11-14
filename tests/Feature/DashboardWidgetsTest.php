<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Filament\Widgets\ExchangesWidget;
use App\Filament\Widgets\MaterialPriceHistoryChartWidget;
use App\Filament\Widgets\StockPricesWidget;
use App\Models\Exchange;
use App\Models\StockPrice;
use App\Models\StockPriceHistory;
use App\Models\User;
use App\Support\CurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CurrencyConverter::flushCache();
    }

    /**
     * @return array<string, mixed>
     */
    private function chartData(string $startDate, string $endDate, int $material = 1): array
    {
        $widget = new MaterialPriceHistoryChartWidget;
        $widget->materialValue = $material;
        $widget->startDate = $startDate;
        $widget->endDate = $endDate;

        return (new \ReflectionMethod($widget, 'getData'))->invoke($widget);
    }

    public function test_stock_prices_are_shown_in_the_preferred_currency(): void
    {
        Exchange::create(['currency' => 'CHF', 'value' => 0.8]);
        StockPrice::create(['material' => 1, 'price' => 100, 'fetched_at' => now()]);

        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::CHF]));

        Livewire::test(StockPricesWidget::class)
            ->assertSee('CHF')
            ->assertDontSee('USD / oz');
    }

    public function test_stock_price_history_chart_groups_long_ranges_weekly_at_most(): void
    {
        $stockPrice = StockPrice::create(['material' => 1, 'price' => 100, 'fetched_at' => now()]);

        $start = now()->subYears(5)->startOfDay();
        $days = 5 * 365;

        foreach (range(0, $days) as $offset) {
            StockPriceHistory::create([
                'stock_price_id' => $stockPrice->id,
                'price' => 100 + $offset,
                'fetched_at' => $start->copy()->addDays($offset),
            ]);
        }

        $this->actingAs(User::factory()->create());

        $data = $this->chartData($start->toDateString(), now()->toDateString());

        // Weekly is the coarsest grouping, so roughly one point per week remains.
        $this->assertGreaterThan(intdiv($days, 7) - 5, count($data['labels']));
        $this->assertLessThan(intdiv($days, 7) + 5, count($data['labels']));
        $this->assertSame(0, $data['datasets'][0]['pointRadius']);

        foreach ($data['labels'] as $label) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $label);
        }
    }

    public function test_stock_price_history_chart_keeps_daily_points_for_a_full_year(): void
    {
        $stockPrice = StockPrice::create(['material' => 1, 'price' => 100, 'fetched_at' => now()]);

        $start = now()->subDays(300)->startOfDay();

        foreach (range(0, 300) as $offset) {
            StockPriceHistory::create([
                'stock_price_id' => $stockPrice->id,
                'price' => 100 + $offset,
                'fetched_at' => $start->copy()->addDays($offset),
            ]);
        }

        $this->actingAs(User::factory()->create());

        $data = $this->chartData($start->toDateString(), now()->toDateString());

        $this->assertCount(301, $data['labels']);
        $this->assertSame(0, $data['datasets'][0]['pointRadius']);
    }

    public function test_stock_price_history_chart_keeps_daily_points_for_short_ranges(): void
    {
        $stockPrice = StockPrice::create(['material' => 1, 'price' => 100, 'fetched_at' => now()]);

        $start = now()->subDays(20)->startOfDay();

        foreach (range(0, 20) as $offset) {
            StockPriceHistory::create([
                'stock_price_id' => $stockPrice->id,
                'price' => 100 + $offset,
                'fetched_at' => $start->copy()->addDays($offset),
            ]);
        }

        $this->actingAs(User::factory()->create());

        $data = $this->chartData($start->toDateString(), now()->toDateString());

        $this->assertCount(21, $data['labels']);
        $this->assertSame(2, $data['datasets'][0]['pointRadius']);
    }

    public function test_stock_price_history_chart_shows_hourly_points_for_a_single_day(): void
    {
        $stockPrice = StockPrice::create(['material' => 1, 'price' => 100, 'fetched_at' => now()]);

        $start = now()->startOfDay();

        foreach (range(0, 5) as $hour) {
            StockPriceHistory::create([
                'stock_price_id' => $stockPrice->id,
                'price' => 100 + $hour,
                'fetched_at' => $start->copy()->addHours($hour),
            ]);
        }

        $this->actingAs(User::factory()->create());

        $data = $this->chartData($start->toDateString(), $start->toDateString());

        $this->assertCount(6, $data['labels']);

        foreach ($data['labels'] as $label) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $label);
        }
    }

    public function test_stock_price_sparkline_uses_daily_averages_without_hourly_data(): void
    {
        $stockPrice = StockPrice::create(['material' => 1, 'price' => 100, 'fetched_at' => now()]);

        foreach (range(0, 4) as $offset) {
            StockPriceHistory::create([
                'stock_price_id' => $stockPrice->id,
                'price' => 100 + $offset,
                'fetched_at' => now()->subDays($offset)->startOfDay(),
            ]);
        }

        $this->actingAs(User::factory()->create());

        // One averaged point per day, so the five daily snapshots stay distinct.
        $this->assertCount(5, $this->sparklineFor($stockPrice));
    }

    public function test_stock_price_sparkline_switches_to_24h_with_hourly_data(): void
    {
        $stockPrice = StockPrice::create(['material' => 1, 'price' => 100, 'fetched_at' => now()]);

        foreach (range(0, 5) as $hour) {
            StockPriceHistory::create([
                'stock_price_id' => $stockPrice->id,
                'price' => 100 + $hour,
                'fetched_at' => now()->subHours($hour)->startOfHour(),
            ]);
        }

        $this->actingAs(User::factory()->create());

        // Hourly snapshots are kept individually rather than averaged per day.
        $this->assertCount(6, $this->sparklineFor($stockPrice));
    }

    /**
     * @return array<int, float>
     */
    private function sparklineFor(StockPrice $stockPrice): array
    {
        $widget = new StockPricesWidget;

        $hourly = (new \ReflectionMethod($widget, 'hasHourlySnapshots'))->invoke($widget, $stockPrice);

        return (new \ReflectionMethod($widget, 'sparkline'))->invoke($widget, $stockPrice, $hourly);
    }

    public function test_exchange_rates_are_ordered_preferred_then_usd_then_alphabetically(): void
    {
        foreach (['AUD' => 1.5, 'CHF' => 0.8, 'EUR' => 0.9, 'USD' => 1.0] as $currency => $value) {
            Exchange::create(['currency' => $currency, 'value' => $value]);
        }

        $this->actingAs(User::factory()->create(['preferred_currency' => Currency::EUR]));

        Livewire::test(ExchangesWidget::class)
            ->assertCanSeeTableRecords(Exchange::whereIn('currency', ['EUR', 'USD', 'AUD', 'CHF'])
                ->get()
                ->sortBy(fn (Exchange $e) => match ($e->currency) {
                    'EUR' => '0',
                    'USD' => '1',
                    default => '2'.$e->currency,
                })
                ->values(), inOrder: true);
    }

    public function test_stock_price_history_is_backfilled_once_from_existing_price_records(): void
    {
        $stock = StockPrice::create([
            'material' => 1,
            'price' => 100,
            'fetched_at' => '2024-01-10 09:00:00',
        ]);

        $this->assertDatabaseMissing('stock_price_histories', [
            'stock_price_id' => $stock->id,
        ]);

        $this->artisan('app:backfill-stock-price-histories')->assertSuccessful();

        $this->assertDatabaseHas('stock_price_histories', [
            'stock_price_id' => $stock->id,
            'price' => '100.000000',
            'fetched_at' => '2024-01-10 09:00:00',
        ]);

        $this->assertSame(1, $stock->fresh()->histories()->count());

        $this->artisan('app:backfill-stock-price-histories')->assertSuccessful();

        $stock->refresh();
        $this->assertSame(1, $stock->histories()->count());
    }

    public function test_historical_metalpriceapi_import_can_store_a_date_range(): void
    {
        Http::fake([
            'https://api.metalpriceapi.com/v1/timeframe*' => Http::response([
                'rates' => [
                    '2024-01-01' => ['XAU' => 2000.5],
                    '2024-01-02' => ['XAU' => 2010.5],
                ],
            ]),
        ]);

        $this->artisan('app:fetch-stock-prices --from=2024-01-01 --to=2024-01-02')->assertSuccessful();

        $this->assertDatabaseHas('stock_price_histories', [
            'stock_price_id' => 1,
            'price' => '2000.500000',
            'fetched_at' => '2024-01-01 00:00:00',
        ]);

        $this->assertDatabaseHas('stock_price_histories', [
            'stock_price_id' => 1,
            'price' => '2010.500000',
            'fetched_at' => '2024-01-02 00:00:00',
        ]);
    }

    public function test_historical_metalpriceapi_import_fails_cleanly_when_date_range_requires_paid_plan(): void
    {
        Http::fake([
            'https://api.metalpriceapi.com/v1/timeframe*' => Http::response([
                'success' => false,
                'error' => [
                    'statusCode' => 209,
                    'message' => 'Querying older than 30 days requires a paid plan. See documentation here: https://metalpriceapi.com/documentation#api_timeframe',
                ],
            ], 200),
        ]);

        $this->artisan('app:fetch-stock-prices --from=2025-01-01 --to=2025-02-01')
            ->assertFailed();
    }
}
