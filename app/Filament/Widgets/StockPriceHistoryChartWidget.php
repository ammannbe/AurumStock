<?php

namespace App\Filament\Widgets;

use App\Enums\Material;
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

class StockPriceHistoryChartWidget extends ChartWidget
{
    use HasFiltersSchema;

    protected ?string $heading = null;

    protected ?string $pollingInterval = '30s';

    protected ?string $maxHeight = '360px';

    public function getHeading(): ?string
    {
        return __('Stock Price History').' ('.CurrencyConverter::preferredCode().')';
    }

    /**
     * Preset ranges, mapped to the number of days they span. Null means all data.
     */
    private const PRESETS = [
        '1d' => 1,
        '1m' => 30,
        '3m' => 90,
        '1y' => 365,
        '5y' => 1825,
        'all' => null,
    ];

    private const DEFAULT_PRESET = '1y';

    private const SESSION_KEY = 'widgets.stock_price_history.filters';

    // Runs after the trait fills the schema defaults, so stored filters win.
    public function mountHasFiltersSchema(): void
    {
        $stored = session(self::SESSION_KEY);

        $this->filters = is_array($stored) && $stored !== []
            ? $stored
            : $this->defaultFilters();
    }

    public function updatedFilters(mixed $value, ?string $key = null): void
    {
        $preset = $this->filters['preset'] ?? null;

        if ($key === 'preset' && is_string($preset) && $preset !== 'custom') {
            [$this->filters['startDate'], $this->filters['endDate']] = $this->rangeForPreset($preset);
        }

        // Editing a date by hand no longer matches the chosen preset.
        if (in_array($key, ['startDate', 'endDate'], true)) {
            $this->filters['preset'] = $this->presetForCurrentDates() ?? 'custom';
        }

        session([self::SESSION_KEY => $this->filters]);
    }

