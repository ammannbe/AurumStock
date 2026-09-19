<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition()
    {
        return [
            'user_id' => null,
            'name' => $this->faker->unique()->company,
            'website' => $this->faker->url,
            'notes' => $this->faker->sentence,
        ];
    }
}
