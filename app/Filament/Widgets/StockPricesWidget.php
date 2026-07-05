<?php

namespace App\Filament\Widgets;

use App\Enums\Material;
use App\Models\StockPrice;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class StockPricesWidget extends StatsOverviewWidget
{
    public function getHeading(): ?string
    {
        return __('Stock Prices');
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        return collect(Material::cases())
            ->map(function (Material $material): Stat {
                $stock = StockPrice::query()
                    ->where('material', $material)
                    ->latest('fetched_at')
                    ->latest('id')
                    ->first();

                $price = $stock ? (float) $stock->price : 0.0;

                $history = $stock
                    ? $stock->histories()
                        ->latest('id')
                        ->limit(20)
                        ->pluck('price')
                        ->reverse()
                        ->map(fn ($value): float => (float) $value)
                        ->values()
                        ->all()
                    : [];

                if ($history === []) {
                    $history = [$price];
                }

                return Stat::make($material->label(), Number::currency($price, 'USD', app()->getLocale(), 4))
                    ->description(__('USD / oz'))
                    ->descriptionIcon($material->icon())
                    ->color(match ($material) {
                        Material::Gold => 'warning',
                        Material::Silver => 'gray',
                        Material::Platinum => 'info',
                        Material::Palladium => 'success',
                    })
                    ->chart($history);
            })
            ->all();
    }
}
