<?php

namespace Tests\Feature;

use App\Enums\SharePermission;
use App\Models\Depository;
use App\Models\DepositoryShare;
use App\Models\Product;
use App\Models\ProductShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharingVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_shared_product_is_visible_for_direct_product_share(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();

        $depository = Depository::factory()->create(['user_id' => $owner->id]);

        $sharedProduct = Product::factory()->create([
            'user_id' => $owner->id,
            'depository_id' => $depository->id,
            'name' => 'Shared Product A',
        ]);

        Product::factory()->create([
            'user_id' => $owner->id,
            'depository_id' => $depository->id,
            'name' => 'Private Product B',
        ]);

        ProductShare::create([
            'product_id' => $sharedProduct->id,
            'owner_user_id' => $owner->id,
            'shared_with_user_id' => $recipient->id,
            'permission' => SharePermission::View,
        ]);

        $this->actingAs($recipient);

        $response = $this->get('/app/products');

        $response->assertOk();
        $response->assertSee('Shared Product A');
        $response->assertDontSee('Private Product B');
    }

    public function test_depository_share_makes_all_contained_products_visible(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();

        $sharedDepository = Depository::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Shared Depository',
        ]);

        Product::factory()->create([
            'user_id' => $owner->id,
            'depository_id' => $sharedDepository->id,
            'name' => 'Depository Product 1',
        ]);

        Product::factory()->create([
            'user_id' => $owner->id,
            'depository_id' => $sharedDepository->id,
            'name' => 'Depository Product 2',
        ]);

        DepositoryShare::create([
            'depository_id' => $sharedDepository->id,
            'owner_user_id' => $owner->id,
            'shared_with_user_id' => $recipient->id,
            'permission' => SharePermission::View,
        ]);

        $this->actingAs($recipient);

        $depositoriesResponse = $this->get('/app/depositories');
        $depositoriesResponse->assertOk();
        $depositoriesResponse->assertSee('Shared Depository');

        $productsResponse = $this->get('/app/products');
        $productsResponse->assertOk();
        $productsResponse->assertSee('Depository Product 1');
        $productsResponse->assertSee('Depository Product 2');
    }

    public function test_share_permissions_control_update_access(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();

        $depository = Depository::factory()->create(['user_id' => $owner->id]);
        $product = Product::factory()->create([
            'user_id' => $owner->id,
            'depository_id' => $depository->id,
        ]);

        ProductShare::create([
            'product_id' => $product->id,
            'owner_user_id' => $owner->id,
            'shared_with_user_id' => $recipient->id,
            'permission' => SharePermission::View,
        ]);

        $this->assertFalse($recipient->can('update', $product));

        ProductShare::query()
            ->where('product_id', $product->id)
            ->where('shared_with_user_id', $recipient->id)
            ->update(['permission' => SharePermission::Edit->value]);

        $product->refresh();

        $this->assertTrue($recipient->can('update', $product));
    }
}
