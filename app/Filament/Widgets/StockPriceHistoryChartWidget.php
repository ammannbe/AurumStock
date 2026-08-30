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

        $labels = $rows
            ->pluck('day')
            ->unique()
            ->values()
            ->map(fn (string $day): string => Carbon::parse($day)->format('Y-m-d'))
            ->all();

        $datasets = collect(Material::cases())
            ->map(function (Material $material) use ($rows, $labels): array {
                $byDay = $rows
                    ->where('material', $material->value)
                    ->keyBy(fn (array $row): string => Carbon::parse($row['day'])->format('Y-m-d'));

                return [
                    'label' => $material->label(),
                    'data' => collect($labels)
                        ->map(fn (string $label): ?float => isset($byDay[$label]) ? (float) $byDay[$label]['avg_price'] : null)
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
                    'pointRadius' => 2,
                ];
            })
            ->all();

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
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
