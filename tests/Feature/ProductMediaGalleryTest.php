<?php

namespace Tests\Feature;

use App\Models\Depository;
use App\Models\Product;
use App\Models\ProductTemplate;
use App\Models\User;
use App\Support\ProductMediaGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMediaGalleryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(array $attributes = []): Product
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        return Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            ...$attributes,
        ]);
    }

    public function test_it_is_empty_without_media(): void
    {
        $product = $this->product([
            'front_image_path' => null,
            'back_image_path' => null,
            'assets' => null,
        ]);

        $this->assertSame([], ProductMediaGallery::for($product));
    }

    public function test_it_orders_front_then_back_then_assets(): void
    {
        $product = $this->product([
            'front_image_path' => 'products/front.jpg',
            'back_image_path' => 'products/back.jpg',
            'assets' => [
                ['type' => 'image', 'label' => 'Detail', 'path' => 'products/media/detail.jpg'],
                ['type' => 'video', 'label' => null, 'path' => 'products/media/clip.mp4'],
            ],
        ]);

        $items = ProductMediaGallery::for($product);

        $this->assertCount(4, $items);
        $this->assertStringContainsString('front.jpg', $items[0]['url']);
        $this->assertStringContainsString('back.jpg', $items[1]['url']);
        $this->assertSame('Detail', $items[2]['label']);
        $this->assertSame('video', $items[3]['type']);
        $this->assertSame(__('Video'), $items[3]['label']);
    }

    public function test_it_prefers_the_inscription_texts_as_labels(): void
    {
        $product = $this->product([
            'front_image_path' => 'products/front.jpg',
            'back_image_path' => 'products/back.jpg',
            'text_front' => 'Helvetia',
            'text_back' => null,
            'assets' => null,
        ]);

        $items = ProductMediaGallery::for($product);

        $this->assertSame('Helvetia', $items[0]['label']);
        $this->assertSame(__('Back (value side)'), $items[1]['label']);
    }

    public function test_it_skips_malformed_assets(): void
    {
        $product = $this->product([
            'front_image_path' => 'products/front.jpg',
            'back_image_path' => null,
            'assets' => [
                ['type' => 'image', 'label' => 'No path'],
                ['type' => 'nonsense', 'path' => 'products/media/odd.bin'],
                'not-an-array',
            ],
        ]);

        $items = ProductMediaGallery::for($product);

        $this->assertCount(2, $items);
        $this->assertSame('image', $items[1]['type']);
        $this->assertSame(__('Image'), $items[1]['label']);
    }

    public function test_it_derives_the_type_from_the_file_path(): void
    {
        $product = $this->product([
            'front_image_path' => null,
            'back_image_path' => null,
            'assets' => [
                ['label' => null, 'path' => 'products/media/clip.mov'],
                ['label' => null, 'path' => 'products/media/detail.png'],
            ],
        ]);

        $items = ProductMediaGallery::for($product);

        $this->assertSame('video', $items[0]['type']);
        $this->assertSame('image', $items[1]['type']);
    }

    public function test_it_builds_a_gallery_for_a_template(): void
    {
        $template = ProductTemplate::factory()->create([
            'front_image_path' => 'product-templates/front.jpg',
            'back_image_path' => null,
            'assets' => [
                ['type' => 'video', 'label' => 'Clip', 'path' => 'product-templates/media/clip.mp4'],
            ],
        ]);

        $items = ProductMediaGallery::for($template);

        $this->assertCount(2, $items);
        $this->assertSame('video', $items[1]['type']);
    }
}
