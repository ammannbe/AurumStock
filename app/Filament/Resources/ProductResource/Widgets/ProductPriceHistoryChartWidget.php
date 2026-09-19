<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Models\Product;
use App\Support\CurrencyConverter;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Support\Facades\DB;

class ProductPriceHistoryChartWidget extends ChartWidget
{
    use HasFiltersSchema;

    public ?Product $record = null;

    // Resolved responsively by the dashboard stylesheet.
    protected ?string $maxHeight = 'var(--aurum-metal-chart-height)';

    /**
     * Above this many days the chart falls back to weekly averages.
     */
    private const MAX_DAILY_POINTS = 400;

    /**
     * Above this many points the markers are hidden so the line stays readable.
     */
    private const MAX_POINTS_WITH_MARKERS = 60;

    /**
     * Share of the shown range prepended before the purchase, for context.
     */
    private const PURCHASE_LEAD_IN = 0.1;

    /**
     * Preset ranges, mapped to the number of days they span. Null means all data.
     */
    private const PRESETS = [
        '1m' => 30,
        '3m' => 90,
        '1y' => 365,
        '5y' => 1825,
        'purchase' => null,
        'year' => null,
        'all' => null,
    ];

    public function getHeading(): ?string
    {
        return __('Price Trend').' ('.CurrencyConverter::preferredCode().')';
    }

    public function getDescription(): ?string
    {
        return __('Material value of this position based on historical metal prices.');
    }

    // Runs after the trait fills the schema defaults, so our own default wins.
    public function mountHasFiltersSchema(): void
    {
        $this->filters = $this->defaultFilters();
    }

    public function updatedFilters(mixed $value, ?string $key = null): void
    {
        $preset = $this->filters['preset'] ?? null;

        if ($key === 'preset' && is_string($preset) && $preset !== 'custom') {
            [$this->filters['startDate'], $this->filters['endDate']] = $this->rangeForPreset($preset);
        }

        // Editing a date by hand no longer matches the chosen preset.
        if (in_array($key, ['startDate', 'endDate'], true)) {
            $this->filters['preset'] = 'custom';
        }
    }

