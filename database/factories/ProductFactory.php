<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'user_id' => null,
            'depository_id' => null,
            'name' => $this->faker->word,
            'type' => 1,
            'material' => 1,
            'weight' => $this->faker->randomFloat(4, 0.1, 1000),
            'weight_unit' => 1,
            'amount' => 1,
            'price' => null,
            'price_currency' => null,
        ];
    }
}
