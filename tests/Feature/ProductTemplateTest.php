<?php

namespace Tests\Feature;

use App\Enums\Material;
use App\Enums\ProductType;
use App\Enums\WeightUnit;
use App\Filament\UserResources\ProductTemplateResource;
use App\Models\Depository;
use App\Models\Product;
use App\Models\ProductTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