    public function resetFilters(): void
    {
        $this->filters = $this->defaultFilters();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFilters(): array
    {
        $preset = match (true) {
            $this->record?->purchase_date !== null => 'purchase',
            $this->record?->year !== null => 'year',
            default => 'all',
        };

        [$start, $end] = $this->rangeForPreset($preset);

        return [
            'preset' => $preset,
            'startDate' => $start,
            'endDate' => $end,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function rangeForPreset(string $preset): array
    {
        $end = now()->toDateString();

        if ($preset === 'purchase') {
            $purchaseDate = $this->record?->purchase_date;

            return [
                $purchaseDate ? $purchaseDate->toDateString() : $this->earliestRecordedDay(),
                $end,
            ];
        }

        if ($preset === 'year') {
            $year = $this->record?->year;

            return [
                $year ? Carbon::create($year, 1, 1)->toDateString() : $this->earliestRecordedDay(),
                $end,
            ];
        }

        // 'all' maps to null, so check the key rather than using ?? here.
        $days = array_key_exists($preset, self::PRESETS) ? self::PRESETS[$preset] : null;

        if ($days === null) {
            return [$this->earliestRecordedDay(), $end];
        }

        return [now()->subDays($days)->toDateString(), $end];
    }

    private function earliestRecordedDay(): string
    {
        $material = $this->record?->material;

        $earliest = DB::table('stock_price_histories')
            ->join('stock_prices', 'stock_prices.id', '=', 'stock_price_histories.stock_price_id')
            ->when($material, fn ($query) => $query->where('stock_prices.material', $material->value))
            ->selectRaw('MIN(DATE(COALESCE(stock_price_histories.fetched_at, stock_price_histories.created_at))) as earliest_day')
            ->first()?->earliest_day;

        return $earliest ? (string) $earliest : now()->subYear()->toDateString();
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('preset')
                    ->label(__('Period'))
                    ->options(fn (): array => array_filter([
                        'purchase' => $this->record?->purchase_date ? __('Since purchase') : null,
                        'year' => $this->record?->year ? __('Since product year') : null,
                        '1m' => __('1 month'),
                        '3m' => __('3 months'),
                        '1y' => __('1 year'),
                        '5y' => __('5 years'),
                        'all' => __('All'),
                        'custom' => __('Custom'),
                    ]))
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->columnSpanFull(),
                DatePicker::make('startDate')
                    ->label(__('Start Date'))
                    ->native(false)
                    ->columnSpan(1),
                DatePicker::make('endDate')
                    ->label(__('End Date'))
                    ->native(false)
                    ->columnSpan(1),
                Actions::make([
                    Action::make('resetFilters')
                        ->label(__('Reset'))
                        ->icon('heroicon-m-arrow-path')
                        ->color('gray')
                        ->link()
                        ->action(fn () => $this->resetFilters()),
                ])
                    ->alignEnd()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(): array
    {
        $defaults = $this->defaultFilters();

        $start = Carbon::parse($this->filters['startDate'] ?? $defaults['startDate']);
        $end = Carbon::parse($this->filters['endDate'] ?? $defaults['endDate']);

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$this->withPurchaseLeadIn($start, $end), $end];
    }

    /**
     * Pull the start back so the purchase is preceded by some context, but never
     * further than the oldest recorded day.
     */
    private function withPurchaseLeadIn(Carbon $start, Carbon $end): Carbon
    {
        $purchaseDate = $this->record?->purchase_date;

        if (! $purchaseDate || ! $start->isSameDay($purchaseDate)) {
            return $start;
        }

        $leadInDays = (int) round($start->diffInDays($end) * self::PURCHASE_LEAD_IN);

        if ($leadInDays < 1) {
            return $start;
        }

        $earliest = Carbon::parse($this->earliestRecordedDay());

        return $start->copy()->subDays($leadInDays)->max($earliest);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $product = $this->record;

        if (! $product) {
            return ['datasets' => [], 'labels' => []];
        }

        [$startDate, $endDate] = $this->resolveDateRange();

        $rows = DB::table('stock_price_histories')
            ->join('stock_prices', 'stock_prices.id', '=', 'stock_price_histories.stock_price_id')
            ->where('stock_prices.material', $product->material->value)
            ->whereBetween(
                DB::raw('COALESCE(stock_price_histories.fetched_at, stock_price_histories.created_at)'),
                [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]
            )
            ->selectRaw('DATE(COALESCE(stock_price_histories.fetched_at, stock_price_histories.created_at)) as day, AVG(stock_price_histories.price) as avg_price')
            ->groupBy(DB::raw('DATE(COALESCE(stock_price_histories.fetched_at, stock_price_histories.created_at))'))
            ->orderBy('day')
            ->get();

        if ($rows->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        $troyOunces = $product->weight_unit->toTroyOunce($product->fine_weight) * (int) $product->amount;

        $interval = $this->resolveInterval($rows->count());

        // Convert each day at the rate that applied then, not at today's rate.
        $ratesByDay = CurrencyConverter::ratesForDays(
            $rows->map(fn ($row): string => (string) $row->day)->unique()->values()->all()
        );

        // Bucketing happens in PHP so the query stays portable across drivers.
        $buckets = $rows
            ->groupBy(fn ($row): string => $this->bucketKey((string) $row->day, $interval))
            ->map(fn ($group): float => round(
                $group->avg(fn ($row): float => (float) $row->avg_price * $troyOunces * ($ratesByDay[(string) $row->day] ?? 1.0)),
                2
            ))
            ->sortKeys();

        $labels = $buckets->keys()->all();
        $values = $buckets->values()->all();

        $datasets = [[
            'label' => __('Material Value'),
            'data' => $values,
            'borderColor' => $product->material->color() === 'warning' ? '#eab308' : '#3b82f6',
            'backgroundColor' => 'rgba(59, 130, 246, 0.12)',
            'fill' => true,
            'tension' => 0.3,
            'borderWidth' => 2,
            'pointRadius' => count($labels) <= self::MAX_POINTS_WITH_MARKERS ? 2 : 0,
            'pointHoverRadius' => 4,
        ]];

        $purchasePrice = $this->purchasePriceInPreferredCurrency($product);

        if ($purchasePrice !== null) {
            $datasets[] = [
                'label' => __('Total Purchase Price'),
                'data' => array_fill(0, count($labels), round($purchasePrice, 2)),
                'borderColor' => '#ef4444',
                'borderDash' => [6, 4],
                'pointRadius' => 0,
                'fill' => false,
            ];
        }

        $purchaseIndex = $this->purchaseLabelIndex($labels, $interval);

        if ($purchaseIndex !== null) {
            // A single visible point marking the day the product was bought.
            $datasets[] = [
                'label' => __('Purchase Date'),
                'data' => array_map(
                    fn (int $index): ?float => $index === $purchaseIndex ? $values[$index] : null,
                    array_keys($labels)
                ),
                'borderColor' => '#ef4444',
                'backgroundColor' => '#ef4444',
                'pointRadius' => array_map(
                    fn (int $index): int => $index === $purchaseIndex ? 6 : 0,
                    array_keys($labels)
                ),
                'pointHoverRadius' => 8,
                'pointStyle' => 'rectRot',
                'showLine' => false,
                'fill' => false,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function purchaseLabelIndex(array $labels, string $interval): ?int
    {
        $purchaseDate = $this->record?->purchase_date;

        if (! $purchaseDate) {
            return null;
        }

        $index = array_search($this->bucketKey($purchaseDate->toDateString(), $interval), $labels, true);

        return $index === false ? null : $index;
    }

    /**
     * @return 'day'|'week'
     */
    private function resolveInterval(int $dayCount): string
    {
        return $dayCount <= self::MAX_DAILY_POINTS ? 'day' : 'week';
    }

    private function bucketKey(string $day, string $interval): string
    {
        $date = Carbon::parse($day);

        return $interval === 'week'
            ? $date->startOfWeek()->format('Y-m-d')
            : $date->format('Y-m-d');
    }

    private function purchasePriceInPreferredCurrency(Product $product): ?float
    {
        $price = $product->price;
        $currency = $product->price_currency;

        if (! $price || ! $currency) {
            return null;
        }

        // The purchase is a fixed historical amount, so convert at the rate of that day.
        $purchaseDate = $product->purchase_date;

        $rate = $purchaseDate
            ? CurrencyConverter::rateOn($purchaseDate, $currency)
            : CurrencyConverter::rate($currency);

        if ($rate <= 0) {
            return null;
        }

        $priceInUsd = (float) $price / $rate;

        return $purchaseDate
            ? CurrencyConverter::fromUsdOn($priceInUsd, $purchaseDate)
            : CurrencyConverter::fromUsd($priceInUsd);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        $values = [];

        // The purchase price line matters for the scale too, so include every dataset.
        foreach ($this->getCachedData()['datasets'] ?? [] as $dataset) {
            foreach ($dataset['data'] ?? [] as $value) {
                if (is_numeric($value)) {
                    $values[] = (float) $value;
                }
            }
        }

        $scale = ['beginAtZero' => false];

        if ($values !== []) {
            $min = min($values);
            $max = max($values);
            // A flat series would otherwise collapse into a zero-height axis.
            $padding = $max > $min ? ($max - $min) * 0.05 : max(abs($max) * 0.01, 0.01);

            $scale['suggestedMin'] = round($min - $padding, 2);
            $scale['suggestedMax'] = round($max + $padding, 2);
        }

        return [
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => $scale,
                'x' => ['ticks' => ['maxTicksLimit' => 8]],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
