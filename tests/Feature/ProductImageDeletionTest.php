<?php

namespace Tests\Feature;

use App\Models\Depository;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_front_image_delete_removes_image_not_product()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user);

        /** @var \App\Models\Product $product */
        $product = Product::factory()->create(['user_id' => $user->id]);

        $path = "products/{$product->id}/front.jpg";
        Storage::disk('public')->put($path, 'dummy');
        $product->update(['front_image_path' => $path]);

        $response = $this->delete(route('products.front_image.destroy', $product));

        $response->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertNull($product->fresh()->front_image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_back_image_delete_removes_image_not_product()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user);

        /** @var \App\Models\Product $product */
        $product = Product::factory()->create(['user_id' => $user->id]);

        $path = "products/{$product->id}/back.png";
        Storage::disk('public')->put($path, 'dummy');
        $product->update(['back_image_path' => $path]);

        $response = $this->delete(route('products.back_image.destroy', $product));

        $response->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertNull($product->fresh()->back_image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_destroy_asset_only_removes_asset_and_file()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user);

        /** @var \App\Models\Product $product */
        $product = Product::factory()->create(['user_id' => $user->id]);

        // legacy string asset and structured asset
        $path1 = "products/{$product->id}/a.jpg";
        $path2 = "products/{$product->id}/b.pdf";
        Storage::disk('public')->put($path1, 'a');
        Storage::disk('public')->put($path2, 'b');

        $product->update(['assets' => [$path1, ['path' => $path2, 'original_name' => 'b.pdf', 'mime' => 'application/pdf']]]);

        // remove first asset (legacy string)
        $response = $this->delete(route('products.assets.destroy', ['product' => $product, 'index' => 0]));
        $response->assertRedirect();

        $fresh = $product->fresh();
        $this->assertCount(1, $fresh->assets);
        Storage::disk('public')->assertMissing($path1);
        Storage::disk('public')->assertExists($path2);
    }

    public function test_notes_are_stored_on_create()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $payload = [
            'name' => 'Test',
            'material' => 1,
            'weight' => 10,
            'weight_unit' => 1,
            'amount' => 1,
            'depository_id' => $depository->id,
            'notes' => 'Some notes here',
        ];

        $response = $this->post(route('products.store'), $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('products', ['notes' => 'Some notes here']);
    }
}
