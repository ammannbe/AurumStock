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
}
