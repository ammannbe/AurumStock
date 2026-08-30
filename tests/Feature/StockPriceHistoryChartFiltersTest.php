<?php

namespace Tests\Feature;

use App\Filament\Widgets\StockPriceHistoryChartWidget;
use App\Models\StockPrice;
use App\Models\StockPriceHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockPriceHistoryChartFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $stockPrice = StockPrice::create(['material' => 1, 'price' => 100, 'fetched_at' => now()]);

        foreach ([0, 10, 200, 800, 2000] as $daysAgo) {
            StockPriceHistory::create([
                'stock_price_id' => $stockPrice->id,
                'price' => 100 + $daysAgo,
                'fetched_at' => now()->subDays($daysAgo),
            ]);
        }

        $this->actingAs(User::factory()->create());
    }

    public function test_it_defaults_to_one_year(): void
    {
        Livewire::test(StockPriceHistoryChartWidget::class)
            ->assertSet('filters.preset', '1y')
            ->assertSet('filters.startDate', now()->subDays(365)->toDateString())
            ->assertSet('filters.endDate', now()->toDateString());
    }

    public function test_choosing_a_preset_updates_the_dates(): void
    {
        Livewire::test(StockPriceHistoryChartWidget::class)
            ->set('filters.preset', '1m')
            ->assertSet('filters.startDate', now()->subDays(30)->toDateString())
            ->assertSet('filters.endDate', now()->toDateString());
    }

    public function test_the_all_preset_starts_at_the_earliest_record(): void
    {
        Livewire::test(StockPriceHistoryChartWidget::class)
            ->set('filters.preset', 'all')
            ->assertSet('filters.startDate', now()->subDays(2000)->toDateString());
    }

    public function test_editing_a_date_switches_to_custom(): void
    {
        Livewire::test(StockPriceHistoryChartWidget::class)
            ->set('filters.startDate', now()->subDays(123)->toDateString())
            ->assertSet('filters.preset', 'custom');
    }

    public function test_filters_are_remembered_in_the_session(): void
    {
        Livewire::test(StockPriceHistoryChartWidget::class)
            ->set('filters.preset', '5y');

        $this->assertSame('5y', session('widgets.stock_price_history.filters.preset'));

        Livewire::test(StockPriceHistoryChartWidget::class)
            ->assertSet('filters.preset', '5y');
    }

    public function test_reset_restores_the_default_and_clears_the_session(): void
    {
        Livewire::test(StockPriceHistoryChartWidget::class)
            ->set('filters.preset', '5y')
            ->assertSet('filters.preset', '5y')
            ->call('resetFilters')
            ->assertSet('filters.preset', '1y')
            ->assertSet('filters.startDate', now()->subDays(365)->toDateString());

        $this->assertNull(session('widgets.stock_price_history.filters'));
    }

    public function test_the_preset_limits_the_data_returned(): void
    {
        $component = Livewire::test(StockPriceHistoryChartWidget::class)
            ->set('filters.preset', '1m');

        $widget = $component->instance();
        $data = (new \ReflectionMethod($widget, 'getData'))->invoke($widget);

        // Only the two entries within the last 30 days fall in range.
        $this->assertCount(2, $data['labels']);
    }
}
