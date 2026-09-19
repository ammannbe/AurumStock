<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Depository;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductReplicateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_duplicate_action_links_to_the_create_page_with_the_record_id(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
        ]);

        $this->actingAs($user);

        Livewire::test(ListProducts::class)
            ->assertTableActionHasUrl('duplicate', route('filament.user.resources.products.create').'?duplicate='.$product->getKey(), $product);
    }

    public function test_the_duplicate_action_is_hidden_for_another_users_product(): void
    {
        $owner = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $owner->id]);
        $product = Product::factory()->create([
            'user_id' => $owner->id,
            'depository_id' => $depository->id,
        ]);

        $other = User::factory()->create();
        $this->actingAs($other);

        Livewire::test(ListProducts::class)
            ->assertCanNotSeeTableRecords([$product]);
    }

    public function test_the_create_form_is_prefilled_from_the_duplicated_product(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'order_id' => $order->id,
            'purchase_date' => now()->subDay(),
            'tax_year' => 2024,
        ]);

        $this->actingAs($user);

        $this->get('/app/products/create?duplicate='.$product->getKey())->assertStatus(200);

        Livewire::withQueryParams(['duplicate' => $product->getKey()])
            ->test(CreateProduct::class) // @phpstan-ignore argument.templateType
            ->assertSchemaStateSet([
                'name' => $product->name,
                'depository_id' => $depository->id,
                'order_id' => null,
                'purchase_date' => null,
                'tax_year' => null,
            ]);

        $this->assertDatabaseCount('products', 1);
    }

    public function test_the_create_page_renders_when_the_duplicated_product_has_media_assets(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'assets' => [['path' => 'products/media/example.jpg', 'label' => 'Example']],
        ]);

        $this->actingAs($user);

        $this->get('/app/products/create?duplicate='.$product->getKey())->assertStatus(200);
    }

    public function test_the_create_form_ignores_another_users_product_id(): void
    {
        $owner = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $owner->id]);
        $product = Product::factory()->create([
            'user_id' => $owner->id,
            'depository_id' => $depository->id,
            'name' => 'Owner Only Product',
        ]);

        $other = User::factory()->create();
        $this->actingAs($other);

        Livewire::withQueryParams(['duplicate' => $product->getKey()])
            ->test(CreateProduct::class) // @phpstan-ignore argument.templateType
            ->assertSchemaStateSet(['name' => null]);
    }
}
