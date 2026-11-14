<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Depository;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->test(CreateProduct::class)
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

    public function test_the_files_are_not_copied_until_the_form_is_actually_saved(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $frontPath = UploadedFile::fake()->image('front.jpg')->storeAs('products', 'front.jpg', 'public');

        $product = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'front_image_path' => $frontPath,
        ]);

        $this->actingAs($user);

        Livewire::withQueryParams(['duplicate' => $product->getKey()])
            ->test(CreateProduct::class);

        $disk = Storage::disk('public');
        $filesAfterMountingOnly = collect($disk->files('products'))->reject(fn (string $file) => $file === $frontPath);

        $this->assertEmpty($filesAfterMountingOnly, 'No file should be copied just from opening the create form.');
    }

    public function test_duplicating_and_saving_copies_the_image_files_instead_of_sharing_them(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $frontPath = UploadedFile::fake()->image('front.jpg')->storeAs('products', 'front.jpg', 'public');
        $mediaPath = UploadedFile::fake()->image('media.jpg')->storeAs('products/media', 'media.jpg', 'public');

        $product = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'front_image_path' => $frontPath,
            'assets' => [['path' => $mediaPath, 'label' => 'Extra']],
        ]);

        $this->actingAs($user);

        Livewire::withQueryParams(['duplicate' => $product->getKey()])
            ->test(CreateProduct::class)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('products', 2);

        $disk = Storage::disk('public');

        $copiedFiles = collect($disk->files('products'))->reject(fn (string $file) => $file === $frontPath);
        $this->assertNotEmpty($copiedFiles, 'Expected a copied front image file to exist.');

        $copiedMediaFiles = collect($disk->files('products/media'))->reject(fn (string $file) => $file === $mediaPath);
        $this->assertNotEmpty($copiedMediaFiles, 'Expected a copied media asset file to exist.');

        // The original files must remain untouched.
        $this->assertTrue($disk->exists($frontPath));
        $this->assertTrue($disk->exists($mediaPath));

        $duplicate = Product::query()->whereKeyNot($product->id)->firstOrFail();
        $this->assertNotSame($product->front_image_path, $duplicate->front_image_path);
        $this->assertNotSame($product->assets[0]['path'], $duplicate->assets[0]['path']);
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
            ->test(CreateProduct::class)
            ->assertSchemaStateSet(['name' => null]);
    }
}
