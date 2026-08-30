<?php

namespace App\Filament\Widgets;

use App\Enums\Material;
use App\Support\CurrencyConverter;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
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

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('startDate')
                    ->label(__('Start Date'))
                    ->default(now()->subMonth()->toDateString())
                    ->native(false),
                DatePicker::make('endDate')
                    ->label(__('End Date'))
                    ->default(now()->toDateString())
                    ->native(false),
            ])
            ->columns(2);
    }

    /**
     * Above this many days the chart falls back to weekly averages.
     */
    private const MAX_DAILY_POINTS = 400;

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

        $rows = DB::table('stock_price_histories')
            ->join('stock_prices', 'stock_prices.id', '=', 'stock_price_histories.stock_price_id')
            ->selectRaw('stock_prices.material as material, DATE(COALESCE(stock_price_histories.fetched_at, stock_price_histories.created_at)) as day, AVG(stock_price_histories.price) as avg_price')
            ->whereBetween(DB::raw('COALESCE(stock_price_histories.fetched_at, stock_price_histories.created_at)'), [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->groupBy('stock_prices.material', DB::raw('DATE(COALESCE(stock_price_histories.fetched_at, stock_price_histories.created_at))'))
            ->orderBy('day')
            ->get()
            ->map(fn ($row): array => [
                'material' => (int) $row->material,
                'day' => (string) $row->day,
                'avg_price' => CurrencyConverter::fromUsd((float) $row->avg_price),
            ])
            ->values();

        $dayCount = $rows->pluck('day')->unique()->count();
        $interval = $this->resolveInterval($dayCount);

        // Bucketing happens in PHP so the query stays portable across drivers.
        $buckets = $rows
            ->groupBy(fn (array $row): string => $row['material'].'|'.$this->bucketKey($row['day'], $interval))
            ->map(fn ($group): array => [
                'material' => $group->first()['material'],
                'bucket' => $this->bucketKey($group->first()['day'], $interval),
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

    private function bucketKey(string $day, string $interval): string
    {
        $date = Carbon::parse($day);

        return $interval === 'week'
            ? $date->startOfWeek()->format('Y-m-d')
            : $date->format('Y-m-d');
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
        $startDate = Carbon::parse($this->filters['startDate'] ?? now()->subMonth()->toDateString());
        $endDate = Carbon::parse($this->filters['endDate'] ?? now()->toDateString());

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [$startDate, $endDate];
    }
}
