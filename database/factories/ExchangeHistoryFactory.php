<?php

namespace Database\Factories;

use App\Models\Exchange;
use App\Models\ExchangeHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeHistory>
 */
class ExchangeHistoryFactory extends Factory
{
    protected $model = ExchangeHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exchange_id' => Exchange::factory(),
            'value' => $this->faker->randomFloat(6, 0.5, 1.5),
            'fetched_at' => now()->startOfDay(),
        ];
    }
}
