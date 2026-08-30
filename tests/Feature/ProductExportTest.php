<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductSpreadsheetExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProductExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_export_contains_product_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = Product::factory()->create(['user_id' => $user->id, 'name' => 'Krugerrand', 'tax_year' => 2024]);

        $response = ProductSpreadsheetExporter::download(collect([$product]), 'csv');
        $content = file_get_contents($response->getFile()->getPathname());

        $this->assertStringContainsString('Krugerrand', $content);
        $this->assertStringContainsString('2024', $content);
    }

    public function test_ods_export_produces_a_file(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = Product::factory()->create(['user_id' => $user->id]);

        $response = ProductSpreadsheetExporter::download(collect([$product]), 'ods');

        $this->assertGreaterThan(0, $response->getFile()->getSize());
    }

    public function test_export_only_includes_visible_products(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);

        $own = Product::factory()->create(['user_id' => $user->id, 'name' => 'Own Bar']);
        Product::factory()->create(['user_id' => $other->id, 'name' => 'Foreign Bar']);

        /** @var Collection<int, Product> $visible */
        $visible = ProductResource::getEloquentQuery()->get();

        $this->assertTrue($visible->contains('id', $own->id));
        $this->assertCount(1, $visible);

        $response = ProductSpreadsheetExporter::download($visible, 'csv');
        $content = file_get_contents($response->getFile()->getPathname());

        $this->assertStringContainsString('Own Bar', $content);
        $this->assertStringNotContainsString('Foreign Bar', $content);
    }
}
