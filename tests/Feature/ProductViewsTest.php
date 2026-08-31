<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Depository;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_the_create_form_defaults_to_the_profile_currency(): void
    {
        $user = User::factory()->create(['preferred_currency' => Currency::CHF]);
        Depository::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        Livewire::test(CreateProduct::class)
            ->assertSchemaStateSet(['price_currency' => Currency::CHF->value]);
    }

    public function test_the_media_repeater_uses_a_translated_add_label(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->app->setLocale('de');

        $response = $this->get('/app/products/create');

        $response->assertStatus(200);
        $response->assertSee('Medium hinzufügen', escape: false);
        $response->assertDontSee('Add to Zusätzliche Medien', escape: false);
    }

    public function test_the_inscription_fields_use_the_mongolian_capable_font(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/app/products/create');

        $response->assertStatus(200);
        $response->assertSee('aurum-inscription', escape: false);
        $this->assertFileExists(public_path('fonts/app/noto-sans-mongolian.woff2'));
    }

    public function test_the_products_table_renders_all_media_in_the_slider(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'front_image_path' => 'products/front.jpg',
            'back_image_path' => 'products/back.jpg',
            'assets' => [
                ['type' => 'image', 'label' => 'Detail', 'path' => 'products/media/detail.jpg'],
                ['type' => 'video', 'label' => 'Clip', 'path' => 'products/media/clip.mp4'],
            ],
        ]);

        $this->actingAs($user);

        Livewire::test(ListProducts::class)
            ->assertSee('data-gallery', escape: false)
            ->assertSee('clip.mp4', escape: false)
            ->assertSee('+2', escape: false);
    }

    public function test_the_products_table_is_sorted_by_purchase_date_descending(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $older = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'purchase_date' => '2020-01-01',
        ]);

        $newer = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'purchase_date' => '2024-06-01',
        ]);

        $this->actingAs($user);

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
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

    public function test_user_can_store_weight_with_four_decimal_places(): void
    {
        $user = User::factory()->create();
        $depo = Depository::create(['user_id' => $user->id, 'name' => 'Home', 'location' => 'Box']);

        $product = Product::create([
            'name' => 'Precise Weight',
            'type' => 1,
            'material' => 1,
            'weight' => 5.8065,
            'weight_unit' => 2,
            'amount' => 1,
            'depository_id' => $depo->id,
            'user_id' => $user->id,
        ]);

        $product->refresh();

        $this->assertSame('5.8065', $product->weight);
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

    public function test_products_index_renders_with_existing_products(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $depo = Depository::create(['user_id' => $user->id, 'name' => 'Home', 'location' => 'Box']);

        // create a product (casts will make weight_unit an enum on model instances)
        Product::create([
            'name' => 'Index Product',
            'type' => 1,
            'material' => 1,
            'weight' => 31.1035,
            'weight_unit' => 2,
            'amount' => 1,
            'depository_id' => $depo->id,
            'user_id' => $user->id,
        ]);

        $response = $this->get('/app/products');
        $response->assertStatus(200);
    }
}
