<?php

namespace Database\Factories;

use App\Models\ProductTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductTemplate>
 */
class ProductTemplateFactory extends Factory
{
    protected $model = ProductTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word,
            'type' => 2,
            'material' => 1,
            'weight' => $this->faker->randomFloat(4, 0.1, 1000),
            'weight_unit' => 1,
            'amount' => 1,
            'face_value' => 20,
            'year' => $this->faker->numberBetween(1900, 2026),
            'reference_number' => $this->faker->bothify('REF-###'),
            'size' => '35 mm',
            'price' => null,
            'price_currency' => null,
            'front_image_path' => null,
            'back_image_path' => null,
            'text_front' => null,
            'text_back' => null,
            'assets' => null,
            'notes' => null,
            'quality' => null,
            'certificate' => false,
            'manufacturer' => $this->faker->company,
            'country_of_origin' => 'CH',
            'fineness' => '999.9',
            'packaging' => null,
            'mintage' => $this->faker->numberBetween(500, 50000),
            'purchase_date' => null,
            'tax_year' => null,
        ];
    }
}
