<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'user_id' => null,
            'supplier_id' => function (array $attributes) {
                return Supplier::factory()->create(['user_id' => $attributes['user_id'] ?? null])->getKey();
            },
            'order_number' => $this->faker->unique()->bothify('ORD-####'),
            'order_date' => $this->faker->dateTimeBetween('-3 years')->format('Y-m-d'),
            'receipts' => null,
        ];
    }
}
