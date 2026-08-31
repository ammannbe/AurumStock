<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductTableFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_can_filter_products_by_tax_year(): void
    {
        $matching = Product::factory()->create(['user_id' => $this->user->id, 'tax_year' => 2024]);
        $other = Product::factory()->create(['user_id' => $this->user->id, 'tax_year' => 2022]);

        Livewire::test(ListProducts::class)
            ->filterTable('tax_year', 2024)
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_can_filter_products_by_purchase_date_range(): void
    {
        $inRange = Product::factory()->create(['user_id' => $this->user->id, 'purchase_date' => '2024-06-15']);
        $tooEarly = Product::factory()->create(['user_id' => $this->user->id, 'purchase_date' => '2023-01-01']);
        $tooLate = Product::factory()->create(['user_id' => $this->user->id, 'purchase_date' => '2025-01-01']);

        Livewire::test(ListProducts::class)
            ->filterTable('purchase_date', [
                'purchase_date_from' => '2024-01-01',
                'purchase_date_until' => '2024-12-31',
            ])
            ->assertCanSeeTableRecords([$inRange])
            ->assertCanNotSeeTableRecords([$tooEarly, $tooLate]);
    }

    public function test_can_sort_products_by_tax_year_and_purchase_date(): void
    {
        $older = Product::factory()->create([
            'user_id' => $this->user->id,
            'tax_year' => 2020,
            'purchase_date' => '2020-01-01',
        ]);
        $newer = Product::factory()->create([
            'user_id' => $this->user->id,
            'tax_year' => 2025,
            'purchase_date' => '2025-01-01',
        ]);

        Livewire::test(ListProducts::class)
            ->sortTable('tax_year')
            ->assertCanSeeTableRecords([$older, $newer], inOrder: true)
            ->sortTable('tax_year', 'desc')
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
            ->sortTable('purchase_date')
            ->assertCanSeeTableRecords([$older, $newer], inOrder: true);
    }

    public function test_can_sort_products_by_name(): void
    {
        $first = Product::factory()->create(['user_id' => $this->user->id, 'name' => 'Alpha Coin']);
        $last = Product::factory()->create(['user_id' => $this->user->id, 'name' => 'Zeta Coin']);

        Livewire::test(ListProducts::class)
            ->sortTable('name')
            ->assertCanSeeTableRecords([$first, $last], inOrder: true)
            ->sortTable('name', 'desc')
            ->assertCanSeeTableRecords([$last, $first], inOrder: true);
    }
}
