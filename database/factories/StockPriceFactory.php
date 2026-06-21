<?php

namespace Database\Factories;

use App\Models\StockPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockPrice>
 */
class StockPriceFactory extends Factory
{
    protected $model = StockPrice::class;

    public function definition()
    {
        return [
            'material' => 1,
            'price_usd_per_gram' => $this->faker->randomFloat(6, 10, 80),
            'fetched_at' => now(),
        ];
    }
}
