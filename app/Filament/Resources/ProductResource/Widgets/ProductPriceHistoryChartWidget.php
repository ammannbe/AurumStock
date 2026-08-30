<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Models\Product;
use App\Support\CurrencyConverter;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ProductPriceHistoryChartWidget extends ChartWidget
{
    public ?Product $record = null;

    protected ?string $maxHeight = '320px';

    public function getHeading(): ?string
    {
        return __('Price Trend').' ('.CurrencyConverter::preferredCode().')';
    }

    public function getDescription(): ?string
    {
        return __('Material value of this position based on historical metal prices.');
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

        $rows = DB::table('stock_price_histories')
            ->join('stock_prices', 'stock_prices.id', '=', 'stock_price_histories.stock_price_id')
            ->where('stock_prices.material', $product->material->value)
            ->selectRaw('DATE(COALESCE(stock_price_histories.fetched_at, stock_price_histories.created_at)) as day, AVG(stock_price_histories.price) as avg_price')
            ->groupBy(DB::raw('DATE(COALESCE(stock_price_histories.fetched_at, stock_price_histories.created_at))'))
            ->orderBy('day')
            ->get();

        if ($rows->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        $troyOunces = $product->weight_unit->toTroyOunce((float) $product->weight) * (int) $product->amount;

        $labels = $rows->map(fn ($row): string => Carbon::parse($row->day)->format('Y-m-d'))->all();

        $values = $rows
            ->map(fn ($row): float => round(CurrencyConverter::fromUsd((float) $row->avg_price * $troyOunces), 2))
            ->all();

        $datasets = [[
            'label' => __('Material Value'),
            'data' => $values,
            'borderColor' => $product->material->color() === 'warning' ? '#eab308' : '#3b82f6',
            'backgroundColor' => 'rgba(59, 130, 246, 0.12)',
            'fill' => true,
            'tension' => 0.3,
            'pointRadius' => 2,
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

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    private function purchasePriceInPreferredCurrency(Product $product): ?float
    {
        $price = $product->price;
        $rate = (float) ($product->exchange?->getAttribute('value') ?? 0);

        if (! $price || $rate <= 0) {
            return null;
        }

        return CurrencyConverter::fromUsd((float) $price / $rate);
    }

    protected function getType(): string
    {
        return 'line';
    }
}
