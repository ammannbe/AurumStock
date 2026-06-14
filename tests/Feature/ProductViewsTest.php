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

    public function test_authenticated_user_can_view_products_index_and_create()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/products');
        $response->assertStatus(200);

        $response = $this->get('/products/create');
        $response->assertStatus(200);
    }

    public function test_user_can_create_product_and_see_it_in_index()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $depo = Depository::create(['user_id' => $user->id, 'name' => 'Home', 'location' => 'Box']);

        $response = $this->post('/products', [
            'name' => 'Test Gold',
            'material' => 1,
            'weight' => 31.1035,
            'weight_unit' => 2,
            'amount' => 1,
            'depository_id' => $depo->id,
        ]);

        $response->assertRedirect('/products');

        $this->assertDatabaseHas('products', ['name' => 'Test Gold', 'user_id' => $user->id]);

        $index = $this->get('/products');
        $index->assertStatus(200);
        $index->assertSee('Test Gold');

        // edit
        $product = Product::where('name', 'Test Gold')->first();
        $this->put('/products/'.$product->id, [
            'name' => 'Test Gold Updated',
            'material' => 1,
            'weight' => 31.1035,
            'weight_unit' => 2,
            'amount' => 1,
            'depository_id' => $depo->id,
        ])->assertRedirect('/products');

        $this->assertDatabaseHas('products', ['name' => 'Test Gold Updated']);

        // export CSV
        $export = $this->get('/products/export');
        $export->assertStatus(200);
        $this->assertStringContainsString('text/csv', $export->headers->get('Content-Type'));
    }
}
