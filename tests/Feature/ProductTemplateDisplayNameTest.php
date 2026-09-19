<?php

namespace Tests\Feature;

use App\Enums\Material;
use App\Enums\ProductType;
use App\Enums\WeightUnit;
use App\Models\Depository;
use App\Models\ProductTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTemplateDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setLocale('de');
    }

    public function test_a_coin_template_is_labelled_with_material_name_and_year(): void
    {
        $template = ProductTemplate::factory()->create([
            'name' => 'Vreneli 20 Francs',
            'year' => 2024,
            'material' => Material::Gold->value,
            'type' => ProductType::Coin->value,
        ]);

        $this->assertSame('Goldmünze Vreneli 20 Francs 2024', $template->displayName());
    }

    public function test_an_ingot_template_is_labelled_with_weight_fineness_and_manufacturer(): void
    {
        $template = ProductTemplate::factory()->create([
            'material' => Material::Gold->value,
            'type' => ProductType::Ingot->value,
            'weight' => 10,
            'weight_unit' => WeightUnit::Gram->value,
            'fineness' => '999.9',
            'manufacturer' => 'metalor',
        ]);

        $this->assertSame('Goldbarren 10 Gramm 999.9 (Metalor)', $template->displayName());
    }

    public function test_an_ingot_template_without_a_manufacturer_omits_the_parentheses(): void
    {
        $template = ProductTemplate::factory()->create([
            'material' => Material::Silver->value,
            'type' => ProductType::Ingot->value,
            'weight' => 1,
            'weight_unit' => WeightUnit::TroyOunce->value,
            'fineness' => '999',
            'manufacturer' => null,
        ]);

        $this->assertSame('Silberbarren 1 Feinunze 999', $template->displayName());
    }

    public function test_the_weight_drops_trailing_zeros(): void
    {
        $template = ProductTemplate::factory()->create([
            'material' => Material::Gold->value,
            'type' => ProductType::Ingot->value,
            'weight' => 2.5,
            'weight_unit' => WeightUnit::TroyOunce->value,
            'fineness' => '999.9',
            'manufacturer' => null,
        ]);

        $this->assertSame('Goldbarren 2.5 Feinunze 999.9', $template->displayName());
    }

    public function test_the_product_create_form_shows_generated_template_labels(): void
    {
        $user = User::factory()->create();
        Depository::factory()->create(['user_id' => $user->id]);

        ProductTemplate::factory()->create([
            'name' => 'Ambiguous stored name',
            'year' => 2020,
            'material' => Material::Gold->value,
            'type' => ProductType::Coin->value,
        ]);

        $this->actingAs($user);

        $response = $this->get('/app/products/create');

        $response->assertStatus(200);
        $response->assertSee('Goldmünze Ambiguous stored name 2020', escape: false);
    }
}
