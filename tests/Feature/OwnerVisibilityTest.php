<?php

namespace Tests\Feature;

use App\Models\Depository;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OwnerVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sharing_tables_do_not_exist(): void
    {
        $this->assertFalse(Schema::hasTable('product_shares'));
        $this->assertFalse(Schema::hasTable('depository_shares'));
    }

    public function test_users_only_see_their_own_products(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();

        $depository = Depository::factory()->create(['user_id' => $owner->id]);

        Product::factory()->create([
            'user_id' => $owner->id,
            'depository_id' => $depository->id,
            'name' => 'Another User Product',
        ]);

        $recipientDepository = Depository::factory()->create(['user_id' => $recipient->id]);
        Product::factory()->create([
            'user_id' => $recipient->id,
            'depository_id' => $recipientDepository->id,
            'name' => 'Own Product',
        ]);

        $this->actingAs($recipient);

        $response = $this->get('/app/products');

        $response->assertOk();
        $response->assertSee('Own Product');
        $response->assertDontSee('Another User Product');
    }

    public function test_users_only_see_their_own_depositories(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();

        Depository::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Another User Depository',
        ]);
        Depository::factory()->create(['user_id' => $recipient->id, 'name' => 'Own Depository']);

        $this->actingAs($recipient);

        $depositoriesResponse = $this->get('/app/depositories');
        $depositoriesResponse->assertOk();
        $depositoriesResponse->assertSee('Own Depository');
        $depositoriesResponse->assertDontSee('Another User Depository');
    }

    public function test_only_owners_can_view_or_update_records(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();

        $depository = Depository::factory()->create(['user_id' => $owner->id]);
        $product = Product::factory()->create([
            'user_id' => $owner->id,
            'depository_id' => $depository->id,
        ]);

        $this->assertFalse($recipient->can('view', $product));
        $this->assertFalse($recipient->can('update', $product));
        $this->assertFalse($recipient->can('view', $depository));
        $this->assertFalse($recipient->can('update', $depository));
        $this->assertTrue($owner->can('view', $product));
        $this->assertTrue($owner->can('update', $product));
    }
}
