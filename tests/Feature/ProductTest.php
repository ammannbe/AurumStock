<?php

namespace Tests\Feature;

use App\Enums\Material;
use App\Enums\WeightUnit;
use App\Models\Depository;
use App\Models\Exchange;
use App\Models\Product;
use App\Models\StockPrice;
use App\Models\User;
use App\Support\ProductStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_product(): void
    {
        $user = User::factory()->create();
        $depot = Depository::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        /** @var Product $product */
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depot->id,
        ]);

        $this->assertEquals($user->id, $product->owner->id);
    }

    public function test_price_trends_account_for_product_amount_once(): void
    {
        Exchange::create([
            'currency' => 'USD',
            'value' => 1,
        ]);
        StockPrice::create([
            'material' => Material::Gold,
            'price' => 150,
        ]);

        $product = Product::factory()->create([
            'material' => Material::Gold,
            'weight' => 1,
            'weight_unit' => WeightUnit::TroyOunce,
            'amount' => 2,
            'price' => 100,
            'price_currency' => 'USD',
        ])->load(['exchange', 'stockPrice']);

        $this->assertEqualsWithDelta(50, $product->getAttribute('price_trend'), 0.001);
        $this->assertEqualsWithDelta(50, ProductStatistics::priceTrend(collect([$product])), 0.001);
    }

    public function test_fine_weight_applies_the_fineness_to_the_gross_weight(): void
    {
        $product = Product::factory()->make([
            'weight' => 20,
            'weight_unit' => WeightUnit::Gram,
            'fineness' => '835',
        ]);

        $this->assertEqualsWithDelta(16.7, $product->fine_weight, 0.0001);
    }

    public function test_fine_weight_falls_back_to_pure_when_fineness_is_missing(): void
    {
        $product = Product::factory()->make([
            'weight' => 20,
            'weight_unit' => WeightUnit::Gram,
            'fineness' => null,
        ]);

        $this->assertEqualsWithDelta(20.0, $product->fine_weight, 0.0001);
    }

    public function test_material_value_is_based_on_fine_weight_not_gross_weight(): void
    {
        StockPrice::create([
            'material' => Material::Silver,
            'price' => 100,
        ]);

        $product = Product::factory()->create([
            'material' => Material::Silver,
            'weight' => 1,
            'weight_unit' => WeightUnit::TroyOunce,
            'amount' => 1,
            'fineness' => '500',
        ])->load('stockPrice');

        // 1 oz gross at 50% purity = 0.5 oz fine x 100 USD.
        $this->assertEqualsWithDelta(50.0, $product->material_value, 0.001);
    }

    public function test_material_value_is_unchanged_for_pure_products(): void
    {
        StockPrice::create([
            'material' => Material::Gold,
            'price' => 200,
        ]);

        $product = Product::factory()->create([
            'material' => Material::Gold,
            'weight' => 1,
            'weight_unit' => WeightUnit::TroyOunce,
            'amount' => 3,
            'fineness' => '999.9',
        ])->load('stockPrice');

        $this->assertEqualsWithDelta(199.98, $product->material_value, 0.001);
        $this->assertEqualsWithDelta(599.94, $product->material_value_total, 0.001);
    }
}
