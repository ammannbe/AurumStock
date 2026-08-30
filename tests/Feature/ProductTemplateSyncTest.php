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
        ]);

        $this->assertNotNull($product->source_hash);
        $this->assertSame($template->source_hash, $product->source_hash);
        $this->assertTrue($product->isSynchronizedWithTemplate());
    }

    public function test_a_locally_overridden_field_is_reported_as_a_difference(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $template = ProductTemplate::factory()->create(['name' => 'Vreneli 20 Francs']);

        $product = Product::fromTemplate($template, [
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'name' => 'My personal copy',
        ]);

        $this->assertFalse($product->isSynchronizedWithTemplate());
        $this->assertSame(['name'], array_column($product->templateDifferences(), 'field'));
    }

    public function test_product_falls_out_of_sync_when_template_changes(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $template = ProductTemplate::factory()->create(['name' => 'Vreneli 20 Francs']);

        $product = Product::fromTemplate($template, [
            'user_id' => $user->id,
            'depository_id' => $depository->id,
        ]);

        $this->assertTrue($product->isSynchronizedWithTemplate());

        $template->update(['name' => 'Vreneli 20 Francs (revised)']);

        $this->assertFalse($product->fresh()->isSynchronizedWithTemplate());
    }

    public function test_differences_are_reported_per_field(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $template = ProductTemplate::factory()->create([
            'name' => 'Vreneli',
            'mintage' => 2500,
        ]);

        $product = Product::fromTemplate($template, [
            'user_id' => $user->id,
            'depository_id' => $depository->id,
        ]);

        $this->assertSame([], $product->templateDifferences());

        $template->update(['name' => 'Vreneli revised', 'mintage' => 9999]);

        $differences = $product->fresh()->templateDifferences();
        $fields = array_column($differences, 'field');

        $this->assertContains('name', $fields);
        $this->assertContains('mintage', $fields);
        $this->assertCount(2, $differences);

        $name = collect($differences)->firstWhere('field', 'name');
        $this->assertSame('Vreneli', $name['product']);
        $this->assertSame('Vreneli revised', $name['template']);
    }

    public function test_sync_now_applies_template_values_and_restores_sync_state(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $template = ProductTemplate::factory()->create(['name' => 'Vreneli']);

        $product = Product::fromTemplate($template, [
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'price' => 250,
        ]);

        $template->update(['name' => 'Vreneli revised', 'mintage' => 4242]);

        $product = $product->fresh();
        $this->assertFalse($product->isSynchronizedWithTemplate());

        $product->syncFromTemplate();

        $product = $product->fresh();

        $this->assertSame('Vreneli revised', $product->name);
        $this->assertSame(4242, $product->mintage);
        $this->assertSame([], $product->templateDifferences());
        $this->assertTrue($product->isSynchronizedWithTemplate());
        $this->assertSame('250.00', $product->price);
    }

    public function test_purchase_details_do_not_affect_template_hash(): void
    {
        $template = ProductTemplate::factory()->create();
        $hash = $template->source_hash;

        $template->update(['notes' => $template->notes]);

        $this->assertSame($hash, $template->fresh()->source_hash);
    }

    public function test_product_matching_its_template_is_in_sync_without_a_stored_hash(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);
        $template = ProductTemplate::factory()->create();

        $product = Product::fromTemplate($template, [
            'user_id' => $user->id,
            'depository_id' => $depository->id,
        ]);

        // Mirrors a product linked through the form, which never wrote a hash.
        $product->forceFill(['source_hash' => null])->saveQuietly();

        $product = $product->fresh();

        $this->assertSame([], $product->templateDifferences());
        $this->assertTrue($product->isSynchronizedWithTemplate());
    }

    public function test_linking_a_template_stores_the_current_hash(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);
        $template = ProductTemplate::factory()->create();

        $product = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
        ]);

        $this->assertNull($product->source_hash);

        $product->update(['template_id' => $template->id]);

        $this->assertSame($template->source_hash, $product->fresh()->source_hash);
    }

    public function test_unlinking_a_template_clears_the_hash(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);
        $template = ProductTemplate::factory()->create();

        $product = Product::fromTemplate($template, [
            'user_id' => $user->id,
            'depository_id' => $depository->id,
        ]);

        $product->update(['template_id' => null]);

        $this->assertNull($product->fresh()->source_hash);
    }

    public function test_every_synced_field_has_a_translated_label(): void
    {
        $this->app->setLocale('de');

        foreach (ProductTemplate::SYNCED_FIELDS as $field) {
            $label = ProductTemplate::fieldLabel($field);

            $this->assertNotSame('', $label);
            $this->assertStringNotContainsString('_', $label, "Field [{$field}] has no readable label.");
        }

        $this->assertSame('Gewicht pro Stück', ProductTemplate::fieldLabel('weight'));
        $this->assertSame('Herkunftsland', ProductTemplate::fieldLabel('country_of_origin'));
    }

    public function test_difference_view_renders_field_labels_and_empty_values(): void
    {
        $html = view('filament.products.template-differences', [
            'differences' => [
                ['field' => 'country_of_origin', 'product' => null, 'template' => 'CH'],
            ],
        ])->render();

        $this->assertStringContainsString(ProductTemplate::fieldLabel('country_of_origin'), $html);
        $this->assertStringContainsString('diff-empty', $html);
        $this->assertStringContainsString('CH', $html);
    }

    public function test_product_edit_page_renders_with_sync_status_and_chart(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);
        $template = ProductTemplate::factory()->create();

        $product = Product::fromTemplate($template, [
            'user_id' => $user->id,
            'depository_id' => $depository->id,
        ]);

        $this->actingAs($user);

        $this->get("/app/products/{$product->id}/edit")->assertStatus(200);
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
