<?php

namespace Tests\Feature;

use App\Filament\Resources\SupplierResource;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_manage_suppliers(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/app/suppliers')->assertStatus(200);
        $this->get('/app/suppliers/create')->assertStatus(200);
    }

    public function test_guests_cannot_access_suppliers(): void
    {
        $this->get('/app/suppliers')->assertRedirect();
    }

    public function test_users_only_see_their_own_suppliers(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Supplier::factory()->create(['user_id' => $owner->id, 'name' => 'Foreign Supplier']);
        Supplier::factory()->create(['user_id' => $other->id, 'name' => 'Own Supplier']);

        $this->actingAs($other);

        $response = $this->get('/app/suppliers');
        $response->assertOk();
        $response->assertSee('Own Supplier');
        $response->assertDontSee('Foreign Supplier');
    }

    public function test_supplier_options_are_scoped_to_the_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Supplier::factory()->create(['user_id' => $owner->id, 'name' => 'Foreign Supplier']);
        $own = Supplier::factory()->create(['user_id' => $other->id, 'name' => 'Own Supplier']);

        $this->actingAs($other);

        $this->assertSame([$own->id => 'Own Supplier'], SupplierResource::optionsForCurrentUser());
    }
}
