<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Widgets\ProductPriceHistoryChartWidget;
use App\Models\Depository;
use App\Models\Product;
use App\Models\StockPrice;
use App\Models\StockPriceHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPriceHistoryChartTest extends TestCase
{
    use RefreshDatabase;

    private function seedHistory(): void
    {
        $stockPrice = StockPrice::create(['material' => 1, 'price' => 100, 'fetched_at' => now()]);

        foreach (range(0, 400, 10) as $daysAgo) {
            StockPriceHistory::create([
                'stock_price_id' => $stockPrice->id,
                'price' => 100 + $daysAgo,
                'fetched_at' => now()->subDays($daysAgo),
            ]);
        }
    }

    private function makeProduct(?string $purchaseDate, ?int $year = null): Product
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        return Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'material' => 1,
            'purchase_date' => $purchaseDate,
            'year' => $year,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataFor(ProductPriceHistoryChartWidget $widget): array
    {
        return (new \ReflectionMethod($widget, 'getData'))->invoke($widget);
    }

    private function widgetFor(Product $product): ProductPriceHistoryChartWidget
    {
        $widget = new ProductPriceHistoryChartWidget;
        $widget->record = $product;
        $widget->mountHasFiltersSchema();

        return $widget;
    }

    public function test_it_starts_at_the_purchase_date(): void
    {
        $this->seedHistory();
        $product = $this->makeProduct(now()->subDays(100)->toDateString());

        $widget = $this->widgetFor($product);

        $this->assertSame('purchase', $widget->filters['preset']);
        $this->assertSame(now()->subDays(100)->toDateString(), $widget->filters['startDate']);
    }

    public function test_it_starts_at_the_product_year_without_a_purchase_date(): void
    {
        $this->seedHistory();
        $product = $this->makeProduct(null, (int) now()->subYear()->format('Y'));

        $widget = $this->widgetFor($product);

        $this->assertSame('year', $widget->filters['preset']);
        $this->assertSame(now()->subYear()->startOfYear()->toDateString(), $widget->filters['startDate']);
    }

    public function test_it_uses_the_whole_period_without_a_purchase_date_or_year(): void
    {
        $this->seedHistory();
        $product = $this->makeProduct(null, null);

        $widget = $this->widgetFor($product);

        $this->assertSame('all', $widget->filters['preset']);
        $this->assertSame(now()->subDays(400)->toDateString(), $widget->filters['startDate']);
    }

    public function test_the_range_limits_the_data(): void
    {
        $this->seedHistory();
        $product = $this->makeProduct(now()->subDays(100)->toDateString());

        $widget = $this->widgetFor($product);
        $sincePurchase = count($this->dataFor($widget)['labels']);

        $widget->filters['preset'] = '1m';
        $widget->updatedFilters('1m', 'preset');
        $lastMonth = count($this->dataFor($widget)['labels']);

        $this->assertLessThan($sincePurchase, $lastMonth);
    }

    public function test_it_highlights_the_purchase_date(): void
    {
        $this->seedHistory();
        $product = $this->makeProduct(now()->subDays(100)->toDateString());

        $data = $this->dataFor($this->widgetFor($product));

        $marker = $this->datasetLabelled($data, __('Purchase Date'));

        $this->assertNotNull($marker);

        $visible = array_filter($marker['pointRadius'], fn (int $radius): bool => $radius > 0);

        $this->assertCount(1, $visible);

        // The highlighted point sits on the value line, not at zero.
        $index = array_key_first($visible);
        $this->assertNotNull($marker['data'][$index]);
    }

    public function test_there_is_no_marker_without_a_purchase_date(): void
    {
        $this->seedHistory();
        $product = $this->makeProduct(null);

        $data = $this->dataFor($this->widgetFor($product));

        $this->assertNull($this->datasetLabelled($data, __('Purchase Date')));
    }

    public function test_it_shows_history_from_before_the_purchase(): void
    {
        $this->seedHistory();
        $product = $this->makeProduct(now()->subDays(100)->toDateString());

        $data = $this->dataFor($this->widgetFor($product));

        // 10% of the 100 day range is prepended, so the chart starts ~110 days back.
        $this->assertLessThan(
            now()->subDays(100)->toDateString(),
            $data['labels'][0],
        );
        $this->assertGreaterThanOrEqual(
            now()->subDays(120)->toDateString(),
            $data['labels'][0],
        );
    }

    public function test_the_lead_in_never_predates_the_recorded_history(): void
    {
        $this->seedHistory();

        // The seeded history only reaches back 400 days.
        $product = $this->makeProduct(now()->subDays(390)->toDateString());

        $data = $this->dataFor($this->widgetFor($product));

        $this->assertGreaterThanOrEqual(
            now()->subDays(400)->toDateString(),
            $data['labels'][0],
        );
    }

    public function test_the_lead_in_only_applies_to_the_purchase_range(): void
    {
        $this->seedHistory();
        $product = $this->makeProduct(now()->subDays(100)->toDateString());

        $widget = $this->widgetFor($product);
        $widget->filters['preset'] = '1m';
        $widget->updatedFilters('1m', 'preset');

        $data = $this->dataFor($widget);

        $this->assertGreaterThanOrEqual(
            now()->subDays(30)->toDateString(),
            $data['labels'][0],
        );
    }

    public function test_the_y_axis_does_not_begin_at_zero(): void
    {
        $this->seedHistory();
        $product = $this->makeProduct(now()->subDays(100)->toDateString());

        $widget = $this->widgetFor($product);
        $options = (new \ReflectionMethod($widget, 'getOptions'))->invoke($widget);

        $this->assertFalse($options['scales']['y']['beginAtZero']);
        $this->assertGreaterThan(0, $options['scales']['y']['suggestedMin']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function datasetLabelled(array $data, string $label): ?array
    {
        foreach ($data['datasets'] as $dataset) {
            if (($dataset['label'] ?? null) === $label) {
                return $dataset;
            }
        }

        return null;
    }

    public function test_reset_restores_the_purchase_range(): void
    {
        $this->seedHistory();
        $product = $this->makeProduct(now()->subDays(100)->toDateString());

        $widget = $this->widgetFor($product);

        $widget->filters['preset'] = '1m';
        $widget->updatedFilters('1m', 'preset');
        $this->assertSame('1m', $widget->filters['preset']);

        $widget->resetFilters();

        $this->assertSame('purchase', $widget->filters['preset']);
        $this->assertSame(now()->subDays(100)->toDateString(), $widget->filters['startDate']);
    }
}
