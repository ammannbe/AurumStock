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

                $hourly = $stock !== null && $this->hasHourlySnapshots($stock);
                $history = $stock !== null ? $this->sparkline($stock, $hourly) : [];

                if ($history === []) {
                    $history = [$price];
                }

                return Stat::make($material->label(), Number::currency($price, $currency, app()->getLocale(), 4))
                    ->description(__(':currency / oz · :window', [
                        'currency' => $currency,
                        'window' => $hourly ? __('24h') : __('30d'),
                    ]))
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

    /**
     * More than one snapshot within a day means the fetcher runs hourly.
     */
    private function hasHourlySnapshots(StockPrice $stock): bool
    {
        return $stock->histories()
            ->where('fetched_at', '>=', now()->subDay())
            ->count() > 1;
    }

    /**
     * @return array<int, float>
     */
    private function sparkline(StockPrice $stock, bool $hourly): array
    {
        if ($hourly) {
            return $stock->histories()
                ->where('fetched_at', '>=', now()->subDay())
                ->orderBy('fetched_at')
                ->pluck('price')
                ->map(fn ($value): float => CurrencyConverter::fromUsd((float) $value))
                ->all();
        }

        return $stock->histories()
            ->where('fetched_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(fetched_at) as day, AVG(price) as average')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('average')
            ->map(fn ($value): float => CurrencyConverter::fromUsd((float) $value))
            ->all();
    }
}
