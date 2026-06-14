<?php

namespace Tests\Feature;

use App\Models\Depository;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_product()
    {
        $user = User::factory()->create();
        $depot = Depository::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $product = Product::factory()->create([
            'user_id' => $user->id,
            'depository_id' => $depot->id,
        ]);

        $this->assertEquals($user->id, $product->owner->id);
    }
}
