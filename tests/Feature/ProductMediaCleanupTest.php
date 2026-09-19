<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductTemplate;
use App\Models\User;
use App\Support\ProductMediaFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_deleting_a_product_removes_its_images(): void
    {
        Storage::disk('public')->put('products/front.jpg', 'x');
        Storage::disk('public')->put('products/back.jpg', 'x');
        Storage::disk('public')->put('products/media/detail.jpg', 'x');

        $product = Product::factory()->create([
            'user_id' => User::factory()->create()->id,
            'front_image_path' => 'products/front.jpg',
            'back_image_path' => 'products/back.jpg',
            'assets' => [['type' => 'image', 'label' => null, 'path' => 'products/media/detail.jpg']],
        ]);

        $product->delete();

        Storage::disk('public')->assertMissing('products/front.jpg');
        Storage::disk('public')->assertMissing('products/back.jpg');
        Storage::disk('public')->assertMissing('products/media/detail.jpg');
    }

    public function test_deleting_a_template_removes_its_images(): void
    {
        Storage::disk('public')->put('product-templates/front.jpg', 'x');

        $template = ProductTemplate::factory()->create([
            'front_image_path' => 'product-templates/front.jpg',
            'back_image_path' => null,
            'assets' => null,
        ]);

        $template->delete();

        Storage::disk('public')->assertMissing('product-templates/front.jpg');
    }

    public function test_a_file_shared_with_another_product_is_kept(): void
    {
        Storage::disk('public')->put('products/shared.jpg', 'x');

        $user = User::factory()->create();
        $first = Product::factory()->create([
            'user_id' => $user->id,
            'front_image_path' => 'products/shared.jpg',
        ]);
        Product::factory()->create([
            'user_id' => $user->id,
            'front_image_path' => 'products/shared.jpg',
        ]);

        $first->delete();

        Storage::disk('public')->assertExists('products/shared.jpg');
    }

    public function test_a_file_still_used_by_the_template_is_kept(): void
    {
        Storage::disk('public')->put('product-templates/front.jpg', 'x');

        $template = ProductTemplate::factory()->create([
            'front_image_path' => 'product-templates/front.jpg',
        ]);

        // Products created from a template reference the template's file directly.
        $product = Product::factory()->create([
            'user_id' => User::factory()->create()->id,
            'template_id' => $template->id,
            'front_image_path' => 'product-templates/front.jpg',
        ]);

        $product->delete();

        Storage::disk('public')->assertExists('product-templates/front.jpg');
    }

    public function test_the_cleanup_command_deletes_only_orphans(): void
    {
        Storage::disk('public')->put('products/used.jpg', 'x');
        Storage::disk('public')->put('products/orphan.jpg', 'x');
        Storage::disk('public')->put('products/media/orphan-asset.mp4', 'x');
        Storage::disk('public')->put('product-templates/used.jpg', 'x');
        Storage::disk('public')->put('product-templates/orphan.jpg', 'x');

        Product::factory()->create([
            'user_id' => User::factory()->create()->id,
            'front_image_path' => 'products/used.jpg',
            'back_image_path' => null,
            'assets' => null,
        ]);
        ProductTemplate::factory()->create([
            'front_image_path' => 'product-templates/used.jpg',
            'back_image_path' => null,
            'assets' => null,
        ]);

        $this->artisan('app:cleanup-product-media')->assertSuccessful();

        Storage::disk('public')->assertExists('products/used.jpg');
        Storage::disk('public')->assertExists('product-templates/used.jpg');
        Storage::disk('public')->assertMissing('products/orphan.jpg');
        Storage::disk('public')->assertMissing('products/media/orphan-asset.mp4');
        Storage::disk('public')->assertMissing('product-templates/orphan.jpg');
    }

    public function test_the_cleanup_command_dry_run_keeps_every_file(): void
    {
        Storage::disk('public')->put('products/orphan.jpg', 'x');

        $this->artisan('app:cleanup-product-media --dry-run')->assertSuccessful();

        Storage::disk('public')->assertExists('products/orphan.jpg');
        $this->assertSame(['products/orphan.jpg'], ProductMediaFiles::orphans());
    }

    public function test_the_cleanup_command_reports_when_nothing_to_do(): void
    {
        $this->artisan('app:cleanup-product-media')
            ->expectsOutputToContain('No unused product media found.')
            ->assertSuccessful();
    }
}
