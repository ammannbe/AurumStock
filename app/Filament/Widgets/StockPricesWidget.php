<?php

namespace App\Filament\Widgets;

use App\Enums\Material;
use App\Models\Product;
use App\Models\StockPrice;
use App\Support\CurrencyConverter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

class StockPricesWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    public function getHeading(): ?string
    {
        return __('Stock Prices');
    }

    /**
     * Two per row on narrow viewports as well, so the cards stay a 2x2 grid.
     *
     * @return array<string, int>
     */
    protected function getColumns(): array
    {
        return ['default' => 2, '@4xl' => 4, '!@2xl' => 4];
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $currency = CurrencyConverter::preferredCode();
        $holdings = $this->holdingsByMaterial();

        return collect(Material::cases())
            ->map(function (Material $material) use ($currency, $holdings): Stat {
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

                /** @var Collection<int, Product> $products */
                $products = $holdings->get($material->value) ?? collect();

                $currentUsd = (float) $products->sum(fn (Product $product): float => (float) ($product->material_value_total ?? 0));

                $value = Number::currency(
                    CurrencyConverter::fromUsd($currentUsd),
                    $currency,
                    app()->getLocale(),
                    2,
                );

                return Stat::make($this->label($material, $products), $value)
                    ->description($this->describe($products, $currency))
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
     * @param  Collection<int, Product>  $products
     */
    private function label(Material $material, Collection $products): HtmlString
    {
        $label = e($material->label());

        if ($products->isEmpty()) {
            return new HtmlString($label);
        }

        $count = __(':count pcs', [
            'count' => (int) $products->sum(fn (Product $product): int => (int) ($product->amount ?? 1)),
        ]);

        return new HtmlString($label.' <span class="aurum-stat-count">('.e($count).')</span>');
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function describe(Collection $products, string $currency): HtmlString
    {
        $locale = app()->getLocale();
        $lines = [];

        if ($products->isNotEmpty()) {
            $currentUsd = (float) $products->sum(fn (Product $product): float => (float) ($product->material_value_total ?? 0));
            $purchaseUsd = (float) $products->sum(fn (Product $product): float => $this->purchaseUsd($product));

            if ($purchaseUsd > 0) {
                $gainUsd = $currentUsd - $purchaseUsd;
                $percent = $gainUsd / $purchaseUsd * 100;
                $negative = $gainUsd < 0;

                $gain = ($negative ? '−' : '+')
                    .Number::currency(abs(CurrencyConverter::fromUsd($gainUsd)), $currency, $locale, 2)
                    .' ('.Number::percentage(abs($percent), 2, locale: $locale).')';

                $lines[] = $negative
                    ? '<span class="aurum-stat-negative">'.e($gain).'</span>'
                    : e($gain);

                $since = $products->filter(fn (Product $product): bool => $product->purchase_date !== null)
                    ->min('purchase_date');

                if ($since !== null) {
                    $lines[] = e(__('since :date', ['date' => $since->isoFormat('L')]));
                }
            }
        }

        $markup = collect($lines)
            ->map(fn (string $line): string => '<span>'.$line.'</span>')
            ->implode('');

        return new HtmlString('<span class="aurum-stat-lines">'.$markup.'</span>');
    }

    private function purchaseUsd(Product $product): float
    {
        $rate = (float) ($product->exchange?->getAttribute('value') ?? 0);

        if (! $product->price || $rate <= 0) {
            return 0.0;
        }

        return ((float) $product->price / $rate) * (int) ($product->amount ?? 1);
    }

    /**
     * @return Collection<int, Collection<int, Product>>
     */
    private function holdingsByMaterial(): Collection
    {
        $userId = Auth::id();

        if ($userId === null) {
            return collect();
        }

        /** @var Collection<int, Collection<int, Product>> $grouped */
        $grouped = Product::query()
            ->with(['exchange', 'stockPrice'])
            ->where('user_id', $userId)
            ->get()
            ->groupBy(fn (Product $product): int => $product->material->value)
            ->toBase();

        return $grouped;
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
