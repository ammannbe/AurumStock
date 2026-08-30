<?php

namespace Tests\Feature;

use App\Enums\Manufacturer;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Depository;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductManufacturerFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Depository $depository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->depository = Depository::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user);
    }

    private function product(?string $manufacturer): Product
    {
        return Product::factory()->create([
            'user_id' => $this->user->id,
            'depository_id' => $this->depository->id,
            'manufacturer' => $manufacturer,
        ]);
    }

    public function test_it_filters_products_by_manufacturer(): void
    {
        $swissmint = $this->product(Manufacturer::Swissmint->value);
        $perth = $this->product(Manufacturer::PerthMint->value);

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$swissmint, $perth])
            ->filterTable('manufacturer', [Manufacturer::Swissmint->value])
            ->assertCanSeeTableRecords([$swissmint])
            ->assertCanNotSeeTableRecords([$perth]);
    }

    public function test_it_allows_selecting_several_manufacturers(): void
    {
        $swissmint = $this->product(Manufacturer::Swissmint->value);
        $perth = $this->product(Manufacturer::PerthMint->value);
        $metalor = $this->product(Manufacturer::Metalor->value);

        Livewire::test(ListProducts::class)
            ->filterTable('manufacturer', [
                Manufacturer::Swissmint->value,
                Manufacturer::PerthMint->value,
            ])
            ->assertCanSeeTableRecords([$swissmint, $perth])
            ->assertCanNotSeeTableRecords([$metalor]);
    }

    public function test_the_options_only_contain_manufacturers_in_use(): void
    {
        $this->product(Manufacturer::Swissmint->value);
        $this->product(null);

        $options = (new \ReflectionMethod(
            ProductResource::class,
            'manufacturerFilterOptions'
        ))->invoke(null);

        $this->assertSame(
            [Manufacturer::Swissmint->value => Manufacturer::Swissmint->label()],
            $options
        );
    }

    public function test_a_value_outside_the_enum_still_gets_an_option(): void
    {
        $this->product('a_legacy_mint');

        $options = (new \ReflectionMethod(
            ProductResource::class,
            'manufacturerFilterOptions'
        ))->invoke(null);

        $this->assertSame(['a_legacy_mint' => 'a_legacy_mint'], $options);
    }

    public function test_the_list_renders_with_a_manufacturer_outside_the_enum(): void
    {
        $product = $this->product('a_legacy_mint');

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$product])
            ->assertSuccessful();
    }

    public function test_it_only_offers_manufacturers_from_visible_products(): void
    {
        $this->product(Manufacturer::Swissmint->value);

        $otherUser = User::factory()->create();
        $otherDepository = Depository::factory()->create(['user_id' => $otherUser->id]);

        Product::factory()->create([
            'user_id' => $otherUser->id,
            'depository_id' => $otherDepository->id,
            'manufacturer' => Manufacturer::PerthMint->value,
        ]);

        $options = (new \ReflectionMethod(
            ProductResource::class,
            'manufacturerFilterOptions'
        ))->invoke(null);

        $this->assertArrayHasKey(Manufacturer::Swissmint->value, $options);
        $this->assertArrayNotHasKey(Manufacturer::PerthMint->value, $options);
    }
}
