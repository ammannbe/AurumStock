<?php

namespace Tests\Feature;

use App\Models\Depository;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_products_index_and_create(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/app/products');
        $response->assertStatus(200);

        $response = $this->get('/app/products/create');
        $response->assertStatus(200);
    }

    public function test_user_can_create_product_via_model(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $depo = Depository::create(['user_id' => $user->id, 'name' => 'Home', 'location' => 'Box']);

        $product = Product::create([
            'name' => 'Test Gold',
            'type' => 1,
            'material' => 1,
            'weight' => 31.1035,
            'weight_unit' => 2,
            'amount' => 1,
            'depository_id' => $depo->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('products', ['name' => 'Test Gold', 'user_id' => $user->id]);
        $this->assertEquals($depo->id, $product->depository_id);
        $this->assertEquals($user->id, $product->user_id);
    }

    public function test_user_can_update_product_via_model(): void
    {
        $user = User::factory()->create();
        $depo = Depository::create(['user_id' => $user->id, 'name' => 'Home', 'location' => 'Box']);

        $product = Product::create([
            'name' => 'Test Gold',
            'type' => 1,
            'material' => 1,
            'weight' => 31.1035,
            'weight_unit' => 2,
            'amount' => 1,
            'depository_id' => $depo->id,
            'user_id' => $user->id,
        ]);

        $product->update(['name' => 'Test Gold Updated']);

        $this->assertDatabaseHas('products', ['name' => 'Test Gold Updated', 'id' => $product->id]);
    }
}
