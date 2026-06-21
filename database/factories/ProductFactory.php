<?php

namespace Database\Factories;

use App\Models\Depository;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'depository_id' => function (array $attributes) {
                return Depository::factory()->create(['user_id' => $attributes['user_id'] ?? null])->getKey();
            },
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