    public function resetFilters(): void
    {
        $this->filters = $this->defaultFilters();

        session()->forget(self::SESSION_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFilters(): array
    {
        [$start, $end] = $this->rangeForPreset(self::DEFAULT_PRESET);

        return [
            'preset' => self::DEFAULT_PRESET,
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

        // 'all' maps to null, so check the key rather than using ?? here.
        $days = array_key_exists($preset, self::PRESETS)
            ? self::PRESETS[$preset]
            : self::PRESETS[self::DEFAULT_PRESET];

        if ($days === null) {
            $earliest = DB::table('stock_price_histories')
                ->selectRaw('MIN(DATE(COALESCE(fetched_at, created_at))) as earliest_day')
                ->first()?->earliest_day;

            return [$earliest ? (string) $earliest : now()->subYear()->toDateString(), $end];
        }

        return [now()->subDays($days)->toDateString(), $end];
    }

    private function presetForCurrentDates(): ?string
    {
        $start = $this->filters['startDate'] ?? null;
        $end = $this->filters['endDate'] ?? null;

        if (! is_string($start) || ! is_string($end)) {
            return null;
        }

        foreach (array_keys(self::PRESETS) as $preset) {
            [$presetStart, $presetEnd] = $this->rangeForPreset($preset);

            if ($presetStart === $start && $presetEnd === $end) {
                return $preset;
            }
        }

        return null;
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('preset')
                    ->label(__('Period'))
                    ->options([
                        '1d' => __('1 day'),
                        '1m' => __('1 month'),
                        '3m' => __('3 months'),
                        '1y' => __('1 year'),
                        '5y' => __('5 years'),
                        'all' => __('All'),
                        'custom' => __('Custom'),
                    ])
                    ->default(self::DEFAULT_PRESET)
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->columnSpanFull(),
                DatePicker::make('startDate')
                    ->label(__('Start Date'))
                    ->default(fn (): string => $this->rangeForPreset(self::DEFAULT_PRESET)[0])
                    ->native(false)
                    ->columnSpan(1),
                DatePicker::make('endDate')
                    ->label(__('End Date'))
                    ->default(now()->toDateString())
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
     * Above this many days the chart falls back to weekly averages.
     */
    private const MAX_DAILY_POINTS = 400;

    /**
     * Up to this many days the chart shows each hourly snapshot separately.
     */
    private const MAX_HOURLY_DAYS = 2;

    /**
     * Above this many points the markers are hidden so the line stays readable.
     */
    private const MAX_POINTS_WITH_MARKERS = 60;

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        [$startDate, $endDate] = $this->resolveDateRange();

        $from = $startDate->copy()->startOfDay();
        $to = $endDate->copy()->endOfDay();

        $hourly = $from->diffInDays($to) <= self::MAX_HOURLY_DAYS;

        $timestamp = 'COALESCE(stock_price_histories.fetched_at, stock_price_histories.created_at)';

        $query = DB::table('stock_price_histories')
            ->join('stock_prices', 'stock_prices.id', '=', 'stock_price_histories.stock_price_id')
            ->whereBetween(DB::raw($timestamp), [$from, $to]);

        // Short ranges keep every snapshot so hourly fetches stay visible.
        $rows = ($hourly
            ? $query
                ->selectRaw("stock_prices.material as material, {$timestamp} as moment, stock_price_histories.price as avg_price")
                ->orderBy('moment')
            : $query
                ->selectRaw("stock_prices.material as material, DATE({$timestamp}) as moment, AVG(stock_price_histories.price) as avg_price")
                ->groupBy('stock_prices.material', DB::raw("DATE({$timestamp})"))
                ->orderBy('moment')
        )
            ->get()
            ->map(fn ($row): array => [
                'material' => (int) $row->material,
                'moment' => (string) $row->moment,
                'day' => Carbon::parse((string) $row->moment)->format('Y-m-d'),
                'avg_price_usd' => (float) $row->avg_price,
            ])
            ->values();

        // Convert each day at the rate that applied then, not at today's rate.
        $ratesByDay = CurrencyConverter::ratesForDays(
            $rows->pluck('day')->unique()->values()->all()
        );

        $rows = $rows->map(fn (array $row): array => [
            'material' => $row['material'],
            'moment' => $row['moment'],
            'avg_price' => $row['avg_price_usd'] * ($ratesByDay[$row['day']] ?? 1.0),
        ]);

        $interval = $hourly
            ? 'hour'
            : $this->resolveInterval($rows->pluck('moment')->unique()->count());

        // Bucketing happens in PHP so the query stays portable across drivers.
        $buckets = $rows
            ->groupBy(fn (array $row): string => $row['material'].'|'.$this->bucketKey($row['moment'], $interval))
            ->map(fn ($group): array => [
                'material' => $group->first()['material'],
                'bucket' => $this->bucketKey($group->first()['moment'], $interval),
                'avg_price' => $group->avg('avg_price'),
            ])
            ->values();

        $labels = $buckets->pluck('bucket')->unique()->sort()->values()->all();

        $showPoints = count($labels) <= self::MAX_POINTS_WITH_MARKERS;

        $datasets = collect(Material::cases())
            ->map(function (Material $material) use ($buckets, $labels, $showPoints): array {
                $byBucket = $buckets
                    ->where('material', $material->value)
                    ->keyBy('bucket');

                return [
                    'label' => $material->label(),
                    'data' => collect($labels)
                        ->map(fn (string $label): ?float => isset($byBucket[$label]) ? round((float) $byBucket[$label]['avg_price'], 2) : null)
                        ->all(),
                    'borderColor' => match ($material) {
                        Material::Gold => '#eab308',
                        Material::Silver => '#6b7280',
                        Material::Platinum => '#3b82f6',
                        Material::Palladium => '#10b981',
                    },
                    'backgroundColor' => match ($material) {
                        Material::Gold => 'rgba(234, 179, 8, 0.15)',
                        Material::Silver => 'rgba(107, 114, 128, 0.15)',
                        Material::Platinum => 'rgba(59, 130, 246, 0.15)',
                        Material::Palladium => 'rgba(16, 185, 129, 0.15)',
                    },
                    'spanGaps' => true,
                    'tension' => 0.3,
                    'borderWidth' => 2,
                    'pointRadius' => $showPoints ? 2 : 0,
                    'pointHoverRadius' => 4,
                ];
            })
            ->all();

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    /**
     * @return 'day'|'week'
     */
    private function resolveInterval(int $dayCount): string
    {
        return $dayCount <= self::MAX_DAILY_POINTS ? 'day' : 'week';
    }

    private function bucketKey(string $moment, string $interval): string
    {
        $date = Carbon::parse($moment);

        return match ($interval) {
            'hour' => $date->startOfHour()->format('Y-m-d H:i'),
            'week' => $date->startOfWeek()->format('Y-m-d'),
            default => $date->format('Y-m-d'),
        };
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveDateRange(): array
    {
        $defaults = $this->defaultFilters();

        $startDate = Carbon::parse($this->filters['startDate'] ?? $defaults['startDate']);
        $endDate = Carbon::parse($this->filters['endDate'] ?? $defaults['endDate']);

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [$startDate, $endDate];
    }
}
