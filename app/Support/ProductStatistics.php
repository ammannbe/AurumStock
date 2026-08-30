<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductStatistics
{
    /**
     * @param  Collection<int, Product>  $products
     */
    public static function priceTrend(Collection $products): float
    {
        $totalPurchaseUsd = $products->sum(function (Product $product): float {
            $exchangeValue = (float) ($product->exchange?->getAttribute('value') ?? 0);

            if (! $product->price || $exchangeValue <= 0) {
                return 0;
            }

            return ((float) $product->price / $exchangeValue) * ($product->amount ?? 1);
        });

        if ($totalPurchaseUsd <= 0) {
            return 0;
        }

        $totalMaterialUsd = $products->sum(
            fn (Product $product): float => (float) ($product->material_value_total ?? 0)
        );

        return ($totalMaterialUsd - $totalPurchaseUsd) / $totalPurchaseUsd * 100;
    }
}
