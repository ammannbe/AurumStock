<?php

namespace Database\Factories;

use App\Models\Depository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Depository>
 */
class DepositoryFactory extends Factory
{
    protected $model = Depository::class;

    public function definition()
    {
        return [
            'user_id' => null,
            'name' => $this->faker->company,
            'description' => $this->faker->sentence,
        ];
    }
}
