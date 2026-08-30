<?php

namespace App\Filament\Widgets;

use App\Enums\Material;
use App\Models\StockPrice;
use App\Support\CurrencyConverter;
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
        $currency = CurrencyConverter::preferredCode();

        return collect(Material::cases())
            ->map(function (Material $material) use ($currency): Stat {
                $stock = StockPrice::query()
                    ->where('material', $material)
                    ->latest('fetched_at')
                    ->latest('id')
                    ->first();

                $price = CurrencyConverter::fromUsd($stock !== null ? (float) $stock->price : null);

                $history = $stock
                    ? $stock->histories()
                        ->latest('id')
                        ->limit(30)
                        ->pluck('price')
                        ->reverse()
                        ->map(fn ($value): float => CurrencyConverter::fromUsd((float) $value))
                        ->values()
                        ->all()
                    : [];

                if ($history === []) {
                    $history = [$price];
                }

                return Stat::make($material->label(), Number::currency($price, $currency, app()->getLocale(), 4))
                    ->description(__(':currency / oz', ['currency' => $currency]))
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
