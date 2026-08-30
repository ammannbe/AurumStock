<?php

namespace Tests\Feature;

use App\Models\Depository;
use App\Models\Product;
use App\Models\ProductTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTemplateSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_tracks_template_sync_hash(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $template = ProductTemplate::factory()->create([
            'name' => 'Vreneli 20 Francs',
            'material' => 1,
            'type' => 2,
            'year' => 2024,
        ]);

        $product = Product::fromTemplate($template, [
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'name' => 'My personal copy',
        ]);

        $this->assertNotNull($product->source_hash);
        $this->assertSame($template->source_hash, $product->source_hash);
        $this->assertTrue($product->isSynchronizedWithTemplate());
    }

    public function test_removing_a_product_image_keeps_the_template_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $path = 'templates/front.png';
        Storage::disk('public')->put($path, 'template-image');

        $template = ProductTemplate::factory()->create([
            'front_image_path' => $path,
        ]);

        $product = Product::fromTemplate($template, [
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'front_image_path' => $path,
        ]);

        $product->deleteMediaField('front_image_path');

        $this->assertNull($product->fresh()->front_image_path);
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertSame($path, $template->fresh()->front_image_path);
    }
}
