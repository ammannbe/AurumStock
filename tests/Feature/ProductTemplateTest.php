<?php

namespace Tests\Feature;

use App\Enums\Material;
use App\Enums\Packaging;
use App\Enums\ProductType;
use App\Enums\Quality;
use App\Enums\WeightUnit;
use App\Filament\UserResources\ProductTemplateResource;
use App\Filament\UserResources\ProductTemplateResource\Pages\CreateProductTemplate;
use App\Models\Depository;
use App\Models\Product;
use App\Models\ProductTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_product_from_a_template(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $template = ProductTemplate::factory()->create([
            'name' => 'Vreneli 20 Francs',
            'year' => 2024,
            'material' => Material::Gold->value,
            'type' => ProductType::Coin->value,
            'weight' => 7.8125,
            'weight_unit' => WeightUnit::Gram->value,
            'manufacturer' => 'Swissmint',
            'country_of_origin' => 'CH',
            'fineness' => '999.9',
            'mintage' => 2500,
            'face_value' => 20,
            'text_front' => 'Helvetia',
            'text_back' => 'Swiss cross',
        ]);

        $product = Product::fromTemplate($template, [
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'name' => 'My personal copy',
        ]);

        $this->assertSame('My personal copy', $product->name);
        $this->assertSame(Material::Gold->value, $product->material->value);
        $this->assertSame(ProductType::Coin->value, $product->type->value);
        $this->assertSame(7.8125, (float) $product->weight);
        $this->assertSame('Swissmint', $product->manufacturer);
        $this->assertSame('Helvetia', $product->text_front);
        $this->assertSame('Swiss cross', $product->text_back);
        $this->assertSame($template->id, $product->template_id);
    }

    public function test_admin_user_can_access_product_template_resource(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        $this->assertTrue(ProductTemplateResource::canViewAny());
    }

    public function test_non_admin_user_cannot_access_product_template_resource(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user);
        $this->assertFalse(ProductTemplateResource::canViewAny());
    }

    public function test_template_can_be_created_from_a_product(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $product = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'name' => 'Krugerrand 1oz',
            'year' => 2023,
            'material' => Material::Gold->value,
            'type' => ProductType::Coin->value,
            'weight' => 31.1035,
            'weight_unit' => WeightUnit::Gram->value,
            'manufacturer' => 'rand_refinery',
            'country_of_origin' => 'ZA',
            'fineness' => '916.7',
            'quality' => Quality::MintState->value,
            'packaging' => Packaging::CoinCapsule->value,
            'text_front' => 'Paul Kruger',
            'text_back' => 'Springbok',
        ]);

        $template = ProductTemplate::createFromProduct($product, 'Krugerrand Reference');

        $this->assertDatabaseHas('product_templates', [
            'id' => $template->id,
            'name' => 'Krugerrand Reference',
        ]);

        $fresh = $template->fresh();

        $this->assertSame(Material::Gold, $fresh->material);
        $this->assertSame(ProductType::Coin, $fresh->type);
        $this->assertSame(31.1035, (float) $fresh->weight);
        $this->assertSame('rand_refinery', $fresh->manufacturer);
        $this->assertSame('ZA', $fresh->country_of_origin);
        $this->assertSame('Paul Kruger', $fresh->text_front);
        $this->assertSame('Springbok', $fresh->text_back);
        $this->assertNotNull($fresh->source_hash);

        foreach (['amount', 'certificate', 'packaging', 'price', 'price_currency', 'reference_number', 'purchase_date', 'tax_year', 'quality'] as $removed) {
            $this->assertArrayNotHasKey($removed, $fresh->getAttributes());
        }
    }

    public function test_admin_can_render_product_template_list_with_records(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        ProductTemplate::factory()->count(3)->create();

        $this->actingAs($admin);

        $this->get('/app/product-templates')->assertStatus(200);
    }

    public function test_template_pages_render_translated_labels(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $template = ProductTemplate::factory()->create(['manufacturer' => 'swissmint']);

        $this->actingAs($admin);
        $this->app->setLocale('de');

        $create = $this->get('/app/product-templates/create');
        $create->assertStatus(200);

        foreach (['Jahr', 'Hersteller', 'Feingehalt', 'Auflage', 'Nennwert', 'Gewichtseinheit'] as $label) {
            $create->assertSee($label, escape: false);
        }

        $create->assertDontSee('Face value', escape: false);
        $create->assertDontSee('Weight unit', escape: false);

        $list = $this->get('/app/product-templates');
        $list->assertStatus(200);
        $list->assertSee('Swissmint', escape: false);
    }

    public function test_the_create_form_uses_the_expected_defaults(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(CreateProductTemplate::class)
            ->assertSchemaStateSet([
                'weight' => 1,
                'weight_unit' => WeightUnit::TroyOunce->value,
            ]);
    }

    public function test_the_media_repeater_uses_a_translated_add_label(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $this->app->setLocale('de');

        $response = $this->get('/app/product-templates/create');

        $response->assertStatus(200);
        $response->assertSee('Medium hinzufügen', escape: false);
        $response->assertDontSee('Add to Zusätzliche Medien', escape: false);
    }

    public function test_template_edit_url_points_at_the_user_panel(): void
    {
        $template = ProductTemplate::factory()->create();

        $url = ProductTemplateResource::getUrl('edit', ['record' => $template]);

        $this->assertStringContainsString("/app/product-templates/{$template->id}/edit", $url);
    }

    public function test_template_created_from_product_falls_back_to_product_name(): void
    {
        $user = User::factory()->create();
        $depository = Depository::factory()->create(['user_id' => $user->id]);

        $product = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depository->id,
            'name' => 'Maple Leaf 1oz',
        ]);

        $template = ProductTemplate::createFromProduct($product);

        $this->assertSame('Maple Leaf 1oz', $template->name);
    }
}
